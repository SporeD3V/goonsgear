#!/usr/bin/env python3
"""Deploy gallery optimization changes to staging"""
import os
import paramiko
import sys

HOST = os.environ.get('GOONSGEAR_SSH_HOST', '91.98.230.33')
PORT = int(os.environ.get('GOONSGEAR_SSH_PORT', '1221'))
USER = os.environ.get('GOONSGEAR_SSH_USER', 'spored3v')
PASSWORD = os.environ.get('GOONSGEAR_SSH_PASSWORD', '')

def run_cmd(client, cmd, label=None):
    if label:
        print(f"\n{'='*70}\n{label}\n{'='*70}")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=60)
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    if out:
        print(out)
    if err:
        print(f"Error: {err}")
    return out

def main():
    try:
        if PASSWORD == '':
            raise RuntimeError('Missing GOONSGEAR_SSH_PASSWORD environment variable.')

        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        
        # Upload ProductMedia.php
        print("\n" + "="*70)
        print("Uploading ProductMedia.php")
        print("="*70)
        sftp = client.open_sftp()
        sftp.put(
            'c:/Projects/goonsgear/app/Models/ProductMedia.php',
            '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/app/Models/ProductMedia.php'
        )
        
        # Upload shop/show.blade.php
        print("\n" + "="*70)
        print("Uploading shop/show.blade.php")
        print("="*70)
        sftp.put(
            'c:/Projects/goonsgear/resources/views/shop/show.blade.php',
            '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php'
        )
        
        # Upload shop/index.blade.php
        print("\n" + "="*70)
        print("Uploading shop/index.blade.php")
        print("="*70)
        sftp.put(
            'c:/Projects/goonsgear/resources/views/shop/index.blade.php',
            '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/index.blade.php'
        )
        
        # Upload ShopController.php
        print("\n" + "="*70)
        print("Uploading ShopController.php")
        print("="*70)
        sftp.put(
            'c:/Projects/goonsgear/app/Http/Controllers/ShopController.php',
            '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/app/Http/Controllers/ShopController.php'
        )
        
        sftp.close()
        
        # Clear caches
        run_cmd(client,
                "cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && "
                "php artisan view:clear && "
                "php artisan cache:clear && "
                "php artisan config:cache",
                'Clearing Laravel Caches')
        
        print("\n" + "="*70)
        print("✓ Deployment complete")
        print("\nTest URLs:")
        print("  Product: https://goonsgear.macaw.studio/shop/snowgoons-soft-patch-hoodie")
        print("  Catalog: https://goonsgear.macaw.studio/shop?category=vinyl")
        print("\nExpected behavior:")
        print("  - Main product image: 600x600 gallery variant (~50-150KB)")
        print("  - Gallery thumbnails: 200x200 (~10-50KB)")
        print("  - Catalog listings: 600x600 gallery variant")
        print("  - Live search: 200x200 thumbnails")
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
