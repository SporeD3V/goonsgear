#!/usr/bin/env python3
"""Remove duplicate product_media records"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD'

def run_cmd(client, cmd, label=None):
    if label:
        print(f"\n{'='*70}\n{label}\n{'='*70}")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=60)
    out = stdout.read().decode().strip()
    if out:
        print(out)
    return out

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        
        # Count duplicates before
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT COUNT(*) as duplicate_records FROM ("
                "  SELECT product_id, path, COUNT(*) as cnt "
                "  FROM product_media "
                "  GROUP BY product_id, path "
                "  HAVING cnt > 1"
                ") as dups;"
                "\"",
                'Duplicate Path Count (Before)')
        
        # Delete duplicates, keeping only the one with lowest ID (first inserted)
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "DELETE pm1 FROM product_media pm1 "
                "INNER JOIN product_media pm2 "
                "WHERE pm1.product_id = pm2.product_id "
                "AND pm1.path = pm2.path "
                "AND pm1.id > pm2.id;"
                "\"",
                'Deleting Duplicate Records')
        
        # Count after
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT COUNT(*) as total_media FROM product_media;"
                "\"",
                'Total Media Records (After Cleanup)')
        
        # Verify no duplicates remain
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT COUNT(*) as remaining_duplicates FROM ("
                "  SELECT product_id, path, COUNT(*) as cnt "
                "  FROM product_media "
                "  GROUP BY product_id, path "
                "  HAVING cnt > 1"
                ") as dups;"
                "\"",
                'Remaining Duplicates (Should be 0)')
        
        # Check DJ Crypt product after cleanup
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT "
                "  p.name, "
                "  COUNT(DISTINCT pm.path) as unique_images, "
                "  COUNT(pm.id) as total_records "
                "FROM products p "
                "JOIN product_media pm ON pm.product_id = p.id "
                "WHERE p.name LIKE '%Tales From The Crypt%' "
                "GROUP BY p.id;"
                "\"",
                'DJ Crypt Product (After Cleanup)')
        
        print("\n" + "="*70)
        print("✓ Duplicate cleanup complete")
        print("Test: https://goonsgear.macaw.studio/shop/dj-crypt-tales-from-the-crypt-vinyl")
        print("="*70)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
