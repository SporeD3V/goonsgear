"""Deploy changed files to staging via SFTP.

Usage:
  python deploy.py                  # deploy all uncommitted changes (git diff)
  python deploy.py file1 file2 ...  # deploy specific files
"""

import os
import subprocess
import sys

import paramiko

REMOTE_HOST = '91.98.230.33'
REMOTE_PORT = 1221
REMOTE_USER = 'spored3v'
REMOTE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

# Directories that should never be deployed
EXCLUDED_PREFIXES = (
    'node_modules/', 'vendor/', 'storage/', '.git/', '.github/',
    'tests/', 'bootstrap/cache/', '_',
)
EXCLUDED_EXTENSIONS = ('.py', '.sql', '.md', '.neon')


def get_changed_files():
    """Return files changed vs HEAD (staged + unstaged + untracked app files)."""
    result = subprocess.run(
        ['git', 'diff', '--name-only', 'HEAD'],
        capture_output=True, text=True, check=True,
    )
    files = set(result.stdout.strip().splitlines())

    # Also include staged but not yet committed
    result2 = subprocess.run(
        ['git', 'diff', '--name-only', '--cached'],
        capture_output=True, text=True, check=True,
    )
    files.update(result2.stdout.strip().splitlines())

    return sorted(f for f in files if f)


def should_deploy(filepath):
    """Filter out files that don't belong on the server."""
    if any(filepath.startswith(p) for p in EXCLUDED_PREFIXES):
        return False
    if any(filepath.endswith(ext) for ext in EXCLUDED_EXTENSIONS):
        return False
    if not os.path.isfile(filepath):
        return False
    return True


def main():
    password = os.environ.get('GOONSGEAR_SSH_PASSWORD', '')
    if not password:
        raise RuntimeError('Missing GOONSGEAR_SSH_PASSWORD environment variable.')

    # Determine which files to deploy
    if len(sys.argv) > 1:
        files = sys.argv[1:]
    else:
        files = get_changed_files()
        files = [f for f in files if should_deploy(f)]

    # If any frontend source files changed, include compiled build assets
    frontend_extensions = ('.js', '.css', '.vue', '.ts', '.tsx')
    frontend_dirs = ('resources/js/', 'resources/css/', 'resources/views/')
    has_frontend_changes = any(
        any(f.startswith(d) for d in frontend_dirs) or any(f.endswith(e) for e in frontend_extensions)
        for f in files
    )
    if has_frontend_changes and len(sys.argv) <= 1:
        build_dir = os.path.join('public', 'build')
        if os.path.isdir(build_dir):
            for root, _dirs, filenames in os.walk(build_dir):
                for filename in filenames:
                    build_file = os.path.join(root, filename).replace('\\', '/')
                    if build_file not in files:
                        files.append(build_file)

    if not files:
        print('No deployable files found.')
        return

    print(f'Deploying {len(files)} file(s):')
    for f in files:
        print(f'  {f}')

    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(REMOTE_HOST, REMOTE_PORT, REMOTE_USER, password)

    sftp = ssh.open_sftp()
    for filepath in files:
        remote_file = f'{REMOTE_PATH}/{filepath}'
        # Ensure remote directory exists
        remote_dir = '/'.join(remote_file.split('/')[:-1])
        try:
            sftp.stat(remote_dir)
        except FileNotFoundError:
            ssh.exec_command(f'mkdir -p {remote_dir}')
        sftp.put(filepath, remote_file)
        print(f'  ✓ {filepath}')
    sftp.close()

    _, stdout, stderr = ssh.exec_command(
        f'cd {REMOTE_PATH} && php artisan optimize:clear'
    )
    stdout.read()
    stderr.read()

    ssh.close()
    print('Deploy complete.')


if __name__ == '__main__':
    main()
