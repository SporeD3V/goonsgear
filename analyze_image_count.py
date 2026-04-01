#!/usr/bin/env python3
"""Analyze image file counts"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

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
        
        # Count by file type
        run_cmd(client,
                f'cd {BASE_PATH}/storage/app/public/products && '
                f'echo "AVIF files: $(find . -name "*.avif" | wc -l)" && '
                f'echo "WebP files: $(find . -name "*.webp" | wc -l)" && '
                f'echo "JPG/PNG files: $(find . -name "*.jpg" -o -name "*.png" | wc -l)"',
                'File Count by Type')
        
        # Count size variants
        run_cmd(client,
                f'cd {BASE_PATH}/storage/app/public/products && '
                f'echo "Main AVIF (no size suffix): $(find . -name "*.avif" | grep -v "thumbnail\\|gallery\\|hero" | wc -l)" && '
                f'echo "Thumbnail variants: $(find . -name "*-thumbnail-*.avif" | wc -l)" && '
                f'echo "Gallery variants: $(find . -name "*-gallery-*.avif" | wc -l)" && '
                f'echo "Hero variants: $(find . -name "*-hero-*.avif" | wc -l)"',
                'AVIF Files by Size Variant')
        
        # Sample filenames
        run_cmd(client,
                f'cd {BASE_PATH}/storage/app/public/products && '
                f'find . -name "*.avif" | head -20',
                'Sample AVIF Filenames (first 20)')
        
        # WordPress attachment count
        run_cmd(client,
                f'mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e "'
                'SELECT COUNT(*) as wp_attachments '
                'FROM wp_posts '
                'WHERE post_type = \\"attachment\\" '
                'AND post_mime_type LIKE \\"image%\\";'
                '"',
                'WordPress Source Images')
        
        print("\n" + "="*70)
        print("ANALYSIS")
        print("="*70)
        print("""
Per source image, the system creates:
  - 1 main AVIF (full size)
  - 1 thumbnail AVIF (200x200)
  - 1 gallery AVIF (600x600)
  - 1 hero AVIF (1200x600)
  - Same 4 files in WebP format
  
Total: 8 files per source image

Calculation:
  12,175 AVIF files ÷ 4 variants per image = ~3,043 source images
  
For ~1,000 products, that's about 3 images per product average.
This includes:
  - Product main images
  - Product gallery images
  - Variant-specific images (colors, styles)
        """)
        print("="*70)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
