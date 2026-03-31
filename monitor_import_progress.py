#!/usr/bin/env python3
"""Monitor import progress - check every 2 minutes"""
import paramiko
import sys
import time

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

def run_cmd(client, cmd):
    stdin, stdout, stderr = client.exec_command(cmd, timeout=30)
    out = stdout.read().decode().strip()
    return out

def check_progress(client):
    # Check if still running
    processes = run_cmd(client, 'ps aux | grep "artisan media:associate" | grep -v grep | wc -l')
    
    # Get current count
    count = run_cmd(client,
        f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
        'SELECT COUNT(*) FROM product_media WHERE product_variant_id IS NOT NULL;" | tail -1')
    
    return int(processes.strip()), int(count.strip()) if count.strip().isdigit() else 0

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        
        print("Monitoring import progress...")
        print("Starting count: 685 variant images")
        print("Target: ~1,541 variant images")
        print("="*70)
        
        last_count = 685
        checks = 0
        max_checks = 30  # Max 60 minutes (30 checks × 2 minutes)
        
        while checks < max_checks:
            processes, current_count = check_progress(client)
            
            if processes == 0:
                print(f"\n✓ Import completed!")
                print(f"Final count: {current_count} variant images")
                break
            
            added = current_count - last_count
            total_added = current_count - 685
            progress_pct = (current_count / 1541) * 100
            
            print(f"[{time.strftime('%H:%M:%S')}] Running ({processes} processes) | "
                  f"Current: {current_count} | "
                  f"Added this check: +{added} | "
                  f"Total added: +{total_added} | "
                  f"Progress: {progress_pct:.1f}%")
            
            last_count = current_count
            checks += 1
            
            if checks < max_checks:
                time.sleep(120)  # Wait 2 minutes
        
        if processes > 0:
            print(f"\n⚠️ Import still running after {checks * 2} minutes")
            print(f"Current: {current_count} variant images")
        
        # Final stats
        print("\n" + "="*70)
        print("FINAL STATS")
        print("="*70)
        stats = run_cmd(client,
            f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
            'SELECT '
            '  COUNT(DISTINCT product_id) as products, '
            '  COUNT(DISTINCT product_variant_id) as variants, '
            '  COUNT(*) as total_records '
            'FROM product_media '
            'WHERE product_variant_id IS NOT NULL;"')
        print(stats)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
