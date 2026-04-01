#!/usr/bin/env python3
"""Check why images aren't showing on shop"""
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
        
        # Check product_media table
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) as total_media FROM product_media;'
                '"',
                'Product Media Count')
        
        # Check if import is running
        run_cmd(client,
                'ps aux | grep "artisan media:associate-legacy" | grep -v grep',
                'Media Import Process Status')
        
        # Sample products with media
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  p.id, '
                '  p.name, '
                '  COUNT(pm.id) as media_count '
                'FROM products p '
                'LEFT JOIN product_media pm ON pm.product_id = p.id '
                'GROUP BY p.id '
                'ORDER BY p.id '
                'LIMIT 10;'
                '"',
                'Sample Products with Media Count')
        
        # Check for any errors in media import log
        run_cmd(client,
                'tail -30 /tmp/media_import.log 2>/dev/null || echo "No log file"',
                'Recent Media Import Log')
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
