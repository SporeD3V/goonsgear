#!/usr/bin/env python3
"""Check import results and identify issues"""
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
        
        # Check for duplicate WP post IDs
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  product_id, '
                '  COUNT(DISTINCT legacy_wp_post_id) as wp_count '
                'FROM import_legacy_products '
                'GROUP BY product_id '
                'HAVING wp_count > 1;'
                '"',
                'Check: Any Products with Multiple WP Post IDs?')
        
        # Check category_product pivot
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) as pivot_count FROM category_product;'
                '"',
                'Check: Category Pivot Table')
        
        # Check products with primary_category_id
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  COUNT(*) as total_products, '
                '  SUM(CASE WHEN primary_category_id IS NOT NULL THEN 1 ELSE 0 END) as with_primary_category '
                'FROM products;'
                '"',
                'Check: Products with Primary Category')
        
        # Check import_legacy_categories mapping
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) FROM import_legacy_categories;'
                '"',
                'Check: Category Mapping Table')
        
        # Sample product - check if it has categories in WP
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e "'
                'SELECT '
                '  p.ID, '
                '  p.post_title, '
                '  GROUP_CONCAT(t.name) as categories '
                'FROM wp_posts p '
                'JOIN wp_term_relationships tr ON tr.object_id = p.ID '
                'JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id '
                'JOIN wp_terms t ON t.term_id = tt.term_id '
                'WHERE p.post_type = \\"product\\" '
                'AND p.post_status = \\"publish\\" '
                'AND tt.taxonomy = \\"product_cat\\" '
                'GROUP BY p.ID '
                'LIMIT 5;'
                '"',
                'Sample WP Products with Categories')
        
        # Check a specific product
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  p.id, '
                '  p.name, '
                '  p.primary_category_id, '
                '  c.name as primary_category '
                'FROM products p '
                'LEFT JOIN categories c ON c.id = p.primary_category_id '
                'LIMIT 5;'
                '"',
                'Sample Laravel Products')
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
