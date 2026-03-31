#!/usr/bin/env python3
"""Run optimized media import after deployment"""
import paramiko
import sys
import time

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'
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
        
        # Check git commit
        current_commit = run_cmd(client,
                f'cd {BASE_PATH} && git rev-parse --short HEAD',
                'Current Deployed Commit')
        
        print(f"\nExpected: d1c79e8")
        print(f"Actual:   {current_commit}")
        
        if current_commit != 'd1c79e8':
            print("\n⚠️ Deployment not complete yet. Wait for GitHub Actions to finish.")
            client.close()
            return 1
        
        print("\n✓ Deployment complete\n")
        
        # Start optimized import
        run_cmd(client,
                f'cd {BASE_PATH} && tmux new-session -d -s media_import_optimized '
                f'"php artisan media:associate-legacy --no-interaction 2>&1 | tee /tmp/media_import_optimized.log"',
                'Starting Optimized Media Import')
        
        print("\n✓ Import started (will reuse 12,175 existing AVIF files)")
        print("\nEstimated time: 5-10 minutes (just creating DB records)")
        
        # Monitor for a few checks
        print("\nMonitoring progress...\n")
        for i in range(5):
            time.sleep(20)
            
            count = run_cmd(client,
                    f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB '
                    f'-e "SELECT COUNT(*) FROM product_media;" | tail -1')
            
            if count.isdigit():
                print(f"[{time.strftime('%H:%M:%S')}] Media records: {count}")
        
        print("\n" + "="*70)
        print("Import running in background")
        print("Check status: tail -f /tmp/media_import_optimized.log")
        print("="*70)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
