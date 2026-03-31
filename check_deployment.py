#!/usr/bin/env python3
"""Check if deployment was successful"""
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
        
        # Check ProductMedia.php for getGalleryPath
        result = run_cmd(client,
                "grep -n 'getGalleryPath' /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/app/Models/ProductMedia.php",
                'Check if getGalleryPath exists')
        
        if not result:
            print("\n⚠️ getGalleryPath() NOT FOUND in ProductMedia.php!")
            print("Deployment may have failed.")
        else:
            print("\n✓ getGalleryPath() found in ProductMedia.php")
        
        # Check show.blade.php
        result = run_cmd(client,
                "grep 'getGalleryPath\\|->path' /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php | head -5",
                'Check show.blade.php')
        
        # Test via Tinker
        run_cmd(client,
                "cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && "
            "php artisan tinker --execute '$m = App\\Models\\ProductMedia::first(); "
                "echo method_exists($m, \"getGalleryPath\") ? \"Method exists\" : \"Method missing\";'",
                'Test if method is callable')
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
