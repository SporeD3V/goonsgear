#!/usr/bin/env python3
"""Check if import is still running and current status"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'
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
        
        # Check if artisan process is running
        run_cmd(client,
                'ps aux | grep "artisan media:associate" | grep -v grep',
                'Check if import process is running')
        
        # Get current variant media count
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) as current_variant_media '
                'FROM product_media '
                'WHERE product_variant_id IS NOT NULL;'
                '"',
                'Current Variant Media Count')
        
        # Get summary
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  COUNT(DISTINCT product_id) as products, '
                '  COUNT(DISTINCT product_variant_id) as variants, '
                '  COUNT(*) as total_records '
                'FROM product_media '
                'WHERE product_variant_id IS NOT NULL;'
                '"',
                'Summary Stats')
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
