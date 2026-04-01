#!/usr/bin/env python3
"""Explore staging server infrastructure - databases and media"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

def run_command(client, cmd, label=None):
    """Execute command and print results"""
    if label:
        print(f"\n{'='*70}")
        print(f"  {label}")
        print('='*70)
    print(f"$ {cmd}\n")
    
    stdin, stdout, stderr = client.exec_command(cmd, timeout=60)
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    
    if out:
        print(out)
    if err and 'warning' not in err.lower():
        print(f"STDERR: {err}")
    
    return out, err

def main():
    print("Connecting to staging server...")
    
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        print(f"✓ Connected to {HOST}:{PORT}\n")
        
        # === DATABASE EXPLORATION ===
        
        # Main DB: List tables and row counts
        run_command(client, 
                   f'cd {BASE_PATH} && php artisan tinker --execute="'
                   'echo \'=== MAIN DATABASE (goonsgearDB) ===\' . PHP_EOL; '
                   '$tables = DB::select(\'SHOW TABLES\'); '
                   'foreach($tables as $t) {{ '
                   '  $table = array_values((array)$t)[0]; '
                   '  $count = DB::table($table)->count(); '
                   '  echo str_pad($table, 40) . \' : \' . number_format($count) . \' rows\' . PHP_EOL; '
                   '}}"',
                   'Main Database Tables & Row Counts')
        
        # Legacy DB: Check connection and tables
        run_command(client,
                   f'cd {BASE_PATH} && php artisan tinker --execute="'
                   'echo \'=== LEGACY DATABASE (LEGACYgoonsgearDB) ===\' . PHP_EOL; '
                   'try {{ '
                   '  $tables = DB::connection(\'legacy\')->select(\'SHOW TABLES\'); '
                   '  echo \'Total tables: \' . count($tables) . PHP_EOL . PHP_EOL; '
                   '  foreach($tables as $t) {{ '
                   '    $table = array_values((array)$t)[0]; '
                   '    if(str_contains($table, \'post\') || str_contains($table, \'product\') || str_contains($table, \'user\') || str_contains($table, \'order\')) {{ '
                   '      $count = DB::connection(\'legacy\')->table($table)->count(); '
                   '      echo str_pad($table, 40) . \' : \' . number_format($count) . \' rows\' . PHP_EOL; '
                   '    }} '
                   '  }} '
                   '}} catch(Exception $e) {{ '
                   '  echo \'ERROR: \' . $e->getMessage() . PHP_EOL; '
                   '}}"',
                   'Legacy Database (WordPress/WooCommerce)')
        
        # === MEDIA FOLDER EXPLORATION ===
        
        # Find WordPress media folders
        run_command(client,
                   f'find /home/macaw-goonsgear -type d -name "uploads" -o -name "media" -o -name "wp-content" 2>/dev/null | head -20',
                   'WordPress Media Folders')
        
        # Check Laravel storage structure
        run_command(client,
                   f'ls -lah {BASE_PATH}/storage/app/public/ 2>/dev/null || echo "No public storage"',
                   'Laravel Public Storage')
        
        # Look for media-related directories
        run_command(client,
                   f'find {BASE_PATH} -maxdepth 3 -type d \( -name "*media*" -o -name "*upload*" -o -name "*image*" \) 2>/dev/null',
                   'Media-Related Directories in Laravel App')
        
        # === AVIF CONVERSION SYSTEM ===
        
        # Search for AVIF-related code
        run_command(client,
                   f'cd {BASE_PATH} && grep -r "avif" --include="*.php" --include="*.py" app/ database/ 2>/dev/null | head -20',
                   'AVIF References in Code')
        
        # Check for conversion scripts
        run_command(client,
                   f'cd {BASE_PATH} && find . -maxdepth 2 -type f -name "*convert*" -o -name "*media*" -o -name "*image*" 2>/dev/null | grep -E "\.(py|php|sh)$"',
                   'Conversion Scripts')
        
        # Check for AVIF processing in database migrations
        run_command(client,
                   f'cd {BASE_PATH} && ls -lah database/migrations/ | grep -i media',
                   'Media-Related Migrations')
        
        # Check for import/processing commands
        run_command(client,
                   f'cd {BASE_PATH} && php artisan list | grep -E "(import|media|image|convert)"',
                   'Artisan Commands for Media/Import')
        
        # Sample product with media
        run_command(client,
                   f'cd {BASE_PATH} && php artisan tinker --execute="'
                   '$product = \\App\\Models\\Product::with(\'media\')->whereHas(\'media\')->first(); '
                   'if($product) {{ '
                   '  echo \'Product: \' . $product->name . PHP_EOL; '
                   '  echo \'Media count: \' . $product->media->count() . PHP_EOL; '
                   '  echo \'First media: \' . PHP_EOL; '
                   '  dump($product->media->first()?->toArray()); '
                   '}} else {{ '
                   '  echo \'No products with media found\' . PHP_EOL; '
                   '}}"',
                   'Sample Product Media Structure')
        
        print("\n" + "="*70)
        print("  ✓ Infrastructure exploration completed")
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
