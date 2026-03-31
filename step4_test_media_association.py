#!/usr/bin/env python3
"""Step 4: Test media:associate-legacy on a single product"""
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
        
        # Find a product with WP variations that have images but Laravel variants missing images
        # Let's find a product with variants but no variant-specific media
        
        result = run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  p.id, '
                '  p.name, '
                '  COUNT(DISTINCT pv.id) as variant_count, '
                '  COUNT(DISTINCT pm.id) as total_media, '
                '  SUM(CASE WHEN pm.product_variant_id IS NOT NULL THEN 1 ELSE 0 END) as variant_media '
                'FROM products p '
                'JOIN product_variants pv ON pv.product_id = p.id '
                'LEFT JOIN product_media pm ON pm.product_id = p.id '
                'GROUP BY p.id '
                'HAVING variant_count > 2 AND total_media > 0 AND variant_media = 0 '
                'LIMIT 5;'
                '"',
                'Step 4a: Find Products with Variants but No Variant-Specific Media')
        
        # Pick first product ID from result
        lines = result.strip().split('\n')
        if len(lines) > 1:
            first_product_line = lines[1]
            product_id = first_product_line.split()[0]
            
            print(f"\n>>> Testing with Product ID: {product_id}")
            
            # Get product details
            run_cmd(client,
                    f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                    f'SELECT id, name, slug FROM products WHERE id = {product_id};'
                    '"',
                    f'Step 4b: Product Details (ID {product_id})')
            
            # Count current media
            run_cmd(client,
                    f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                    f'SELECT COUNT(*) as current_media FROM product_media WHERE product_id = {product_id};'
                    '"',
                    f'Step 4c: Current Media Count BEFORE Association')
            
            # Run media:associate-legacy for this product (DRY RUN first)
            run_cmd(client,
                    f'cd {BASE_PATH} && php artisan media:associate-legacy '
                    f'--product={product_id} '
                    f'--dry-run',
                    f'Step 4d: DRY RUN - media:associate-legacy for Product {product_id}',
                    timeout=180)
            
            print(f"\n>>> DRY RUN complete. Ready to run ACTUAL import.")
            print(f">>> Command: php artisan media:associate-legacy --product={product_id}")
            
        else:
            print("No suitable products found for testing")
        
        print("\n" + "="*70)
        print("✓ Step 4 completed - Media association test")
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
