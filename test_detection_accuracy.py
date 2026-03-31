#!/usr/bin/env python3
"""Test variant detection accuracy on staging"""
import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('91.98.230.33', 1221, 'spored3v', 'HNjp0cfsKOZ9PoJltRvU')

# Upload updated view
sftp = client.open_sftp()
sftp.put(
    'c:/Projects/goonsgear/resources/views/shop/show.blade.php',
    '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php'
)
sftp.close()

print("="*70)
print("CLEARING CACHES")
print("="*70)
stdin, stdout, stderr = client.exec_command(
    "cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && php artisan view:clear"
)
print(stdout.read().decode())

print("\n" + "="*70)
print("RUNNING VARIANT ASSIGNMENT (ACTUAL - NOT DRY RUN)")
print("="*70)
stdin, stdout, stderr = client.exec_command(
    "cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && php artisan variants:assign-types",
    timeout=120
)
print(stdout.read().decode())

print("\n" + "="*70)
print("TESTING SPECIFIC PRODUCTS FROM SCREENSHOTS")
print("="*70)

products = [
    ("Onyx%Chest%MadFace%Shirt", "Expected: Size (M,L,XL,XXL,XXXL,XXXXL) + Color (Black, Red)"),
    ("Crypt%Logo%Short%Beanie", "Expected: Color only (Black, Gray, Red, Yellow)"),
    ("%Patch%SkiMask", "Expected: Color only (Black, White)"),
    ("%Black%Snow%Mesh%Shorts", "Expected: Size (XL, XXL) + Color (Black, White)")
]

for search, expected in products:
    print(f"\n{'='*70}")
    print(f"Product: {search.replace('%', ' ')}")
    print(f"{expected}")
    print("="*70)
    
    stdin, stdout, stderr = client.exec_command(
        f"mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e \""
        f"SELECT v.name as variant, v.variant_type, v.is_active, v.stock_quantity "
        f"FROM products p "
        f"JOIN product_variants v ON v.product_id = p.id "
        f"WHERE p.name LIKE '{search}' "
        f"ORDER BY "
        f"  CASE v.variant_type WHEN 'size' THEN 1 WHEN 'color' THEN 2 ELSE 3 END, "
        f"  v.position "
        f"LIMIT 20;"
        f"\""
    )
    print(stdout.read().decode())

print("\n" + "="*70)
print("✓ VERIFICATION COMPLETE")
print("="*70)

client.close()
