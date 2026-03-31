#!/usr/bin/env python3
"""Step 3: Examine specific product with variants (WP vs Laravel)"""
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
    if out:
        print(out)
    return out

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        print(f"✓ Connected\n")
        
        # Pick a product with multiple variants - using product ID 128 from Step 2
        product_id = 128
        
        # Get Laravel product info
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                f'SELECT id, name, slug FROM products WHERE id = {product_id};'
                '"',
                f'Step 3a: Laravel Product Info (ID {product_id})')
        
        # Get Laravel variants for this product
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                f'SELECT id, sku, name FROM product_variants WHERE product_id = {product_id} LIMIT 10;'
                '"',
                f'Step 3b: Laravel Variants (First 10)')
        
        # Get WP post ID from import mapping
        wp_post_id = run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                f'SELECT legacy_wp_post_id FROM import_legacy_products WHERE product_id = {product_id};'
                '" | tail -1',
                f'Step 3c: Get WP Post ID for Product {product_id}')
        
        if wp_post_id and wp_post_id.strip():
            # Get WP product info
            run_cmd(client,
                    f'cd {BASE_PATH} && mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e "'
                    f'SELECT ID, post_title, post_name FROM wp_posts WHERE ID = {wp_post_id.strip()};'
                    '"',
                    f'Step 3d: WP Product Info (ID {wp_post_id.strip()})')
            
            # Get WP variations for this product
            run_cmd(client,
                    f'cd {BASE_PATH} && mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e "'
                    f'SELECT ID, post_name FROM wp_posts '
                    f'WHERE post_type = \\"product_variation\\" '
                    f'AND post_parent = {wp_post_id.strip()} '
                    f'AND post_status = \\"publish\\" '
                    f'LIMIT 10;'
                    '"',
                    f'Step 3e: WP Variations (First 10)')
            
            # Check which WP variations have images
            run_cmd(client,
                    f'cd {BASE_PATH} && mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e "'
                    f'SELECT '
                    f'  p.ID as variation_id, '
                    f'  pm.meta_value as thumbnail_id '
                    f'FROM wp_posts p '
                    f'LEFT JOIN wp_postmeta pm ON pm.post_id = p.ID AND pm.meta_key = \\"_thumbnail_id\\" '
                    f'WHERE p.post_type = \\"product_variation\\" '
                    f'AND p.post_parent = {wp_post_id.strip()} '
                    f'AND p.post_status = \\"publish\\" '
                    f'LIMIT 10;'
                    '"',
                    f'Step 3f: WP Variations with Thumbnail IDs')
        
        # Get Laravel media for this product
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                f'SELECT '
                f'  id, '
                f'  product_variant_id, '
                f'  SUBSTRING(path, 1, 60) as path_preview, '
                f'  mime_type, '
                f'  is_primary '
                f'FROM product_media '
                f'WHERE product_id = {product_id} '
                f'ORDER BY product_variant_id IS NULL DESC, is_primary DESC, position;'
                '"',
                f'Step 3g: Laravel Media Records for Product {product_id}')
        
        # Count breakdown
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                f'SELECT '
                f'  SUM(CASE WHEN product_variant_id IS NULL THEN 1 ELSE 0 END) as product_level_media, '
                f'  SUM(CASE WHEN product_variant_id IS NOT NULL THEN 1 ELSE 0 END) as variant_specific_media, '
                f'  COUNT(*) as total_media '
                f'FROM product_media '
                f'WHERE product_id = {product_id};'
                '"',
                f'Step 3h: Media Distribution Summary')
        
        print("\n" + "="*70)
        print("✓ Step 3 completed - Product example examined")
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
