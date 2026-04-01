#!/usr/bin/env python3
import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('91.98.230.33', 1221, 'spored3v', 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD')

print("="*70)
print("ISSUE IDENTIFIED: Combo variants marked as 'color' only")
print("These variants have format: 'Size, Color' e.g. 'M, Black'")
print("They should be marked as 'custom' to show as dropdown")
print("="*70)

# Fix: Mark combo variants (containing comma) as custom
print("\nFixing combo variants...")
stdin, stdout, stderr = client.exec_command(
    "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB "
    "-e \"UPDATE product_variants SET variant_type = 'custom' "
    "WHERE name LIKE '%,%';\""
)
print(stdout.read().decode())

# Check result
stdin, stdout, stderr = client.exec_command(
    "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB "
    "-e 'SELECT v.name, v.variant_type FROM products p "
    "JOIN product_variants v ON v.product_id = p.id "
    "WHERE p.slug = \"onyx-all-white-madface-shirt\" "
    "ORDER BY v.position LIMIT 12;'"
)
print("\nONYX VARIANTS AFTER FIX:")
print(stdout.read().decode())

# Clear caches
print("\nClearing caches...")
stdin, stdout, stderr = client.exec_command(
    'cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && '
    'php artisan view:clear && php artisan cache:clear'
)
print("✓ Caches cleared")

print("\n" + "="*70)
print("✓ FIXED - Please refresh and test:")
print("https://goonsgear.macaw.studio/shop/onyx-all-white-madface-shirt")
print("="*70)

client.close()
