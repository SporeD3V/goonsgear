#!/usr/bin/env python3
"""Deploy ShopController fix to staging"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

def run_cmd(client, cmd, label=None):
    if label:
        print(f"\n{'='*70}\n{label}\n{'='*70}")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=60)
    out = stdout.read().decode().strip()
    if out:
        print(out)
    return out

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        print(f"✓ Connected\n")
        
        # Read local fixed file
        with open(r'c:\Projects\goonsgear\app\Http\Controllers\ShopController.php', 'r', encoding='utf-8') as f:
            content = f.read()
        
        # Upload to staging
        sftp = client.open_sftp()
        remote_path = f'{BASE_PATH}/app/Http/Controllers/ShopController.php'
        with sftp.open(remote_path, 'w') as f:
            f.write(content)
        sftp.close()
        
        print(f"✓ Uploaded ShopController.php to {remote_path}\n")
        
        # Clear Laravel caches
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan config:clear',
                'Clear Config Cache')
        
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan route:clear',
                'Clear Route Cache')
        
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan view:clear',
                'Clear View Cache')
        
        # Verify the fix in the file
        run_cmd(client,
                f'grep -n "whereHas.*categories" {BASE_PATH}/app/Http/Controllers/ShopController.php',
                'Verify Fix Applied (should show line with whereHas categories)')
        
        print("\n" + "="*70)
        print("✓ ShopController fix deployed and caches cleared")
        print("="*70)
        print("\nTest URL: https://goonsgear.macaw.studio/shop?category=germanhiphop")
        print("Should now show 82 products\n")
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
