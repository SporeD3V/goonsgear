#!/usr/bin/env python3
"""Explore media system infrastructure on staging"""
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
    print(f"$ {cmd}\n")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=60)
    out = stdout.read().decode().strip()
    if out:
        print(out)
    return out

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        
        # 1. Legacy uploads directory structure
        run_cmd(client, f'ls -lah {BASE_PATH}/storage/app/legacy-uploads/', 
                'Legacy WordPress Uploads Directory')
        
        run_cmd(client, f'find {BASE_PATH}/storage/app/legacy-uploads/ -type f | head -20',
                'Sample Legacy Media Files (first 20)')
        
        run_cmd(client, f'du -sh {BASE_PATH}/storage/app/legacy-uploads/',
                'Legacy Uploads Total Size')
        
        # 2. Current Laravel storage structure
        run_cmd(client, f'ls -lah {BASE_PATH}/storage/app/public/',
                'Laravel Public Storage')
        
        run_cmd(client, f'find {BASE_PATH}/storage/app/public/products -type f | wc -l',
                'Total Product Media Files')
        
        run_cmd(client, f'find {BASE_PATH}/storage/app/public/products -name "*.avif" | wc -l',
                'AVIF Files Count')
        
        run_cmd(client, f'find {BASE_PATH}/storage/app/public/products -name "*.webp" | wc -l',
                'WebP Files Count')
        
        # 3. Sample product media files
        run_cmd(client, f'ls -lh {BASE_PATH}/storage/app/public/products/ | head -30',
                'Sample Product Directories')
        
        # 4. Database: product_media table stats
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan tinker --execute="'
                'echo \\\"Product Media Stats:\\\" . PHP_EOL; '
                'echo \\\"Total records: \\\" . \\App\\Models\\ProductMedia::count() . PHP_EOL; '
                'echo \\\"AVIF files: \\\" . \\App\\Models\\ProductMedia::where(\\\"mime_type\\\", \\\"image/avif\\\")->count() . PHP_EOL; '
                'echo \\\"WebP files: \\\" . \\App\\Models\\ProductMedia::where(\\\"mime_type\\\", \\\"image/webp\\\")->count() . PHP_EOL; '
                'echo \\\"Converted: \\\" . \\App\\Models\\ProductMedia::where(\\\"is_converted\\\", true)->count() . PHP_EOL; '
                '"',
                'Product Media Database Stats')
        
        # 5. Legacy database posts/attachments
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan tinker --execute="'
                'try {{ '
                '  echo \\\"Legacy DB wp_posts (attachments):\\\" . PHP_EOL; '
                '  $count = DB::connection(\\\"legacy\\\")->table(\\\"wp_posts\\\")->where(\\\"post_type\\\", \\\"attachment\\\")->count(); '
                '  echo \\\"Total attachments: \\\" . number_format($count) . PHP_EOL; '
                '}} catch(Exception $e) {{ '
                '  echo \\\"ERROR: \\\" . $e->getMessage() . PHP_EOL; '
                '}}"',
                'Legacy Database Attachments')
        
        # 6. Import mapping tables
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan tinker --execute="'
                'echo \\\"Import Mapping Tables:\\\" . PHP_EOL; '
                'echo \\\"import_legacy_products: \\\" . DB::table(\\\"import_legacy_products\\\")->count() . PHP_EOL; '
                'echo \\\"import_legacy_variants: \\\" . DB::table(\\\"import_legacy_variants\\\")->count() . PHP_EOL; '
                '"',
                'Import Mapping Records')
        
        # 7. Check for conversion utilities
        run_cmd(client, f'which convert imagick avifenc',
                'Image Conversion Utilities Available')
        
        run_cmd(client, f'php -m | grep -E "(gd|imagick|avif)"',
                'PHP Image Extensions')
        
        print("\n" + "="*70)
        print("✓ Media system exploration completed")
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
