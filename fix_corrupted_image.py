#!/usr/bin/env python3
"""Fix corrupted image by finding source and re-converting"""
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
        
        # Get legacy attachment ID for the corrupted image
        run_cmd(client,
                "mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e \""
                "SELECT "
                "  p.ID, "
                "  p.post_title, "
                "  p.guid "
                "FROM wp_posts p "
                "WHERE p.ID = 34298;"
                "\"",
                'Legacy WordPress Attachment Info (ID 34298)')
        
        # Get the file path from postmeta
        run_cmd(client,
                "mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e \""
                "SELECT "
                "  meta_key, "
                "  meta_value "
                "FROM wp_postmeta "
                "WHERE post_id = 34298 "
                "AND meta_key = '_wp_attached_file';"
                "\"",
                'Legacy File Path')
        
        # Check if source file exists
        source_path = run_cmd(client,
                "mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e \""
                "SELECT meta_value FROM wp_postmeta "
                "WHERE post_id = 34298 AND meta_key = '_wp_attached_file';"
                "\" | tail -n 1")
        
        if source_path and source_path != 'meta_value':
            legacy_root = '/home/macaw-goonsgear/htdocs/legacy.goonsgear.macaw.studio/wp-content/uploads'
            full_source = f"{legacy_root}/{source_path}"
            
            run_cmd(client,
                    f"ls -lh {full_source}",
                    f'Source File: {full_source}')
            
            run_cmd(client,
                    f"file {full_source}",
                    'Source File Type')
        
        # Check all 0-byte AVIF files
        run_cmd(client,
                "find /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/storage/app/public/products "
                "-name '*.avif' -size 0 | head -20",
                'All 0-byte AVIF Files (First 20)')
        
        print("\n" + "="*70)
        print("SOLUTION OPTIONS")
        print("="*70)
        print("\n1. Re-run media import with fixed conversion logic")
        print("2. Manually convert source image using GD/Imagick")
        print("3. Delete corrupted media record and re-associate")
        print("\nRecommendation: Delete product_media record for corrupted image,")
        print("then re-run: php artisan media:associate-legacy")
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
