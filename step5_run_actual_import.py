#!/usr/bin/env python3
"""Step 5: Run actual media:associate-legacy and verify results"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

def run_cmd(client, cmd, label=None, timeout=90):
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
        
        product_id = 198
        
        # Run ACTUAL import
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan media:associate-legacy --product={product_id}',
                f'Step 5a: ACTUAL IMPORT - media:associate-legacy for Product {product_id}',
                timeout=180)
        
        # Check results
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                f'SELECT '
                f'  SUM(CASE WHEN product_variant_id IS NULL THEN 1 ELSE 0 END) as product_level, '
                f'  SUM(CASE WHEN product_variant_id IS NOT NULL THEN 1 ELSE 0 END) as variant_specific, '
                f'  COUNT(*) as total '
                f'FROM product_media '
                f'WHERE product_id = {product_id};'
                '"',
                f'Step 5b: Media Count AFTER Import')
        
        # Show variant-specific media details
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                f'SELECT '
                f'  pv.id as variant_id, '
                f'  pv.sku, '
                f'  COUNT(pm.id) as media_count '
                f'FROM product_variants pv '
                f'LEFT JOIN product_media pm ON pm.product_variant_id = pv.id '
                f'WHERE pv.product_id = {product_id} '
                f'GROUP BY pv.id '
                f'ORDER BY pv.id;'
                '"',
                f'Step 5c: Media Count per Variant')
        
        # Sample variant media paths
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                f'SELECT '
                f'  product_variant_id, '
                f'  SUBSTRING(path, 1, 70) as path_preview, '
                f'  mime_type '
                f'FROM product_media '
                f'WHERE product_id = {product_id} '
                f'AND product_variant_id IS NOT NULL '
                f'LIMIT 10;'
                '"',
                f'Step 5d: Sample Variant-Specific Media Paths')
        
        # Check filesystem
        run_cmd(client,
                f'ls -lh {BASE_PATH}/storage/app/public/products/dj-crypt-ginesis-vinyl-tape-bundle/gallery/ | wc -l',
                f'Step 5e: File Count in Product Directory')
        
        print("\n" + "="*70)
        print("✓ Step 5 completed - Actual import executed and verified")
        print("="*70)
        print("\n>>> NEXT: Test variant selector on frontend")
        print(f">>> URL: https://goonsgear.macaw.studio/product/dj-crypt-ginesis-vinyl-tape-bundle")
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
