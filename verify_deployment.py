#!/usr/bin/env python3
"""Verify deployment and media import completion"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD'

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
        
        # Check if import is still running
        ps = run_cmd(client, 'ps aux | grep "571977" | grep -v grep')
        if ps:
            print("\n⚠️ Import still running (PID 571977)")
        else:
            print("\n✓ Import completed")
        
        # Get final counts
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  (SELECT COUNT(*) FROM products) as products, '
                '  (SELECT COUNT(*) FROM product_variants) as variants, '
                '  (SELECT COUNT(*) FROM product_media) as media, '
                '  (SELECT COUNT(*) FROM category_product) as category_relationships;'
                '"',
                'Database State')
        
        # Category product counts
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT "
                "  c.name, "
                "  c.slug, "
                "  COUNT(DISTINCT cp.product_id) as product_count "
                "FROM categories c "
                "JOIN category_product cp ON cp.category_id = c.id "
                "WHERE c.slug IN ('germanhiphop', 'onyx', 'vinyl') "
                "GROUP BY c.id;"
                "\"",
                'Category Product Counts (Should match pivot table)')
        
        # Sample products with images
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT "
                "  p.slug, "
                "  p.name, "
                "  COUNT(pm.id) as image_count "
                "FROM products p "
                "JOIN product_media pm ON pm.product_id = p.id "
                "WHERE p.slug IN ('onyx-snowgoons-wakedafucup-shirt', 'snowgoons-soft-patch-hoodie') "
                "GROUP BY p.id;"
                "\"",
                'Sample Products with Images')
        
        print("\n" + "="*70)
        print("TEST URLS")
        print("="*70)
        print("\n1. Category pages (should show products):")
        print("   https://goonsgear.macaw.studio/shop?category=germanhiphop")
        print("   https://goonsgear.macaw.studio/shop?category=onyx")
        print("   https://goonsgear.macaw.studio/shop?category=vinyl")
        print("\n2. Products with images:")
        print("   https://goonsgear.macaw.studio/shop/onyx-snowgoons-wakedafucup-shirt")
        print("   https://goonsgear.macaw.studio/shop/snowgoons-soft-patch-hoodie")
        print("\n3. Shop listing:")
        print("   https://goonsgear.macaw.studio/shop")
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
