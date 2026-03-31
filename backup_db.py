#!/usr/bin/env python3
"""Create backup directory and backup database"""
import paramiko
import sys
import time

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

def run_cmd(client, cmd, label=None, timeout=300):
    if label:
        print(f"\n{'='*70}\n{label}\n{'='*70}")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=timeout)
    out = stdout.read().decode().strip()
    if out:
        print(out)
    return out

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        
        timestamp = time.strftime('%Y%m%d_%H%M%S')
        
        # Create backup directory
        run_cmd(client, f'mkdir -p {BASE_PATH}/storage/backups', 'Create Backup Directory')
        
        # Backup database
        run_cmd(client,
                f'mysqldump -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB > '
                f'{BASE_PATH}/storage/backups/db_before_reimport_{timestamp}.sql',
                'Backup Database',
                timeout=600)
        
        # Verify
        result = run_cmd(client,
                f'ls -lh {BASE_PATH}/storage/backups/db_before_reimport_{timestamp}.sql',
                'Verify Backup')
        
        if result:
            print(f"\n✓ Backup created: db_before_reimport_{timestamp}.sql")
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
