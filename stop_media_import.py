#!/usr/bin/env python3
"""Stop the inefficient media import"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'

def run_cmd(client, cmd, label=None):
    if label:
        print(f"\n{label}")
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
        
        # Kill the process
        run_cmd(client,
                'pkill -f "artisan media:associate-legacy"',
                'Stopping inefficient media import...')
        
        # Verify stopped
        result = run_cmd(client,
                'ps aux | grep "artisan media:associate-legacy" | grep -v grep',
                'Checking if stopped...')
        
        if result:
            print("⚠️ Still running")
        else:
            print("✓ Media import stopped")
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
