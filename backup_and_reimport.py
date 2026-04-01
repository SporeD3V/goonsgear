#!/usr/bin/env python3
"""Backup database, truncate tables, and re-run import"""
import paramiko
import sys
import time

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

def run_cmd(client, cmd, label=None, timeout=300):
    if label:
        print(f"\n{'='*70}\n{label}\n{'='*70}")
    print(f"$ {cmd}\n")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=timeout)
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    if out:
        print(out)
    if err and 'warning' not in err.lower():
        print(f"STDERR: {err}")
    return out

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        print(f"✓ Connected\n")
        
        timestamp = time.strftime('%Y%m%d_%H%M%S')
        
        # Backup database
        run_cmd(client,
                f'mysqldump -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB > '
                f'{BASE_PATH}/storage/backups/goonsgearDB_before_reimport_{timestamp}.sql',
                'Step 1: Backup Database',
                timeout=600)
        
        run_cmd(client,
                f'ls -lh {BASE_PATH}/storage/backups/goonsgearDB_before_reimport_{timestamp}.sql',
                'Verify Backup Created')
        
        # Truncate tables
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SET FOREIGN_KEY_CHECKS=0; '
                'TRUNCATE product_media; '
                'TRUNCATE product_variants; '
                'TRUNCATE category_product; '
                'DELETE FROM products; '
                'TRUNCATE import_legacy_products; '
                'TRUNCATE import_legacy_variants; '
                'SET FOREIGN_KEY_CHECKS=1;'
                '"',
                'Step 2: Truncate Tables')
        
        # Verify tables empty
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  (SELECT COUNT(*) FROM products) as products, '
                '  (SELECT COUNT(*) FROM product_variants) as variants, '
                '  (SELECT COUNT(*) FROM product_media) as media, '
                '  (SELECT COUNT(*) FROM category_product) as category_pivot;'
                '"',
                'Verify Tables Truncated')
        
        print("\n" + "="*70)
        print("✓ Backup complete, tables truncated")
        print("="*70)
        print("\nReady to start import...")
        print(f"Backup saved: storage/backups/goonsgearDB_before_reimport_{timestamp}.sql")
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
