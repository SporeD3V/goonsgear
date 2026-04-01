#!/usr/bin/env python3
"""Check corrupted image for Onyx Madface Empire Shirt"""
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
        
        # Get product info
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT p.id, p.name, p.slug FROM products p "
                "WHERE p.slug = 'onyx-madface-empire-shirt';"
                "\"",
                'Product Info')
        
        # Get all media for this product
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT "
                "  pm.id, "
                "  pm.path, "
                "  pm.mime_type, "
                "  pm.is_primary, "
                "  pm.position, "
                "  pm.product_variant_id "
                "FROM products p "
                "JOIN product_media pm ON pm.product_id = p.id "
                "WHERE p.slug = 'onyx-madface-empire-shirt' "
                "ORDER BY pm.is_primary DESC, pm.position;"
                "\"",
                'Product Media Records')
        
        # Check if primary image file exists
        primary_path = run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT pm.path FROM products p "
                "JOIN product_media pm ON pm.product_id = p.id "
                "WHERE p.slug = 'onyx-madface-empire-shirt' AND pm.is_primary = 1 "
                "LIMIT 1;"
                "\" | tail -n 1")
        
        if primary_path and primary_path != 'path':
            # Check file on disk
            run_cmd(client,
                    f"ls -lh /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/storage/app/public/{primary_path}",
                    f'Primary Image File: {primary_path}')
            
            # Check file type
            run_cmd(client,
                    f"file /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/storage/app/public/{primary_path}",
                    'File Type')
            
            # Try to get image info with identify (ImageMagick)
            run_cmd(client,
                    f"identify /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/storage/app/public/{primary_path} 2>&1 || echo 'Image is corrupted or unreadable'",
                    'Image Info (ImageMagick)')
        
        # Check legacy WP data for this product
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT map.legacy_wp_post_id FROM products p "
                "JOIN import_legacy_products map ON map.product_id = p.id "
                "WHERE p.slug = 'onyx-madface-empire-shirt';"
                "\"",
                'Legacy WP Post ID')
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
