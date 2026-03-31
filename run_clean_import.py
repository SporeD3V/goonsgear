#!/usr/bin/env python3
"""Run clean import with fixed code"""
import paramiko
import sys
import time

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

def run_cmd(client, cmd, label=None, timeout=1800):
    if label:
        print(f"\n{'='*70}\n{label}\n{'='*70}")
    print(f"$ {cmd}\n")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=timeout)
    
    # Print output as it comes
    start_time = time.time()
    while True:
        if stdout.channel.recv_ready():
            chunk = stdout.read(1024).decode()
            if chunk:
                print(chunk, end='', flush=True)
        
        if stdout.channel.exit_status_ready():
            break
            
        # Timeout check
        if time.time() - start_time > timeout:
            print("\n⚠️ Command timeout")
            break
            
        time.sleep(0.5)
    
    # Get any remaining output
    remaining = stdout.read().decode()
    if remaining:
        print(remaining)
    
    return stdout.channel.recv_exit_status()

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        print(f"✓ Connected\n")
        
        print("="*70)
        print("STARTING CLEAN IMPORT WITH FIXED CODE")
        print("="*70)
        print("\nFixes applied:")
        print("  ✓ Removed name matching (prevents product merging)")
        print("  ✓ Added category pivot sync (multi-category support)")
        print("  ✓ Frontend variant filter hidden on simple products")
        print("\n" + "="*70)
        
        # Run import
        exit_code = run_cmd(client,
                f'cd {BASE_PATH} && php artisan import:legacy-data --no-interaction',
                'Step 1: Import Products, Variants, Orders',
                timeout=1800)
        
        if exit_code == 0:
            print("\n✓ Import completed successfully!")
        else:
            print(f"\n⚠️ Import exited with code {exit_code}")
        
        # Quick stats
        stdin, stdout, stderr = client.exec_command(
            f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
            'SELECT '
            '  (SELECT COUNT(*) FROM products) as products, '
            '  (SELECT COUNT(*) FROM product_variants) as variants, '
            '  (SELECT COUNT(*) FROM category_product) as category_pivot;'
            '"',
            timeout=30)
        print("\n" + "="*70)
        print("IMPORT RESULTS")
        print("="*70)
        print(stdout.read().decode())
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
