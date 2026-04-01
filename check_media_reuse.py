#!/usr/bin/env python3
"""Check if media import is reusing existing files or re-converting"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD'
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
        
        print("="*70)
        print("MEDIA IMPORT ANALYSIS")
        print("="*70)
        
        # Check existing AVIF files on filesystem
        run_cmd(client,
                f'find {BASE_PATH}/storage/app/public/products -name "*.avif" | wc -l',
                'Existing AVIF files on filesystem')
        
        # Check product_media database records
        run_cmd(client,
                f'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) as db_records FROM product_media;'
                '"',
                'Current product_media DB records')
        
        # Check log for conversion vs reuse
        run_cmd(client,
                'tail -50 /tmp/media_import.log | grep -E "(Converting|Skipping|exists)" | tail -20',
                'Recent import log activity')
        
        print("\n" + "="*70)
        print("EXPLANATION")
        print("="*70)
        print("""
When we did the clean re-import, we truncated these tables:
  - product_media (DATABASE RECORDS deleted)
  - products
  - product_variants
  
What WASN'T deleted:
  - Actual image files in storage/app/public/products/ (STILL EXIST)
  
What media:associate-legacy does:
  1. Reads WordPress attachment metadata
  2. Checks if converted AVIF/WebP files already exist
  3. If yes: REUSES existing file (fast)
  4. If no: Converts image (slow)
  5. Creates new product_media DB record (always needed)
  
So it should be MUCH FASTER this time - mostly just recreating
database records and linking to existing converted files.
        """)
        print("="*70)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
