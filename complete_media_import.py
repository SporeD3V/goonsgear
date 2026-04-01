#!/usr/bin/env python3
"""Complete the media import"""
import paramiko
import sys
import time

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
        
        # Check current status
        current = run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) FROM product_media;" | tail -1')
        
        print(f"Current media records: {current}")
        print(f"Target: ~2,968 (from WordPress)")
        print(f"Progress: {current}/2,968 ({int(current)/2968*100:.1f}%)\n")
        
        # Check for running import
        ps = run_cmd(client, 'ps aux | grep "artisan media:associate-legacy" | grep -v grep')
        
        if ps:
            print("⚠️ Import already running")
            print("\nMonitor: tail -f /tmp/media_import.log")
        else:
            # Start import
            run_cmd(client,
                    f'cd {BASE_PATH} && nohup php artisan media:associate-legacy '
                    f'--no-interaction > /tmp/media_import_complete.log 2>&1 & echo $!',
                    'Starting Media Import')
            
            print("\n✓ Import started")
            print("This will:")
            print("  - Reuse existing 12,175 AVIF files (no re-conversion)")
            print("  - Create DB records for remaining ~2,000 images")
            print("  - Estimated time: 5-10 minutes\n")
            
            print("Monitor progress:")
            print("  tail -f /tmp/media_import_complete.log")
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
