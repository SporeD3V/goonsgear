#!/usr/bin/env python3
"""Stop all running imports and check final state"""
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
        
        # Kill all running import processes
        run_cmd(client,
                'pkill -f "artisan media:associate-legacy"',
                'Stopping All Import Processes')
        
        print("\nWaiting 5 seconds for processes to terminate...")
        time.sleep(5)
        
        # Verify no processes running
        run_cmd(client,
                'ps aux | grep "artisan media:associate" | grep -v grep',
                'Verify No Processes Running')
        
        # Get final counts
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  SUM(CASE WHEN product_variant_id IS NULL THEN 1 ELSE 0 END) as product_level, '
                '  SUM(CASE WHEN product_variant_id IS NOT NULL THEN 1 ELSE 0 END) as variant_specific, '
                '  COUNT(*) as total_media '
                'FROM product_media;"',
                'Final Media Count')
        
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  COUNT(DISTINCT product_id) as products_with_variant_media, '
                '  COUNT(DISTINCT product_variant_id) as unique_variants_with_media, '
                '  COUNT(*) as total_variant_media_records '
                'FROM product_media '
                'WHERE product_variant_id IS NOT NULL;"',
                'Variant Media Details')
        
        # Compare with WP
        print("\n" + "="*70)
        print("COMPARISON WITH WORDPRESS")
        print("="*70)
        print("WP variations with images: 1,541")
        
        result = run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(DISTINCT product_variant_id) FROM product_media WHERE product_variant_id IS NOT NULL;" | tail -1')
        
        if result:
            laravel_count = int(result.strip())
            print(f"Laravel variants with media: {laravel_count}")
            print(f"Difference: {1541 - laravel_count} variants {'missing' if laravel_count < 1541 else 'extra'}")
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
