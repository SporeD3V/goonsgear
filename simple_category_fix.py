#!/usr/bin/env python3
"""Use simpler direct SQL to populate category pivot"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'

# Simpler SQL using connection across databases
SQL = """USE goonsgearDB;

INSERT INTO category_product (product_id, category_id, created_at, updated_at)
SELECT DISTINCT
    ilp.product_id,
    ilc.category_id,
    NOW(),
    NOW()
FROM goonsgearDB.import_legacy_products ilp
JOIN LEGACYgoonsgearDB.wp_term_relationships wtr ON wtr.object_id = ilp.legacy_wp_post_id
JOIN LEGACYgoonsgearDB.wp_term_taxonomy wtt ON wtt.term_taxonomy_id = wtr.term_taxonomy_id AND wtt.taxonomy = 'product_cat'
JOIN goonsgearDB.import_legacy_categories ilc ON ilc.legacy_term_id = wtt.term_id
WHERE ilp.product_id IS NOT NULL
  AND ilc.category_id IS NOT NULL
ON DUPLICATE KEY UPDATE updated_at = NOW();
"""

def run_cmd(client, cmd, label=None):
    if label:
        print(f"\n{'='*70}\n{label}\n{'='*70}")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=60)
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    if out:
        print(out)
    if err and 'warning' not in err.lower():
        print(f"STDERR: {err}")
    return out

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        print(f"✓ Connected\n")
        
        # Write SQL file
        sftp = client.open_sftp()
        with sftp.open('/tmp/fix_categories.sql', 'w') as f:
            f.write(SQL)
        sftp.close()
        
        # Execute
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy < /tmp/fix_categories.sql',
                'Populating Category Pivot')
        
        # Verify
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) as entries FROM category_product;'
                '"',
                'Total Pivot Entries')
        
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT c.name, COUNT(cp.product_id) as products '
                'FROM categories c '
                'JOIN category_product cp ON cp.category_id = c.id '
                'WHERE c.name LIKE \\\"%German%\\\" '
                'GROUP BY c.id;'
                '"',
                'German Hip Hop Products')
        
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT c.name, COUNT(cp.product_id) as count '
                'FROM categories c '
                'JOIN category_product cp ON cp.category_id = c.id '
                'GROUP BY c.id '
                'ORDER BY count DESC '
                'LIMIT 10;'
                '"',
                'Top Categories')
        
        print("\n✓ Category pivot populated!")
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
