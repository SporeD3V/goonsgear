#!/usr/bin/env python3
"""Batch 1: Import variant images for first 100 products"""
import paramiko
import sys
import time

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

def run_cmd(client, cmd, label=None, timeout=300):
    if label:
        print(f"\n{'='*70}\n{label}\n{'='*70}")
    print(f"$ {cmd}\n")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=timeout)
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    if out:
        print(out)
    if err and 'deprecated' not in err.lower() and 'warning' not in err.lower():
        print(f"STDERR: {err}")
    return out

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        print(f"✓ Connected\n")
        
        # Count BEFORE
        before = run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) as variant_media_before '
                'FROM product_media '
                'WHERE product_variant_id IS NOT NULL;'
                '"',
                'BEFORE: Variant-Specific Media Count')
        
        print("\n" + "="*70)
        print("Starting Batch 1: Import first 100 products")
        print("="*70)
        
        start_time = time.time()
        
        # Run import with limit=100
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan media:associate-legacy --limit=100',
                'BATCH 1: Import 100 products',
                timeout=600)
        
        elapsed = time.time() - start_time
        
        # Count AFTER
        after = run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) as variant_media_after '
                'FROM product_media '
                'WHERE product_variant_id IS NOT NULL;'
                '"',
                'AFTER: Variant-Specific Media Count')
        
        # Calculate difference
        print("\n" + "="*70)
        print("BATCH 1 RESULTS")
        print("="*70)
        print(f"Time elapsed: {elapsed:.1f} seconds ({elapsed/60:.1f} minutes)")
        
        # Get summary stats
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  COUNT(DISTINCT product_id) as products_with_variant_media, '
                '  COUNT(DISTINCT product_variant_id) as variants_with_media, '
                '  COUNT(*) as total_variant_media '
                'FROM product_media '
                'WHERE product_variant_id IS NOT NULL;'
                '"',
                'Summary: Products and Variants with Media')
        
        print("\n" + "="*70)
        print("✓ Batch 1 completed successfully")
        print("="*70)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
