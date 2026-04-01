#!/usr/bin/env python3
"""Investigate data integrity issues - wrong images, incorrect variants"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

def run_cmd(client, cmd, label=None):
    if label:
        print(f"\n{'='*70}\n{label}\n{'='*70}")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=60)
    out = stdout.read().decode().strip()
    if out:
        print(out)
    return out

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        print(f"✓ Connected\n")
        
        # Example 1: Snowgoons - Goon Bap Anniversary Tape
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT id, name, slug FROM products '
                'WHERE slug = \\"snowgoons-goon-bap-anniversary-tape\\";'
                '"',
                'Example 1: Product - Snowgoons Goon Bap Tape')
        
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  pm.id, '
                '  pm.product_id, '
                '  pm.product_variant_id, '
                '  SUBSTRING(pm.path, 1, 80) as path, '
                '  pm.is_primary '
                'FROM product_media pm '
                'JOIN products p ON p.id = pm.product_id '
                'WHERE p.slug = \\"snowgoons-goon-bap-anniversary-tape\\" '
                'ORDER BY pm.is_primary DESC, pm.position;'
                '"',
                'Media for Snowgoons Product')
        
        # Check if "nine-tape" image belongs to another product
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT DISTINCT p.id, p.name, p.slug '
                'FROM products p '
                'WHERE p.name LIKE \\"%Nine%\\" OR p.slug LIKE \\"%nine%\\" '
                'LIMIT 5;'
                '"',
                'Products with "Nine" in name')
        
        # Example 2: Onyx Keychain
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT id, name, slug FROM products '
                'WHERE slug = \\"onyx-keychain\\";'
                '"',
                'Example 2: Product - Onyx Keychain')
        
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) as variant_count '
                'FROM product_variants pv '
                'JOIN products p ON p.id = pv.product_id '
                'WHERE p.slug = \\"onyx-keychain\\";'
                '"',
                'Variant count for Onyx Keychain')
        
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  pv.id, '
                '  pv.sku, '
                '  pv.name '
                'FROM product_variants pv '
                'JOIN products p ON p.id = pv.product_id '
                'WHERE p.slug = \\"onyx-keychain\\";'
                '"',
                'Variants for Onyx Keychain (should be 1 default only)')
        
        # Example 3: Sean Price Hoodie nested in Skate Deck
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT id, name, slug FROM products '
                'WHERE slug = \\"onyx-jeru-the-damaja-skate-deck\\";'
                '"',
                'Example 3: Product - Skate Deck')
        
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  pv.id, '
                '  pv.name, '
                '  pv.sku '
                'FROM product_variants pv '
                'JOIN products p ON p.id = pv.product_id '
                'WHERE p.slug = \\"onyx-jeru-the-damaja-skate-deck\\" '
                'ORDER BY pv.id '
                'LIMIT 10;'
                '"',
                'Variants for Skate Deck (check for Sean Price Hoodie)')
        
        # Check import mapping for Skate Deck
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  map.legacy_wp_post_id, '
                '  map.product_id, '
                '  p.name '
                'FROM import_legacy_products map '
                'JOIN products p ON p.id = map.product_id '
                'WHERE p.slug = \\"onyx-jeru-the-damaja-skate-deck\\";'
                '"',
                'Import mapping for Skate Deck')
        
        # Category check
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT id, name, slug FROM categories '
                'WHERE slug LIKE \\"%german%\\" OR name LIKE \\"%German%\\";'
                '"',
                'Category: German Hip Hop')
        
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  c.name as category, '
                '  COUNT(cp.product_id) as product_count '
                'FROM categories c '
                'LEFT JOIN category_product cp ON cp.category_id = c.id '
                'WHERE c.slug LIKE \\"%german%\\" '
                'GROUP BY c.id;'
                '"',
                'Products in German Hip Hop category')
        
        # Check multi-category support
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  p.id, '
                '  p.name, '
                '  COUNT(cp.category_id) as category_count '
                'FROM products p '
                'JOIN category_product cp ON cp.product_id = p.id '
                'GROUP BY p.id '
                'HAVING category_count > 1 '
                'LIMIT 10;'
                '"',
                'Products with Multiple Categories')
        
        print("\n" + "="*70)
        print("✓ Investigation completed")
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
