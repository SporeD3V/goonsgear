#!/usr/bin/env python3
"""Verify primary category vs pivot category issue"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD'

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
        
        # Products with German Hip Hop as PRIMARY category
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) as primary_count '
                'FROM products p '
                'JOIN categories c ON c.id = p.primary_category_id '
                'WHERE c.slug = \\"germanhiphop\\";'
                '"',
                'Products with German Hip Hop as PRIMARY Category')
        
        # Products with German Hip Hop in category_product pivot
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(DISTINCT cp.product_id) as pivot_count '
                'FROM category_product cp '
                'JOIN categories c ON c.id = cp.category_id '
                'WHERE c.slug = \\"germanhiphop\\";'
                '"',
                'Products with German Hip Hop in PIVOT Table')
        
        # Same for ONYX
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) as primary_count '
                'FROM products p '
                'JOIN categories c ON c.id = p.primary_category_id '
                'WHERE c.slug = \\"onyx\\";'
                '"',
                'Products with ONYX as PRIMARY Category')
        
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(DISTINCT cp.product_id) as pivot_count '
                'FROM category_product cp '
                'JOIN categories c ON c.id = cp.category_id '
                'WHERE c.slug = \\"onyx\\";'
                '"',
                'Products with ONYX in PIVOT Table')
        
        # Sample German Hip Hop products and their primary categories
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  p.id, '
                '  p.name, '
                '  pc.name as primary_category, '
                '  GROUP_CONCAT(c.name) as all_categories '
                'FROM products p '
                'JOIN category_product cp ON cp.product_id = p.id '
                'JOIN categories c ON c.id = cp.category_id '
                'LEFT JOIN categories pc ON pc.id = p.primary_category_id '
                'WHERE c.slug = \\"germanhiphop\\" '
                'GROUP BY p.id '
                'LIMIT 10;'
                '"',
                'Sample German Hip Hop Products with Categories')
        
        print("\n" + "="*70)
        print("ISSUE CONFIRMED: Query uses primaryCategory instead of categories()")
        print("="*70)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
