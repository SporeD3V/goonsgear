#!/usr/bin/env python3
"""Create SQL file on server and execute it"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

SQL_CONTENT = """
INSERT INTO category_product (product_id, category_id, position, created_at, updated_at)
SELECT DISTINCT
    ilp.product_id,
    ilc.category_id,
    0 as position,
    NOW() as created_at,
    NOW() as updated_at
FROM import_legacy_products ilp
CROSS JOIN (
    SELECT 
        object_id as wp_post_id,
        term_id as wp_term_id
    FROM LEGACYgoonsgearDB.wp_term_relationships wtr
    JOIN LEGACYgoonsgearDB.wp_term_taxonomy wtt ON wtt.term_taxonomy_id = wtr.term_taxonomy_id
    WHERE wtt.taxonomy = 'product_cat'
) wp_cats ON wp_cats.wp_post_id = ilp.legacy_wp_post_id
JOIN import_legacy_categories ilc ON ilc.legacy_term_id = wp_cats.wp_term_id
WHERE ilp.product_id IS NOT NULL
  AND ilc.category_id IS NOT NULL
ON DUPLICATE KEY UPDATE updated_at = NOW();
"""

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
        
        # Create SQL file on server
        sftp = client.open_sftp()
        sql_file = f'{BASE_PATH}/fix_categories.sql'
        with sftp.open(sql_file, 'w') as f:
            f.write(SQL_CONTENT)
        sftp.close()
        print(f"✓ Created SQL file: {sql_file}\n")
        
        # Execute SQL
        run_cmd(client,
                f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB < {sql_file}',
                'Executing Category Pivot SQL')
        
        # Verify total entries
        run_cmd(client,
                f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) as total_entries FROM category_product;'
                '"',
                'Total Category Pivot Entries')
        
        # Check German Hip Hop
        run_cmd(client,
                f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT c.name, COUNT(cp.product_id) as products '
                'FROM categories c '
                'JOIN category_product cp ON cp.category_id = c.id '
                'WHERE c.slug LIKE \\\"%german%\\\" '
                'GROUP BY c.id;'
                '"',
                'German Hip Hop Category')
        
        # Top categories
        run_cmd(client,
                f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT c.name, COUNT(cp.product_id) as products '
                'FROM categories c '
                'JOIN category_product cp ON cp.category_id = c.id '
                'GROUP BY c.id '
                'ORDER BY products DESC '
                'LIMIT 10;'
                '"',
                'Top 10 Categories by Product Count')
        
        print("\n" + "="*70)
        print("✓ Category pivot populated successfully")
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
