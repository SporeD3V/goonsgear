#!/usr/bin/env python3
"""Test the SELECT part of the category query"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'

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
        
        # Test the SELECT query
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) as matching_rows '
                'FROM goonsgearDB.import_legacy_products ilp '
                'JOIN LEGACYgoonsgearDB.wp_term_relationships wtr ON wtr.object_id = ilp.legacy_wp_post_id '
                'JOIN LEGACYgoonsgearDB.wp_term_taxonomy wtt ON wtt.term_taxonomy_id = wtr.term_taxonomy_id AND wtt.taxonomy = \\\"product_cat\\\" '
                'JOIN goonsgearDB.import_legacy_categories ilc ON ilc.legacy_term_id = wtt.term_id;'
                '"',
                'Test: Count matching rows')
        
        # Sample matching rows
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  ilp.product_id, '
                '  ilp.legacy_wp_post_id, '
                '  wtt.term_id, '
                '  ilc.category_id '
                'FROM goonsgearDB.import_legacy_products ilp '
                'JOIN LEGACYgoonsgearDB.wp_term_relationships wtr ON wtr.object_id = ilp.legacy_wp_post_id '
                'JOIN LEGACYgoonsgearDB.wp_term_taxonomy wtt ON wtt.term_taxonomy_id = wtr.term_taxonomy_id AND wtt.taxonomy = \\\"product_cat\\\" '
                'JOIN goonsgearDB.import_legacy_categories ilc ON ilc.legacy_term_id = wtt.term_id '
                'LIMIT 10;'
                '"',
                'Sample Matching Rows')
        
        # Check if there are NULL values blocking the INSERT
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  SUM(CASE WHEN ilp.product_id IS NULL THEN 1 ELSE 0 END) as null_product_ids, '
                '  SUM(CASE WHEN ilc.category_id IS NULL THEN 1 ELSE 0 END) as null_category_ids, '
                '  COUNT(*) as total '
                'FROM goonsgearDB.import_legacy_products ilp '
                'JOIN LEGACYgoonsgearDB.wp_term_relationships wtr ON wtr.object_id = ilp.legacy_wp_post_id '
                'JOIN LEGACYgoonsgearDB.wp_term_taxonomy wtt ON wtt.term_taxonomy_id = wtr.term_taxonomy_id AND wtt.taxonomy = \\\"product_cat\\\" '
                'JOIN goonsgearDB.import_legacy_categories ilc ON ilc.legacy_term_id = wtt.term_id;'
                '"',
                'Check for NULL values')
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
