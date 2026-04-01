#!/usr/bin/env python3
"""Check pagination behavior"""
import paramiko
import sys
import re

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD'

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
        
        # Get products per page
        per_page = run_cmd(client,
                "cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && "
                "php artisan tinker --execute 'echo config(\"pagination.shop_products_per_page\", 12);'",
                'Products Per Page')
        
        # Count German Hip Hop products
        count = run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT COUNT(DISTINCT p.id) as total "
                "FROM products p "
                "JOIN category_product cp ON cp.product_id = p.id "
                "JOIN categories c ON c.id = cp.category_id "
                "WHERE c.slug = 'germanhiphop' AND p.status = 'active';"
                "\" | tail -n 1")
        
        # Fetch last page HTML to check for next button
        html = run_cmd(client,
                "curl -s 'https://goonsgear.macaw.studio/shop?category=germanhiphop&page=7'")
        
        has_next = 'rel="next"' in html or 'pagination.next' in html
        has_disabled_next = 'cursor-not-allowed' in html and 'pagination.next' in html
        
        print(f"\nTotal products: {count}")
        print(f"Per page: {per_page}")
        print(f"Expected pages: {int(count) / int(per_page.strip()) if count.isdigit() else 'N/A'}")
        print(f"\nPage 7 has clickable 'Next': {has_next and not has_disabled_next}")
        print(f"Page 7 has disabled 'Next': {has_disabled_next}")
        
        if has_next and not has_disabled_next:
            print("\n⚠️ BUG CONFIRMED: Last page shows active 'Next' button")
        else:
            print("\n✓ Pagination working correctly")
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
