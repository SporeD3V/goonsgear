#!/usr/bin/env python3
"""Test category fix on staging"""
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
        print(f"✓ Connected\n")
        
        print("="*70)
        print("CATEGORY FIX TEST")
        print("="*70)
        print("\nFix applied: Changed whereHas('primaryCategory') to whereHas('categories')")
        print("This allows products to be found by ANY of their categories, not just primary.\n")
        
        # Test URLs
        print("Test these URLs on staging:")
        print("1. https://goonsgear.macaw.studio/shop?category=germanhiphop")
        print("   Should show 82 products (not 0)")
        print("\n2. https://goonsgear.macaw.studio/shop?category=onyx")
        print("   Should show 93 products (not 1)")
        print("\n3. https://goonsgear.macaw.studio/shop?category=vinyl")
        print("   Should show all vinyl products")
        
        # Verify database state
        run_cmd(client,
                'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  c.name, '
                '  c.slug, '
                '  COUNT(DISTINCT cp.product_id) as products '
                'FROM categories c '
                'JOIN category_product cp ON cp.category_id = c.id '
                'GROUP BY c.id '
                'ORDER BY products DESC;'
                '"',
                'Expected Product Count per Category (from DB)')
        
        print("\n" + "="*70)
        print("✓ Fix deployed - Please test frontend URLs above")
        print("="*70)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        return 1

if __name__ == '__main__':
    sys.exit(main())
