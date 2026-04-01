#!/usr/bin/env python3
"""Start media import and check status"""
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
    stdin, stdout, stderr = client.exec_command(cmd, timeout=30)
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
        
        # Check if already running
        existing = run_cmd(client,
                'ps aux | grep "artisan media:associate-legacy" | grep -v grep',
                'Check for existing media import processes')
        
        if existing:
            print("\n⚠️ Media import already running")
        else:
            # Start in background with tmux
            run_cmd(client,
                    f'cd {BASE_PATH} && tmux new-session -d -s media_import '
                    f'"php artisan media:associate-legacy --no-interaction 2>&1 | tee /tmp/media_import.log"',
                    'Starting media import in tmux session')
            print("\n✓ Media import started in background (tmux session: media_import)")
        
        # Current status
        run_cmd(client,
                f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  (SELECT COUNT(*) FROM products) as products, '
                '  (SELECT COUNT(*) FROM product_media) as current_media;'
                '"',
                'Current Status')
        
        print("\n" + "="*70)
        print("MEDIA IMPORT RUNNING")
        print("="*70)
        print("\nMonitor progress:")
        print("  ssh spored3v@91.98.230.33 -p 1221")
        print("  tmux attach -t media_import")
        print("\nOr check log:")
        print("  tail -f /tmp/media_import.log")
        print("\nEstimated time: 20-30 minutes")
        print("="*70)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
