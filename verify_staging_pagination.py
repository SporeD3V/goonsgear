#!/usr/bin/env python3
"""Verify pagination on staging site"""
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
        
        # Check pagination config
        run_cmd(client,
                "grep -r 'shop_products_per_page' /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/config/",
                'Pagination Config File')
        
        # Get actual per page value
        per_page_output = run_cmd(client,
                "cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && "
                "php artisan tinker --execute \"echo config('pagination.shop_products_per_page', 12);\"")
        
        print(f"\nPer page value: {per_page_output}")
        
        # Check if pagination view exists
        run_cmd(client,
                "ls -la /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/vendor/pagination/tailwind.blade.php",
                'Tailwind Pagination View')
        
        # Test actual URL
        print("\n" + "="*70)
        print("Manual verification needed:")
        print("1. Visit: https://goonsgear.macaw.studio/shop?category=germanhiphop&page=7")
        print("2. Check if 'Next' button is clickable or disabled")
        print("3. Total products: 82, Per page: 12 = 7 pages")
        print("4. Page 7 should show disabled 'Next' button")
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
