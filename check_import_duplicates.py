#!/usr/bin/env python3
"""Check for duplicate WP products mapped to same Laravel product"""
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
        print(f"✓ Connected\n")
        
        # Find Laravel products with multiple WP post IDs
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  product_id, '
                '  COUNT(DISTINCT legacy_wp_post_id) as wp_post_count, '
                '  GROUP_CONCAT(legacy_wp_post_id) as wp_post_ids '
                'FROM import_legacy_products '
                'GROUP BY product_id '
                'HAVING wp_post_count > 1 '
                'ORDER BY wp_post_count DESC '
                'LIMIT 20;'
                '"',
                'Laravel Products with Multiple WP Post IDs (PROBLEM!)')
        
        # Count total affected
        result = run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(DISTINCT product_id) as affected_products '
                'FROM import_legacy_products '
                'GROUP BY product_id '
                'HAVING COUNT(DISTINCT legacy_wp_post_id) > 1;'
                '" | tail -1',
                'Total Products Affected by Duplicate Mapping')
        
        # Check WP for those post IDs
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e "'
                'SELECT ID, post_title, post_status '
                'FROM wp_posts '
                'WHERE ID IN (9736, 22017) '
                'AND post_type = \\"product\\";'
                '"',
                'WP Posts 9736 and 22017 (both mapped to Skate Deck)')
        
        # Check category mapping
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  p.id, '
                '  p.name, '
                '  p.primary_category_id, '
                '  c.name as primary_category '
                'FROM products p '
                'LEFT JOIN categories c ON c.id = p.primary_category_id '
                'WHERE p.id = 280;'
                '"',
                'Skate Deck Primary Category')
        
        # Check category_product pivot
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  cp.product_id, '
                '  c.name as category '
                'FROM category_product cp '
                'JOIN categories c ON c.id = cp.category_id '
                'WHERE cp.product_id = 280;'
                '"',
                'Skate Deck All Categories (pivot table)')
        
        # Sample WP product with German Hip Hop category
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e "'
                'SELECT '
                '  p.ID, '
                '  p.post_title, '
                '  GROUP_CONCAT(t.name) as categories '
                'FROM wp_posts p '
                'LEFT JOIN wp_term_relationships tr ON tr.object_id = p.ID '
                'LEFT JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = \\"product_cat\\" '
                'LEFT JOIN wp_terms t ON t.term_id = tt.term_id '
                'WHERE p.post_type = \\"product\\" '
                'AND p.post_status = \\"publish\\" '
                'GROUP BY p.ID '
                'HAVING categories LIKE \\"%German%\\" '
                'LIMIT 5;'
                '"',
                'WP Products in German Hip Hop Category')
        
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
