#!/usr/bin/env python3
"""Test if shop images are actually accessible"""
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
        
        # Get actual media paths from DB
        result = run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  p.slug, '
                '  pm.path, '
                '  pm.mime_type, '
                '  pm.is_converted, '
                '  pm.converted_to '
                'FROM products p '
                'JOIN product_media pm ON pm.product_id = p.id '
                'WHERE p.slug = \\"snowgoons-soft-patch-hoodie\\" '
                'LIMIT 5;'
                '"',
                'Product Media Paths for "Snowgoons Soft Patch Hoodie"')
        
        # Check if files exist on disk
        run_cmd(client,
                'ls -lh /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/storage/app/public/products/snowgoons-soft-patch-hoodie/gallery/ 2>/dev/null | head -20',
                'Actual Files on Disk')
        
        # Test media route
        run_cmd(client,
                'cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && '
                'php artisan route:list | grep media',
                'Media Route')
        
        print("\n" + "="*70)
        print("TEST URLS")
        print("="*70)
        print("\nShop listing: https://goonsgear.macaw.studio/shop")
        print("Product page: https://goonsgear.macaw.studio/shop/snowgoons-soft-patch-hoodie")
        print("\nCheck browser console for:")
        print("  - 404 errors on image URLs")
        print("  - Network tab for media.show routes")
        print("="*70)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
