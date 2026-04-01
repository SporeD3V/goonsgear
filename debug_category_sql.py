#!/usr/bin/env python3
"""Debug why category SQL isn't working"""
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
        
        # Check import_legacy_products
        run_cmd(client,
                f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) FROM import_legacy_products;'
                '"',
                'import_legacy_products count')
        
        # Sample import_legacy_products
        run_cmd(client,
                f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT * FROM import_legacy_products LIMIT 3;'
                '"',
                'Sample import_legacy_products')
        
        # Check WP categories for a product
        run_cmd(client,
                f'mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e "'
                'SELECT '
                '  wtr.object_id, '
                '  wtt.term_id, '
                '  t.name '
                'FROM wp_term_relationships wtr '
                'JOIN wp_term_taxonomy wtt ON wtt.term_taxonomy_id = wtr.term_taxonomy_id '
                'JOIN wp_terms t ON t.term_id = wtt.term_id '
                'WHERE wtt.taxonomy = \\"product_cat\\" '
                'AND wtr.object_id = 1422 '
                'LIMIT 5;'
                '"',
                'WP Categories for Product 1422')
        
        # Check import_legacy_categories
        run_cmd(client,
                f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT * FROM import_legacy_categories LIMIT 5;'
                '"',
                'Sample import_legacy_categories')
        
        # Test simpler query
        run_cmd(client,
                f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  ilp.product_id, '
                '  ilp.legacy_wp_post_id '
                'FROM import_legacy_products ilp '
                'WHERE ilp.legacy_wp_post_id = 1422;'
                '"',
                'Check product mapping for WP 1422')
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
