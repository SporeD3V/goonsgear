#!/usr/bin/env python3
"""Wait for import completion - check every minute until done"""
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
    out = stdout.read().decode().strip()
    return out

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        
        print("Waiting for import completion...")
        print("Current: 1,196 variant images (77.6%)")
        print("Target: ~1,541 variant images")
        print("="*70)
        
        last_count = 1196
        checks = 0
        max_checks = 60  # Max 60 minutes
        
        while checks < max_checks:
            # Check if still running
            processes = run_cmd(client, 'ps aux | grep "artisan media:associate" | grep -v grep | wc -l')
            processes_count = int(processes.strip())
            
            # Get current count
            count_result = run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) FROM product_media WHERE product_variant_id IS NOT NULL;" | tail -1')
            current_count = int(count_result.strip()) if count_result.strip().isdigit() else 0
            
            if processes_count == 0:
                print(f"\n{'='*70}")
                print(f"✓ ALL IMPORTS COMPLETED!")
                print(f"{'='*70}")
                print(f"Final count: {current_count} variant images")
                print(f"Total added: +{current_count - 685} images")
                break
            
            added = current_count - last_count
            total_added = current_count - 685
            progress_pct = (current_count / 1541) * 100
            remaining = 1541 - current_count
            
            print(f"[{time.strftime('%H:%M:%S')}] {processes_count} processes | "
                  f"Count: {current_count} | "
                  f"+{added} | "
                  f"Total: +{total_added} | "
                  f"{progress_pct:.1f}% | "
                  f"~{remaining} remaining")
            
            last_count = current_count
            checks += 1
            
            time.sleep(60)  # Check every minute
        
        # Final detailed stats
        print("\n" + "="*70)
        print("FINAL RESULTS")
        print("="*70)
        
        stats = run_cmd(client,
            f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
            'SELECT '
            '  COUNT(DISTINCT product_id) as products_with_variant_media, '
            '  COUNT(DISTINCT product_variant_id) as variants_with_media, '
            '  COUNT(*) as total_variant_media_records '
            'FROM product_media '
            'WHERE product_variant_id IS NOT NULL;"')
        print(stats)
        
        # Total media count
        total = run_cmd(client,
            f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
            'SELECT '
            '  SUM(CASE WHEN product_variant_id IS NULL THEN 1 ELSE 0 END) as product_level, '
            '  SUM(CASE WHEN product_variant_id IS NOT NULL THEN 1 ELSE 0 END) as variant_specific, '
            '  COUNT(*) as total_media '
            'FROM product_media;"')
        print("\n" + total)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
