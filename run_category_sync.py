#!/usr/bin/env python3
"""Run the category sync Artisan command"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

def run_cmd(client, cmd, label=None, timeout=300):
    if label:
        print(f"\n{'='*70}\n{label}\n{'='*70}")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=timeout)
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
        
        # Run sync command
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan products:sync-categories',
                'Syncing Product Categories',
                timeout=300)
        
        # Verify results
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) as entries FROM category_product;'
                '"',
                'Total Category Pivot Entries')
        
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT c.name, COUNT(cp.product_id) as products '
                'FROM categories c '
                'JOIN category_product cp ON cp.category_id = c.id '
                'WHERE c.name LIKE \\\"%German%\\\" '
                'GROUP BY c.id;'
                '"',
                'German Hip Hop Category')
        
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT c.name, COUNT(cp.product_id) as count '
                'FROM categories c '
                'JOIN category_product cp ON cp.category_id = c.id '
                'GROUP BY c.id '
                'ORDER BY count DESC '
                'LIMIT 10;'
                '"',
                'Top 10 Categories')
        
        # Check for merged products
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(DISTINCT product_id) as products_with_duplicates '
                'FROM import_legacy_products '
                'GROUP BY product_id '
                'HAVING COUNT(DISTINCT legacy_wp_post_id) > 1;'
                '" | tail -1',
                'Products with Multiple WP Post IDs (Should be 0)')
        
        print("\n" + "="*70)
        print("✓ Category sync completed")
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
