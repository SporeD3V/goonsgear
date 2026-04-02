#!/usr/bin/env python3
"""Create backup directory and backup database"""
import paramiko
import sys
import time

from staging_env import SSH_HOST as HOST, SSH_PORT as PORT, SSH_USER as USER, SSH_PASSWORD as PASSWORD, BASE_PATH
from staging_env import DB_DATABASE, DB_USERNAME, DB_PASSWORD

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
                f'mysqldump -u {DB_USERNAME} -p{DB_PASSWORD} {DB_DATABASE} > '
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
