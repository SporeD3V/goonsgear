#!/usr/bin/env python3
"""Deploy variant UX improvements to staging"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD'

def run_cmd(client, cmd, label=None):
    if label:
        print(f"\n{'='*70}\n{label}\n{'='*70}")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=120)
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    if out:
        print(out)
    if err and 'Warning' not in err:
        print(f"Error: {err}")
    return out

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        
        print("\n" + "="*70)
        print("DEPLOYING VARIANT UX IMPROVEMENTS")
        print("="*70)
        
        # Upload files via SFTP
        sftp = client.open_sftp()
        
        print("\nUploading migration...")
        sftp.put(
            'c:/Projects/goonsgear/database/migrations/2026_03_31_194852_add_variant_type_to_product_variants_table.php',
            '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/database/migrations/2026_03_31_194852_add_variant_type_to_product_variants_table.php'
        )
        
        print("Uploading ProductVariant model...")
        sftp.put(
            'c:/Projects/goonsgear/app/Models/ProductVariant.php',
            '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/app/Models/ProductVariant.php'
        )
        
        print("Uploading AssignVariantTypes command...")
        sftp.put(
            'c:/Projects/goonsgear/app/Console/Commands/AssignVariantTypes.php',
            '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/app/Console/Commands/AssignVariantTypes.php'
        )
        
        print("Uploading shop views...")
        sftp.put(
            'c:/Projects/goonsgear/resources/views/shop/show.blade.php',
            '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php'
        )
        sftp.put(
            'c:/Projects/goonsgear/resources/views/shop/index.blade.php',
            '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/index.blade.php'
        )
        
        sftp.close()
        
        # Run migration
        run_cmd(client,
                "cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && "
                "php artisan migrate --force",
                'Running Migration')
        
        # Clear caches
        run_cmd(client,
                "cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && "
                "php artisan view:clear && "
                "php artisan cache:clear && "
                "php artisan config:cache",
                'Clearing Caches')
        
        # Run dry-run of variant assignment
        run_cmd(client,
                "cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && "
                "php artisan variants:assign-types --dry-run | head -100",
                'Dry-Run Preview (first 100 lines)')
        
        print("\n" + "="*70)
        print("✓ Deployment complete!")
        print("\nNext steps:")
        print("1. Review the dry-run results above")
        print("2. Run: php artisan variants:assign-types (without --dry-run)")
        print("3. Test URLs:")
        print("   - https://goonsgear.macaw.studio/shop/onyx-all-white-madface-shirt")
        print("   - https://goonsgear.macaw.studio/shop (check breadcrumbs)")
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
