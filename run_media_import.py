#!/usr/bin/env python3
"""Run media:associate-legacy command"""
import paramiko
import sys
import time

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        print(f"✓ Connected\n")
        
        print("="*70)
        print("STARTING MEDIA IMPORT")
        print("="*70)
        print("\nThis will:")
        print("  - Import product images from WordPress uploads")
        print("  - Convert to AVIF/WebP formats")
        print("  - Generate thumbnail/gallery/hero sizes")
        print("  - Associate images with products and variants")
        print("\nEstimated time: 20-30 minutes")
        print("="*70 + "\n")
        
        # Start import in background
        stdin, stdout, stderr = client.exec_command(
            f'cd {BASE_PATH} && nohup php artisan media:associate-legacy '
            f'--no-interaction > /tmp/media_import.log 2>&1 & echo $!',
            timeout=10
        )
        pid = stdout.read().decode().strip()
        print(f"✓ Media import started (PID: {pid})")
        print(f"✓ Log file: /tmp/media_import.log\n")
        
        # Monitor progress
        print("Monitoring progress...\n")
        last_count = 0
        checks = 0
        
        while checks < 60:  # Max 60 minutes
            time.sleep(30)  # Check every 30 seconds
            
            # Check if process still running
            stdin, stdout, stderr = client.exec_command(f'ps -p {pid}', timeout=10)
            ps_output = stdout.read().decode()
            
            if pid not in ps_output:
                print("\n✓ Import process completed")
                break
            
            # Get current media count
            stdin, stdout, stderr = client.exec_command(
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB '
                f'-e "SELECT COUNT(*) FROM product_media;" | tail -1',
                timeout=30
            )
            count = stdout.read().decode().strip()
            
            if count.isdigit():
                current = int(count)
                added = current - last_count
                print(f"[{time.strftime('%H:%M:%S')}] Media records: {current} (+{added})")
                last_count = current
            
            checks += 1
        
        # Show final results
        print("\n" + "="*70)
        print("IMPORT RESULTS")
        print("="*70)
        
        stdin, stdout, stderr = client.exec_command(
            f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
            'SELECT '
            '  (SELECT COUNT(*) FROM product_media) as total_media, '
            '  (SELECT COUNT(*) FROM product_media WHERE product_variant_id IS NULL) as product_level, '
            '  (SELECT COUNT(*) FROM product_media WHERE product_variant_id IS NOT NULL) as variant_specific;'
            '"',
            timeout=30
        )
        print(stdout.read().decode())
        
        # Show last 20 lines of log
        print("\n" + "="*70)
        print("IMPORT LOG (last 20 lines)")
        print("="*70)
        stdin, stdout, stderr = client.exec_command('tail -20 /tmp/media_import.log', timeout=10)
        print(stdout.read().decode())
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
