#!/usr/bin/env python3
"""Analyze variant patterns to design better UX"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'

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
        
        # Check variant names to identify patterns
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT p.name as product, v.name as variant_name, v.sku, v.is_active, v.stock_quantity "
                "FROM products p "
                "JOIN product_variants v ON v.product_id = p.id "
                "WHERE p.id IN (SELECT product_id FROM product_variants GROUP BY product_id HAVING COUNT(*) > 1) "
                "ORDER BY p.id, v.position "
                "LIMIT 30;"
                "\"",
                'Sample Products with Multiple Variants')
        
        # Check for size patterns
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT v.name, COUNT(*) as count "
                "FROM product_variants v "
                "WHERE v.name REGEXP '^(S|M|L|XL|XXL|XXXL|Small|Medium|Large)$' "
                "GROUP BY v.name "
                "ORDER BY count DESC;"
                "\"",
                'Common Size Variant Names')
        
        # Check for color patterns
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT v.name, COUNT(*) as count "
                "FROM product_variants v "
                "WHERE v.name REGEXP '(Black|White|Red|Blue|Green|Yellow|Gray|Grey|Navy|Purple|Orange|Pink)' "
                "GROUP BY v.name "
                "ORDER BY count DESC "
                "LIMIT 20;"
                "\"",
                'Common Color Variant Names')
        
        # Check products with both size and color variants
        run_cmd(client,
                "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                "SELECT p.name, GROUP_CONCAT(v.name ORDER BY v.position) as variants "
                "FROM products p "
                "JOIN product_variants v ON v.product_id = p.id "
                "WHERE p.id IN (59, 70, 185, 1000) "
                "GROUP BY p.id;"
                "\"",
                'Sample Specific Products')
        
        print("\n" + "="*70)
        print("DESIGN RECOMMENDATIONS")
        print("="*70)
        print("\n1. Add 'variant_type' field: 'size', 'color', 'style', 'custom'")
        print("2. Frontend UX:")
        print("   - Sizes: Compact button group (S/M/L/XL)")
        print("   - Colors: Swatches with names")
        print("   - Mixed: Two-step selection (color first, then size)")
        print("   - Custom: Current dropdown")
        print("\n3. Show stock status per variant (out of stock grayed out)")
        print("4. Admin: Easy variant type assignment")
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
