#!/usr/bin/env python3
"""Clarify media counts - DB records vs filesystem files"""
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
        
        # DB records
        db_total = run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) FROM product_media;" | tail -1')
        
        # Filesystem files
        avif_total = run_cmd(client,
                f'find {BASE_PATH}/storage/app/public/products -name "*.avif" | wc -l')
        
        avif_main = run_cmd(client,
                f'find {BASE_PATH}/storage/app/public/products -name "*.avif" '
                f'| grep -v "thumbnail\\|gallery\\|hero" | wc -l')
        
        avif_thumb = run_cmd(client,
                f'find {BASE_PATH}/storage/app/public/products -name "*-thumbnail-*.avif" | wc -l')
        
        avif_gallery = run_cmd(client,
                f'find {BASE_PATH}/storage/app/public/products -name "*-gallery-*.avif" | wc -l')
        
        avif_hero = run_cmd(client,
                f'find {BASE_PATH}/storage/app/public/products -name "*-hero-*.avif" | wc -l')
        
        print("\n" + "="*70)
        print("MEDIA COUNTS EXPLAINED")
        print("="*70)
        print(f"""
DATABASE RECORDS (product_media table):
  {db_total} records = associations between products/variants and images
  
FILESYSTEM FILES (storage/app/public/products/):
  {avif_total} total AVIF files
  
  Breakdown:
    {avif_main} main files (full size)
    {avif_thumb} thumbnail variants (-thumbnail-200x200.avif)
    {avif_gallery} gallery variants (-gallery-600x600.avif)
    {avif_hero} hero variants (-hero-1200x600.avif)
  
CALCULATION:
  {avif_main} source images × 4 variants = {int(avif_main) * 4} AVIF files
  
WHY MISMATCH:
  - DB was truncated during clean re-import
  - Files remained on disk (not deleted)
  - Current import creates DB records for existing files
  - Import is {db_total}/{avif_main} = {int(db_total)/int(avif_main)*100:.1f}% complete
        """)
        print("="*70)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
