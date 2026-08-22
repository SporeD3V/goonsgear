"""Fallback staging deploy - mirrors .github/workflows/deploy-stage.yml.

TEMPORARY. GitHub Actions is the sanctioned deploy path; this script exists
only because the GitHub account is locked for billing and Actions cannot run.
Delete it once Actions works again.

It runs the same gates as the workflow and refuses to deploy if any fail:
Composer validation, Pint, Larastan, the release-gate tests, and a frontend
build. It then ships a production-only tree, syncs it server-side with rsync
(same excludes, so .env / storage / bootstrap-cache are never touched), runs
migrations and cache rebuild, and smoke-tests the site.

Usage:
    python scripts/deploy_staging.py --yes

The SSH password is read from GOONSGEAR_SSH_PASSWORD or, failing that,
~/.secrets/goonsgear-staging-ssh.txt. It is never logged.
"""

from __future__ import annotations

import argparse
import json
import os
import shutil
import subprocess
import sys
import tarfile
import tempfile
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

import paramiko

PROJECT_ROOT = Path(__file__).resolve().parent.parent
IS_WINDOWS = sys.platform == "win32"

# Connection details are deliberately NOT hardcoded: this repo is public, and
# commit 05c98ae removed exactly this pattern from config/services.php. They
# come from scripts/deploy.local.json (gitignored) or the matching env vars,
# which take precedence. See scripts/deploy.local.example.json.
CONFIG_FILE = PROJECT_ROOT / "scripts" / "deploy.local.json"


def _load_local_config() -> dict[str, str]:
    if not CONFIG_FILE.exists():
        return {}

    try:
        data = json.loads(CONFIG_FILE.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        raise SystemExit(f"{CONFIG_FILE.name} is not valid JSON: {exc}") from None

    if not isinstance(data, dict):
        raise SystemExit(f"{CONFIG_FILE.name} must contain a JSON object")

    return {str(key): str(value) for key, value in data.items() if value is not None}


_LOCAL_CONFIG = _load_local_config()


def _setting(env_var: str, key: str, default: str = "") -> str:
    return os.environ.get(env_var) or _LOCAL_CONFIG.get(key) or default


HOST = _setting("GOONSGEAR_SSH_HOST", "host")
PORT = _setting("GOONSGEAR_SSH_PORT", "port")
USER = _setting("GOONSGEAR_SSH_USER", "user")
REMOTE_PATH = _setting("GOONSGEAR_REMOTE_PATH", "remote_path")
# The staging URL is public information, so a default is harmless here.
STAGING_URL = _setting("GOONSGEAR_STAGING_URL", "staging_url", "https://goonsgear.macaw.studio")

RELEASE_GATE_TESTS = [
    "tests/Feature/ShopBrowseTest.php",
    "tests/Feature/ShopProductPresentationTest.php",
    "tests/Feature/CartFlowTest.php",
    "tests/Feature/CheckoutFlowTest.php",
    "tests/Feature/LegacyImportCommandTest.php",
    "tests/Feature/ShopPaginationTest.php",
]

# Tracked paths never shipped, matching the workflow's rsync excludes. Kept
# deliberately narrow: anything excluded here but shipped by the workflow would
# be deleted now and restored on the next Actions run.
PAYLOAD_EXCLUDE_PREFIXES = (
    "tests/",
    ".github/",
)
PAYLOAD_EXCLUDE_FILES = {".env"}


class DeployError(RuntimeError):
    """Raised when a phase fails and the deploy must stop."""


def log(message: str) -> None:
    print(f"  {message}", flush=True)


def phase(title: str) -> None:
    print(f"\n=== {title} ===", flush=True)


def run(command: list[str], *, capture: bool = False) -> str:
    """Run a local command, raising DeployError on a non-zero exit."""
    result = subprocess.run(
        command,
        cwd=PROJECT_ROOT,
        text=True,
        shell=False,
        stdout=subprocess.PIPE if capture else None,
        stderr=subprocess.STDOUT if capture else None,
    )

    if result.returncode != 0:
        if capture and result.stdout:
            print(result.stdout, flush=True)
        raise DeployError(f"command failed ({result.returncode}): {' '.join(command)}")

    return result.stdout or ""


def tool(name: str) -> str:
    """Resolve a vendor binary, preferring the .bat shim on Windows."""
    candidate = PROJECT_ROOT / "vendor" / "bin" / (f"{name}.bat" if IS_WINDOWS else name)
    return str(candidate)


def npm() -> str:
    return "npm.cmd" if IS_WINDOWS else "npm"


def composer() -> list[str]:
    return ["composer.bat"] if IS_WINDOWS else ["composer"]


def read_password() -> str:
    password = os.environ.get("GOONSGEAR_SSH_PASSWORD", "")

    if not password:
        secret_file = Path.home() / ".secrets" / "goonsgear-staging-ssh.txt"
        if not secret_file.exists():
            raise DeployError(
                "No SSH password. Set GOONSGEAR_SSH_PASSWORD or create "
                f"{secret_file}"
            )
        password = secret_file.read_text(encoding="utf-8").strip()

    if not password:
        raise DeployError("SSH password is empty.")

    return password


def git(*args: str) -> str:
    return run(["git", *args], capture=True).strip()


def preflight(allow_unpushed: bool) -> str:
    """Verify git state, so the deployed code always matches a pushed commit."""
    phase("Preflight")

    branch = git("rev-parse", "--abbrev-ref", "HEAD")
    if branch != "main":
        raise DeployError(f"on branch '{branch}'; staging deploys come from main")

    commit = git("rev-parse", "HEAD")

    # Application files are taken from the commit, not the working tree, so
    # uncommitted work never reaches the server. Say so rather than fail.
    dirty = git("status", "--porcelain", "--untracked-files=no")
    if dirty:
        log("uncommitted changes below will NOT be deployed:")
        for line in dirty.splitlines():
            log(f"  {line.strip()}")

    if allow_unpushed:
        log(f"branch main @ {commit[:8]} (origin/main check skipped)")

        return commit

    run(["git", "fetch", "origin", "main"], capture=True)

    if git("rev-parse", "origin/main") != commit:
        raise DeployError(
            "HEAD is not pushed to origin/main. Push first so git stays the "
            "source of truth, or pass --allow-unpushed."
        )

    log(f"branch main @ {commit[:8]} (verified on origin/main)")

    return commit


def gate_composer_validate() -> None:
    phase("Gate: Composer manifest")
    run([*composer(), "validate", "--strict", "--no-check-publish"], capture=True)
    log("composer.json and composer.lock are in sync")


def gate_pint() -> None:
    """Pint, ignoring gitignored trees the CI checkout never contains."""
    phase("Gate: Code style (Pint)")

    result = subprocess.run(
        [tool("pint"), "--test", "--format", "json"],
        cwd=PROJECT_ROOT,
        text=True,
        capture_output=True,
    )
    raw = result.stdout or ""
    start = raw.find("{")

    if start == -1:
        if result.returncode == 0:
            log("no style violations")
            return
        print(raw or result.stderr, flush=True)
        raise DeployError("pint failed and produced no parsable report")

    try:
        report = json.loads(raw[start:])
    except json.JSONDecodeError as exc:
        print(raw, flush=True)
        raise DeployError(f"could not parse the pint report: {exc}") from None

    entries = report.get("files", [])

    # Pint has emitted entries without a path field; one of those used to kill
    # the deploy with a bare KeyError. Surface them and keep going instead.
    malformed = [item for item in entries if not isinstance(item, dict) or "path" not in item]
    if malformed:
        log(f"warning: {len(malformed)} pint entry/entries had no path field")
        print(json.dumps(malformed, indent=2)[:2000], flush=True)

    offenders = [
        str(item["path"])
        for item in entries
        if isinstance(item, dict)
        and "path" in item
        and not str(item["path"]).replace("\\", "/").startswith("wp-plugin/")
    ]

    if offenders:
        for path in offenders:
            log(f"style violation: {path}")
        raise DeployError(f"{len(offenders)} file(s) fail Pint; run vendor/bin/pint")

    log("no style violations in deployable code")


def gate_phpstan() -> None:
    phase("Gate: Static analysis (Larastan)")
    run([tool("phpstan"), "analyse", "--no-progress", "--memory-limit=512M"], capture=True)
    log("no static analysis errors")


def gate_tests() -> None:
    phase("Gate: Release-gate tests")
    output = run(["php", "artisan", "test", "--compact", *RELEASE_GATE_TESTS], capture=True)
    tail = [line for line in output.splitlines() if "Tests:" in line]
    log(tail[-1].strip() if tail else "tests passed")


def build_assets() -> None:
    phase("Build frontend assets")
    run([npm(), "run", "build"], capture=True)
    log("vite build complete")


def install_production_dependencies() -> None:
    phase("Install production-only Composer dependencies")
    run(
        [
            *composer(),
            "install",
            "--no-dev",
            "--prefer-dist",
            "--no-interaction",
            "--no-progress",
            "--optimize-autoloader",
        ],
        capture=True,
    )
    log("vendor/ rebuilt without dev dependencies")


def restore_dev_dependencies() -> None:
    phase("Restore local dev dependencies")
    try:
        run([*composer(), "install", "--no-interaction", "--no-progress"], capture=True)
        log("dev dependencies restored")
    except DeployError as error:
        log(f"WARNING: could not restore dev dependencies: {error}")
        log("run 'composer install' manually to get pint/phpstan/phpunit back")


def write_release_marker(staging_dir: Path, commit: str) -> None:
    marker = {
        "source": "local-fallback",
        "commit": commit,
        "ref": "main",
        "run_id": "",
        "run_number": "",
        "released_at": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
    }
    path = staging_dir / "public" / "release.json"
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(marker, indent=2) + "\n", encoding="utf-8")


def extract_commit(staging_dir: Path, work_dir: Path, commit: str) -> None:
    """Lay down application files from the commit, not the working tree."""
    archive = work_dir / "commit.tar"

    with archive.open("wb") as handle:
        result = subprocess.run(
            ["git", "archive", "--format=tar", commit],
            cwd=PROJECT_ROOT,
            stdout=handle,
            stderr=subprocess.PIPE,
            text=False,
        )

    if result.returncode != 0:
        raise DeployError(f"git archive failed: {result.stderr.decode('utf-8', 'replace')}")

    with tarfile.open(archive) as tar:
        tar.extractall(staging_dir, filter="data")

    archive.unlink(missing_ok=True)

    for prefix in PAYLOAD_EXCLUDE_PREFIXES:
        shutil.rmtree(staging_dir / prefix.rstrip("/"), ignore_errors=True)

    for name in PAYLOAD_EXCLUDE_FILES:
        (staging_dir / name).unlink(missing_ok=True)


def copy_build_outputs(staging_dir: Path) -> None:
    """Add generated trees, which are not in git."""
    for generated in ("vendor", "public/build"):
        source = PROJECT_ROOT / generated
        if not source.is_dir():
            raise DeployError(f"missing {generated}/ - build step did not run")
        shutil.copytree(source, staging_dir / generated, dirs_exist_ok=True)


def strip_vendor(staging_dir: Path) -> None:
    """Drop vendor tests and docs, as the workflow does."""
    vendor = staging_dir / "vendor"
    if not vendor.is_dir():
        return

    for directory in list(vendor.rglob("*")):
        if directory.is_dir() and directory.name in {"tests", "Tests"}:
            shutil.rmtree(directory, ignore_errors=True)

    for pattern in ("*.md", "*.txt", "LICENSE", "CHANGELOG*"):
        for junk in vendor.rglob(pattern):
            if junk.is_file():
                junk.unlink(missing_ok=True)


def assemble_payload(work_dir: Path, commit: str) -> Path:
    """Build the exact tree to ship and verify it before anything leaves."""
    staging_dir = work_dir / "payload"
    staging_dir.mkdir()

    extract_commit(staging_dir, work_dir, commit)
    copy_build_outputs(staging_dir)
    write_release_marker(staging_dir, commit)
    strip_vendor(staging_dir)

    for required in ("artisan", "vendor/autoload.php", "public/release.json", "public/index.php"):
        if not (staging_dir / required).is_file():
            raise DeployError(f"payload is missing {required}")

    for forbidden in (".env", "tests", ".github"):
        if (staging_dir / forbidden).exists():
            raise DeployError(f"payload must not contain {forbidden}")

    if not any(staging_dir.rglob("*")):
        raise DeployError("payload is empty")

    return staging_dir


def build_payload(work_dir: Path, commit: str) -> Path:
    phase("Build deploy payload")

    staging_dir = assemble_payload(work_dir, commit)
    file_count = sum(1 for item in staging_dir.rglob("*") if item.is_file())

    archive = work_dir / "payload.tar.gz"
    with tarfile.open(archive, "w:gz") as tar:
        tar.add(staging_dir, arcname=".")

    size_mb = archive.stat().st_size / 1_048_576
    log(f"{file_count} files, {size_mb:.1f} MB compressed")
    return archive


def require_connection_config() -> None:
    """Fail early, and clearly, when the connection details are not configured."""
    missing = [
        name
        for name, value in (
            ("host", HOST),
            ("port", PORT),
            ("user", USER),
            ("remote_path", REMOTE_PATH),
        )
        if not value
    ]

    if missing:
        raise DeployError(
            "Missing deploy connection settings: "
            + ", ".join(missing)
            + f". Copy scripts/deploy.local.example.json to {CONFIG_FILE.name} and fill it in "
            "(it is gitignored), or set the matching GOONSGEAR_* environment variables."
        )

    if not PORT.isdigit():
        raise DeployError(f"SSH port must be numeric, got {PORT!r}")


def ssh_connect(password: str) -> paramiko.SSHClient:
    require_connection_config()

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, int(PORT), USER, password, timeout=30)
    return client


def remote_run(client: paramiko.SSHClient, script: str, *, timeout: int = 600) -> str:
    """Run a bash script on the server, echoing output and failing loudly."""
    _, stdout, stderr = client.exec_command(f"bash -s <<'DEPLOY_EOF'\n{script}\nDEPLOY_EOF", timeout=timeout)
    out = stdout.read().decode("utf-8", "replace")
    err = stderr.read().decode("utf-8", "replace")
    code = stdout.channel.recv_exit_status()

    for line in out.splitlines():
        log(line)

    if code != 0:
        if err.strip():
            for line in err.splitlines():
                log(f"stderr: {line}")
        raise DeployError(f"remote command failed with exit code {code}")

    return out


def upload(client: paramiko.SSHClient, archive: Path, remote_archive: str) -> None:
    phase("Upload payload")

    sftp = client.open_sftp()
    try:
        sftp.put(str(archive), remote_archive)
    finally:
        sftp.close()

    log(f"uploaded to {remote_archive}")


def sync_remote(client: paramiko.SSHClient, remote_archive: str) -> None:
    """Extract server-side and rsync into place with the workflow's excludes."""
    phase("Sync to staging")

    script = f"""
set -eu
REMOTE_PATH="{REMOTE_PATH}"
ARCHIVE="{remote_archive}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE" "$ARCHIVE"' EXIT

test -d "$REMOTE_PATH"
touch "$REMOTE_PATH/.deploy-write-test"
rm -f "$REMOTE_PATH/.deploy-write-test"

mkdir -p "$REMOTE_PATH/bootstrap/cache" "$REMOTE_PATH/storage"
find "$REMOTE_PATH/bootstrap/cache" -maxdepth 1 -type f -name '*.php' -delete || true

tar xzf "$ARCHIVE" -C "$STAGE"
test -f "$STAGE/artisan"

rsync -rlt --delete --force --omit-dir-times --stats \
  --exclude='.env' \
  --exclude='storage/' \
  --exclude='bootstrap/cache/' \
  --exclude='public/storage' \
  "$STAGE/" "$REMOTE_PATH/" | tail -n 12
"""
    remote_run(client, script, timeout=900)


def post_deploy(client: paramiko.SSHClient) -> None:
    phase("Post-deploy setup")

    script = f"""
set -eu
cd "{REMOTE_PATH}"

find storage bootstrap/cache -type d -exec chmod 770 {{}} + 2>/dev/null || true
find storage bootstrap/cache -type f -exec chmod 660 {{}} + 2>/dev/null || true

test -f artisan || {{ echo 'FAIL: artisan missing after deploy'; exit 1; }}
php artisan --version

test -f public/release.json || {{ echo 'FAIL: release.json missing'; exit 1; }}

ABOUT=$(php artisan about --only=environment 2>/dev/null || php artisan about 2>/dev/null)
if echo "$ABOUT" | grep -Eq 'Debug Mode[ .]*ENABLED'; then
  echo 'FAIL: APP_DEBUG must be disabled on staging.'
  exit 1
fi
echo 'debug mode: disabled'

php artisan migrate --force --no-interaction
REMAINING=$(php artisan migrate:status --no-interaction 2>&1 | grep -c 'Pending' || true)
if [ "$REMAINING" -gt 0 ]; then
  echo "FAIL: ${{REMAINING}} migration(s) still pending"
  exit 1
fi
echo 'migrations: up to date'

php artisan optimize:clear --no-interaction >/dev/null
php artisan storage:link --force --no-interaction >/dev/null 2>&1 || true
php artisan optimize --no-interaction >/dev/null
echo 'caches rebuilt'
"""
    remote_run(client, script, timeout=900)


def fetch(url: str) -> tuple[int, str]:
    request = urllib.request.Request(url, headers={"User-Agent": "goonsgear-deploy"})
    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            return response.status, response.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as error:
        return error.code, ""


def smoke_test(commit: str) -> None:
    phase("Smoke test")

    for path in ("/", "/catalog", "/cart"):
        status, _ = fetch(f"{STAGING_URL}{path}")
        log(f"{path} -> {status}")
        if status != 200:
            raise DeployError(f"{path} returned {status}")

    status, body = fetch(f"{STAGING_URL}/api/shop/search?q=sh")
    if status != 200 or '"results"' not in body:
        raise DeployError(f"search endpoint failed (status {status})")
    log("/api/shop/search -> 200 with results")

    status, body = fetch(f"{STAGING_URL}/release.json")
    if status != 200:
        raise DeployError(f"release.json returned {status}")

    deployed = json.loads(body).get("commit", "")
    if deployed != commit:
        raise DeployError(f"release marker is {deployed[:8]}, expected {commit[:8]}")
    log(f"release.json commit matches {commit[:8]}")


def dry_run(allow_unpushed: bool) -> int:
    """Validate git state and payload assembly without touching the server."""
    commit = preflight(allow_unpushed)

    phase("Payload preview (nothing is sent)")
    with tempfile.TemporaryDirectory() as raw_work_dir:
        staging_dir = assemble_payload(Path(raw_work_dir), commit)

        files = [item for item in staging_dir.rglob("*") if item.is_file()]
        app_files = [
            item
            for item in files
            if not item.relative_to(staging_dir).as_posix().startswith(("vendor/", "public/build/"))
        ]

        log(f"{len(files)} files total, {len(app_files)} application files")
        log("ok   artisan, public/index.php, vendor/autoload.php present")
        log("ok   .env, tests/ and .github/ excluded")

        marker = json.loads((staging_dir / "public" / "release.json").read_text())
        log(f"ok   release marker -> {marker['commit'][:8]} ({marker['source']})")

    print(f"\n=== Dry run OK - {commit[:8]} is ready to deploy ===")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--yes", action="store_true", help="skip the confirmation prompt")
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="check git state and payload assembly, then stop without connecting",
    )
    parser.add_argument(
        "--allow-unpushed",
        action="store_true",
        help="deploy a commit that is not on origin/main (use only while pushing is broken)",
    )
    args = parser.parse_args()

    if args.dry_run:
        try:
            return dry_run(args.allow_unpushed)
        except DeployError as error:
            print(f"\nDRY RUN FAILED: {error}", file=sys.stderr, flush=True)
            return 1

    # Fail before the gates rather than after five minutes of work.
    try:
        require_connection_config()
    except DeployError as error:
        print(f"\nDEPLOY FAILED: {error}", file=sys.stderr, flush=True)
        return 1

    print("=" * 72)
    print("FALLBACK STAGING DEPLOY - GitHub Actions is the sanctioned path.")
    print("Use this only while Actions is unavailable. Delete once it works.")
    print("=" * 72)

    if not args.yes:
        if input("Proceed? [y/N] ").strip().lower() not in {"y", "yes"}:
            print("Aborted.")
            return 1

    dependencies_switched = False

    try:
        password = read_password()
        commit = preflight(args.allow_unpushed)

        gate_composer_validate()
        gate_pint()
        gate_phpstan()
        gate_tests()
        build_assets()

        install_production_dependencies()
        dependencies_switched = True

        with tempfile.TemporaryDirectory() as raw_work_dir:
            archive = build_payload(Path(raw_work_dir), commit)
            remote_archive = f"/tmp/goonsgear-deploy-{commit[:8]}.tar.gz"

            client = ssh_connect(password)
            try:
                upload(client, archive, remote_archive)
                sync_remote(client, remote_archive)
                post_deploy(client)
            finally:
                client.close()

        smoke_test(commit)

    except (DeployError, paramiko.SSHException, OSError) as error:
        print(f"\nDEPLOY FAILED: {error}", file=sys.stderr, flush=True)
        return 1
    finally:
        if dependencies_switched:
            restore_dev_dependencies()

    print(f"\n=== Deploy OK - {STAGING_URL} is running {commit[:8]} ===")
    return 0


if __name__ == "__main__":
    sys.exit(main())
