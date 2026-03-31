#!/usr/bin/env python3
"""Test pagination behavior on category pages"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'

def run_cmd(client, cmd, label=None):
    if label:
        print(f"\n{'='*70}\n{label}\n{'='*70}")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=30)
    out = stdout.read().decode().strip()
    if out:
        print(out)
    return out

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        
        # Get pagination config
        run_cmd(client,
                "cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && "
                "php artisan tinker --execute 'echo config(\"pagination.shop_products_per_page\", 12);'",
                'Products Per Page Config')
        
        # Count products in German Hip Hop category
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT COUNT(DISTINCT p.id) as total_products "
                "FROM products p "
                "JOIN category_product cp ON cp.product_id = p.id "
                "JOIN categories c ON c.id = cp.category_id "
                "WHERE c.slug = 'germanhiphop' AND p.status = 'active';"
                "\"",
                'German Hip Hop Active Products')
        
        # Calculate expected pages (82 products / 12 per page = 7 pages)
        print("\n" + "="*70)
        print("Expected: 82 products / 12 per page = ~7 pages")
        print("Test URLs:")
        print("  Page 1: https://goonsgear.macaw.studio/shop?category=germanhiphop")
        print("  Page 7: https://goonsgear.macaw.studio/shop?category=germanhiphop&page=7")
        print("\nPage 7 should NOT show 'Next' button")
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
