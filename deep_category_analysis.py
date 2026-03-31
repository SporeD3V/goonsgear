#!/usr/bin/env python3
"""Deep analysis of WordPress category structure"""
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
        
        # WP category structure
        run_cmd(client,
                f'mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e "'
                'SELECT '
                '  t.term_id, '
                '  t.name, '
                '  t.slug, '
                '  tt.taxonomy, '
                '  tt.count '
                'FROM wp_terms t '
                'JOIN wp_term_taxonomy tt ON tt.term_id = t.term_id '
                'WHERE tt.taxonomy = \\"product_cat\\" '
                'ORDER BY tt.count DESC;'
                '"',
                'WP Product Categories (wp_terms + wp_term_taxonomy)')
        
        # Laravel categories
        run_cmd(client,
                f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT id, name, slug FROM categories ORDER BY name;'
                '"',
                'Laravel Categories Table')
        
        # Category mapping
        run_cmd(client,
                f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  ilc.legacy_term_id, '
                '  ilc.category_id, '
                '  c.name as laravel_name, '
                '  c.slug as laravel_slug '
                'FROM import_legacy_categories ilc '
                'JOIN categories c ON c.id = ilc.category_id '
                'ORDER BY ilc.legacy_term_id;'
                '"',
                'Import Category Mapping (legacy term_id → Laravel category)')
        
        # Check German Hip Hop specifically
        run_cmd(client,
                f'mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e "'
                'SELECT t.term_id, t.name, t.slug '
                'FROM wp_terms t '
                'JOIN wp_term_taxonomy tt ON tt.term_id = t.term_id '
                'WHERE tt.taxonomy = \\"product_cat\\" '
                'AND t.name LIKE \\"%German%\\";'
                '"',
                'WP: German Hip Hop Category Details')
        
        run_cmd(client,
                f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT id, name, slug FROM categories WHERE name LIKE \\"%German%\\";'
                '"',
                'Laravel: German Hip Hop Category Details')
        
        # Check products in German Hip Hop in WP
        run_cmd(client,
                f'mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e "'
                'SELECT COUNT(DISTINCT wtr.object_id) as wp_product_count '
                'FROM wp_term_relationships wtr '
                'JOIN wp_term_taxonomy wtt ON wtt.term_taxonomy_id = wtr.term_taxonomy_id '
                'JOIN wp_terms t ON t.term_id = wtt.term_id '
                'JOIN wp_posts p ON p.ID = wtr.object_id '
                'WHERE wtt.taxonomy = \\"product_cat\\" '
                'AND t.name LIKE \\"%German%\\" '
                'AND p.post_type = \\"product\\" '
                'AND p.post_status = \\"publish\\";'
                '"',
                'WP: Products in German Hip Hop (COUNT)')
        
        # Check Laravel category_product for German Hip Hop
        run_cmd(client,
                f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  c.id, '
                '  c.name, '
                '  c.slug, '
                '  COUNT(cp.product_id) as product_count '
                'FROM categories c '
                'LEFT JOIN category_product cp ON cp.category_id = c.id '
                'WHERE c.name LIKE \\"%German%\\" '
                'GROUP BY c.id;'
                '"',
                'Laravel: German Hip Hop with Product Count')
        
        # Sample products in German Hip Hop
        run_cmd(client,
                f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  p.id, '
                '  p.name, '
                '  p.status '
                'FROM products p '
                'JOIN category_product cp ON cp.product_id = p.id '
                'JOIN categories c ON c.id = cp.category_id '
                'WHERE c.name LIKE \\"%German%\\" '
                'LIMIT 10;'
                '"',
                'Sample Products in German Hip Hop Category')
        
        # Check ONYX category
        run_cmd(client,
                f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  c.id, '
                '  c.name, '
                '  c.slug, '
                '  COUNT(cp.product_id) as product_count '
                'FROM categories c '
                'LEFT JOIN category_product cp ON cp.category_id = c.id '
                'WHERE c.name LIKE \\"%ONYX%\\" OR c.name LIKE \\"%Onyx%\\" '
                'GROUP BY c.id;'
                '"',
                'ONYX Category with Product Count')
        
        print("\n" + "="*70)
        print("✓ Deep category analysis completed")
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
