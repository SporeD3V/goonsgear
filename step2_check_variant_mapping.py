#!/usr/bin/env python3
"""Step 2: Check import_legacy_variants mapping completeness"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

def run_cmd(client, cmd, label=None):
    if label:
        print(f"\n{'='*70}\n{label}\n{'='*70}")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=90)
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
        
        # Laravel variant counts
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) as laravel_variants FROM product_variants;'
                '"',
                'Step 2a: Total Laravel Product Variants')
        
        # Import mapping count
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) as mapped_variants FROM import_legacy_variants;'
                '"',
                'Step 2b: Import Mapping Records (import_legacy_variants)')
        
        # Check if WP variations are mapped
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  legacy_wp_post_id, '
                '  product_variant_id '
                'FROM import_legacy_variants '
                'LIMIT 10;'
                '"',
                'Step 2c: Sample Import Mappings (WP ID -> Laravel ID)')
        
        # Cross-check: Mapped WP variations that have images
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e "'
                'SELECT COUNT(DISTINCT pm.post_id) as wp_vars_with_images_in_mapping '
                'FROM wp_postmeta pm '
                'JOIN wp_posts p ON p.ID = pm.post_id '
                'WHERE pm.meta_key = \\"_thumbnail_id\\" '
                'AND p.post_type = \\"product_variation\\" '
                'AND p.post_status = \\"publish\\" '
                'AND pm.meta_value != \\"\\" '
                'AND p.ID IN (1589, 1590, 1591, 1860, 1861, 1871, 1872, 1873);'
                '"',
                'Step 2d: Verify Sample WP Variations Still Have Images')
        
        # Check Laravel variants with media
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(DISTINCT product_variant_id) as variants_with_media '
                'FROM product_media '
                'WHERE product_variant_id IS NOT NULL;'
                '"',
                'Step 2e: Laravel Variants with Media Records')
        
        # Sample product with multiple variants
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  p.id as product_id, '
                '  p.name as product_name, '
                '  COUNT(pv.id) as variant_count, '
                '  COUNT(pm.id) as media_count '
                'FROM products p '
                'JOIN product_variants pv ON pv.product_id = p.id '
                'LEFT JOIN product_media pm ON pm.product_id = p.id '
                'GROUP BY p.id '
                'HAVING variant_count > 1 '
                'ORDER BY media_count DESC '
                'LIMIT 5;'
                '"',
                'Step 2f: Sample Products with Multiple Variants')
        
        print("\n" + "="*70)
        print("✓ Step 2 completed - Import mapping verification")
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
