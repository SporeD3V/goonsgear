#!/usr/bin/env python3
"""Fix Onyx Madface Empire Shirt by removing corrupted image and setting new primary"""
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
        
        # Delete corrupted 0-byte file
        run_cmd(client,
                "rm -f /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/storage/app/public/"
                "products/onyx-madface-empire-shirt/gallery/legacy-34298-onyx-empire-madface-shirt-red.avif",
                'Deleting 0-byte Corrupted File')
        
        # Delete corrupted media record
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "DELETE FROM product_media WHERE id = 4061;"
                "\"",
                'Deleting Corrupted Media Record (ID 4061)')
        
        # Set black shirt image as primary
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "UPDATE product_media SET is_primary = 1, position = 0 WHERE id = 4062;"
                "\"",
                'Setting Black Shirt Image as Primary')
        
        # Update other image position
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "UPDATE product_media SET position = 1 WHERE id = 4063;"
                "\"",
                'Updating Other Image Position')
        
        # Verify fix
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT "
                "  pm.id, "
                "  pm.path, "
                "  pm.is_primary, "
                "  pm.position "
                "FROM products p "
                "JOIN product_media pm ON pm.product_id = p.id "
                "WHERE p.slug = 'onyx-madface-empire-shirt' "
                "ORDER BY pm.is_primary DESC, pm.position;"
                "\"",
                'Verified Product Media (After Fix)')
        
        # Check new primary image file
        run_cmd(client,
                "ls -lh /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/storage/app/public/"
                "products/onyx-madface-empire-shirt/gallery/legacy-34297-onyx-empire-madface-shirt-black.avif",
                'New Primary Image File (Should NOT be 0 bytes)')
        
        print("\n" + "="*70)
        print("✓ Fix complete")
        print("Test: https://goonsgear.macaw.studio/shop/onyx-madface-empire-shirt")
        print("Primary image now: Black variant (valid AVIF)")
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
