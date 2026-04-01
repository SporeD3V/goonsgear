#!/usr/bin/env python3
"""Check for duplicate images on DJ Crypt product"""
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
        
        # Find DJ Crypt product
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT p.id, p.name, p.slug FROM products p "
                "WHERE p.name LIKE '%DJ Crypt%' OR p.name LIKE '%Tales From%' "
                "LIMIT 5;"
                "\"",
                'Find DJ Crypt Product')
        
        # Get all media for this product
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT "
                "  pm.id, "
                "  pm.path, "
                "  pm.position, "
                "  pm.is_primary, "
                "  pm.product_variant_id "
                "FROM products p "
                "JOIN product_media pm ON pm.product_id = p.id "
                "WHERE p.name LIKE '%Tales From The Crypt%' "
                "ORDER BY pm.path, pm.position;"
                "\"",
                'All Media for DJ Crypt Product')
        
        # Check for duplicate paths
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT "
                "  pm.path, "
                "  COUNT(*) as count, "
                "  GROUP_CONCAT(pm.id) as media_ids, "
                "  GROUP_CONCAT(pm.product_variant_id) as variant_ids "
                "FROM products p "
                "JOIN product_media pm ON pm.product_id = p.id "
                "WHERE p.name LIKE '%Tales From The Crypt%' "
                "GROUP BY pm.path "
                "HAVING count > 1;"
                "\"",
                'Duplicate Image Paths')
        
        # Check all products with duplicates
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT "
                "  p.id, "
                "  p.name, "
                "  pm.path, "
                "  COUNT(*) as duplicate_count "
                "FROM products p "
                "JOIN product_media pm ON pm.product_id = p.id "
                "GROUP BY p.id, pm.path "
                "HAVING duplicate_count > 1 "
                "ORDER BY duplicate_count DESC "
                "LIMIT 10;"
                "\"",
                'Top 10 Products with Duplicate Images')
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
