#!/usr/bin/env python3
"""Find products with images already associated"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'

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
        
        # Find products with images
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  p.id, '
                '  p.slug, '
                '  p.name, '
                '  COUNT(pm.id) as image_count, '
                '  GROUP_CONCAT(SUBSTRING(pm.path, 1, 60)) as sample_paths '
                'FROM products p '
                'JOIN product_media pm ON pm.product_id = p.id '
                'GROUP BY p.id '
                'HAVING image_count > 0 '
                'ORDER BY p.id '
                'LIMIT 5;'
                '"',
                'Products with Images (First 5)')
        
        # Get specific product details
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  pm.id, '
                '  pm.path, '
                '  pm.mime_type, '
                '  pm.is_primary, '
                '  pm.product_variant_id '
                'FROM product_media pm '
                'WHERE pm.product_id = 2 '
                'ORDER BY pm.position;'
                '"',
                'Images for Product ID 2 (Trojan Horse Vinyl)')
        
        print("\n" + "="*70)
        print("TEST PRODUCT")
        print("="*70)
        print("\nProduct: Snowgoons - Trojan Horse Double Vinyl (ID: 2)")
        print("URL: https://goonsgear.macaw.studio/shop/snowgoons-trojan-horse-double-vinyl")
        print("\nThis product has 7 images associated.")
        print("Check if they display correctly on the product page.")
        print("="*70)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
