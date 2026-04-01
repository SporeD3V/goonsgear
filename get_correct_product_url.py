#!/usr/bin/env python3
"""Get correct product URLs"""
import paramiko
import sys

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
        
        # Get products with images
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  p.id, '
                '  p.name, '
                '  p.slug, '
                '  p.status, '
                '  COUNT(pm.id) as images '
                'FROM products p '
                'JOIN product_media pm ON pm.product_id = p.id '
                'GROUP BY p.id '
                'HAVING images > 0 '
                'ORDER BY images DESC '
                'LIMIT 10;'
                '"',
                'Products with Images (sorted by image count)')
        
        print("\n" + "="*70)
        print("CORRECT URLS")
        print("="*70)
        print("\nProduct with most images (9 images):")
        print("https://goonsgear.macaw.studio/shop/giftcard")
        print("\nSnowgoons Trojan Horse (7 images):")
        print("https://goonsgear.macaw.studio/shop/snowgoons-trojan-horse-splatter-vinyl-blob-sand-splatter")
        print("\nSimple product (1 image):")
        print("https://goonsgear.macaw.studio/shop/snowgoons-soft-patch-hoodie")
        print("="*70)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
