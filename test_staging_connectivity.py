#!/usr/bin/env python3
"""Test staging server connectivity and status - READ-ONLY"""
import paramiko
import sys

# Connection details from .env.staging
HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

def run_command(client, cmd, label=None):
    """Execute command and print results"""
    if label:
        print(f"\n{'='*60}")
        print(f"  {label}")
        print('='*60)
    print(f"$ {cmd}\n")
    
    stdin, stdout, stderr = client.exec_command(cmd, timeout=30)
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    
    if out:
        print(out)
    if err:
        print(f"STDERR: {err}")
    
    return out, err

def main():
    print("Connecting to staging server...")
    
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        print(f"✓ Connected to {HOST}:{PORT} as {USER}\n")
        
        # Test 1: Basic server info
        run_command(client, 'uname -a && uptime', 'Server Info')
        
        # Test 2: PHP version
        run_command(client, 'php -v | head -3', 'PHP Version')
        
        # Test 3: Laravel installation
        run_command(client, f'cd {BASE_PATH} && php artisan --version', 'Laravel Version')
        
        # Test 4: Environment
        run_command(client, f'cd {BASE_PATH} && php artisan env', 'Laravel Environment')
        
        # Test 5: Check storage permissions
        run_command(client, f'ls -la {BASE_PATH}/storage/', 'Storage Directory')
        
        # Test 6: Database connection test (read-only query)
        run_command(client, 
                   f'cd {BASE_PATH} && php artisan tinker --execute="echo \'App DB: \' . DB::connection()->getDatabaseName() . PHP_EOL; echo \'Tables: \' . count(DB::select(\'SHOW TABLES\')) . PHP_EOL;"',
                   'App Database Connection')
        
        # Test 7: Check recent migrations
        run_command(client, f'cd {BASE_PATH} && php artisan migrate:status | tail -10', 'Migration Status')
        
        # Test 8: Disk space
        run_command(client, f'df -h {BASE_PATH}', 'Disk Space')
        
        print("\n" + "="*60)
        print("  ✓ All connectivity tests completed")
        print("="*60)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
