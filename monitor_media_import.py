#!/usr/bin/env python3
"""Monitor media import progress"""
import paramiko
import sys
import time

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

def run_cmd(client, cmd):
    stdin, stdout, stderr = client.exec_command(cmd, timeout=30)
    return stdout.read().decode().strip()

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        
        print("Monitoring media import progress...")
        print("Target: ~2,500 media records (products + variants)")
        print("="*70 + "\n")
        
        last_count = 57
        checks = 0
        
        while checks < 120:  # Max 2 hours
            # Check if process running
            ps = run_cmd(client, 'ps aux | grep "artisan media:associate-legacy" | grep -v grep')
            
            if not ps or 'php artisan' not in ps:
                print("\n✓ Import process completed!")
                break
            
            # Get current count
            count = run_cmd(client,
                f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB '
                f'-e "SELECT COUNT(*) FROM product_media;" | tail -1')
            
            if count.isdigit():
                current = int(count)
                added = current - last_count
                progress = (current / 2500) * 100
                
                print(f"[{time.strftime('%H:%M:%S')}] {current:,} records | "
                      f"+{added} | {progress:.1f}% complete")
                
                last_count = current
            
            checks += 1
            time.sleep(60)  # Check every minute
        
        # Final results
        print("\n" + "="*70)
        result = run_cmd(client,
            f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
            'SELECT '
            '  COUNT(*) as total, '
            '  SUM(CASE WHEN product_variant_id IS NULL THEN 1 ELSE 0 END) as product_level, '
            '  SUM(CASE WHEN product_variant_id IS NOT NULL THEN 1 ELSE 0 END) as variant_specific '
            'FROM product_media;"')
        print(result)
        print("="*70)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
