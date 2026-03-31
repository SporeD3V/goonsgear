#!/usr/bin/env python3
"""Verify variant detection against real product examples from screenshots"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'

# Products from screenshots
TEST_PRODUCTS = [
    "Onyx - Chest MadFace Shirt",  # Expected: Size (M,L,XL,XXL,XXXL,XXXXL) + Color (Black, Red)
    "Crypt Logo Short Beanie",     # Expected: Color only (Black, Gray, Red, Yellow)
    "Snowgoons - Patch SkiMask",   # Expected: Color only (Black, White)
    "Snowgoons - Black Snow Mesh Shorts"  # Expected: Size (XL, XXL) + Color (Black, White)
]

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
        
        print("\n" + "="*70)
        print("VARIANT DETECTION ACCURACY TEST")
        print("Testing against products from screenshots")
        print("="*70)
        
        for product_name in TEST_PRODUCTS:
            # Get product and its variants from NEW database
            result = run_cmd(client,
                f"mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
                f"SELECT p.name, v.name as variant, v.sku, v.variant_type, v.is_active, v.stock_quantity "
                f"FROM products p "
                f"JOIN product_variants v ON v.product_id = p.id "
                f"WHERE p.name LIKE '%{product_name}%' "
                f"ORDER BY v.position;"
                f"\"",
                f"\n🔍 {product_name}")
            
            if not result or "Empty set" in result:
                print(f"⚠️  Product not found in database")
            
            # Get WordPress variation data for comparison
            run_cmd(client,
                f"mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e \""
                f"SELECT p.post_title, pm.meta_key, pm.meta_value "
                f"FROM wp_posts p "
                f"LEFT JOIN wp_postmeta pm ON p.ID = pm.post_id "
                f"WHERE p.post_type = 'product_variation' "
                f"AND p.post_parent IN (SELECT ID FROM wp_posts WHERE post_title LIKE '%{product_name}%') "
                f"AND pm.meta_key LIKE 'attribute_%' "
                f"ORDER BY p.post_title, pm.meta_key "
                f"LIMIT 20;"
                f"\"",
                f"WordPress Attributes for {product_name}")
        
        print("\n" + "="*70)
        print("VERIFICATION COMPLETE")
        print("="*70)
        print("\nExpected Results (from screenshots):")
        print("1. Onyx - Chest MadFace: Size (M,L,XL,XXL,XXXL,XXXXL) + Color (Black, Red)")
        print("2. Crypt Logo Beanie: Color only (Black, Gray, Red, Yellow)")
        print("3. Snowgoons SkiMask: Color only (Black, White)")
        print("4. Black Snow Mesh Shorts: Size (XL, XXL) + Color (Black, White)")
        print("\nCheck above results to verify detection accuracy!")
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
