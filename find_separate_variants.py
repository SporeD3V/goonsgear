#!/usr/bin/env python3
import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('91.98.230.33', 1221, 'spored3v', 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD')

print("="*70)
print("FINDING PRODUCTS WITH SEPARATE SIZE/COLOR VARIANTS")
print("="*70)

# Find products where variants are just sizes (no commas)
stdin, stdout, stderr = client.exec_command(
    "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB "
    "-e \"SELECT p.name, COUNT(*) as variants, "
    "GROUP_CONCAT(DISTINCT v.variant_type) as types "
    "FROM products p "
    "JOIN product_variants v ON v.product_id = p.id "
    "WHERE v.variant_type IN ('size', 'color') "
    "GROUP BY p.id "
    "HAVING COUNT(DISTINCT v.variant_type) = 2 "
    "LIMIT 10;\""
)
print("Products with BOTH size AND color (separate):")
print(stdout.read().decode())

# Find products with size only
stdin, stdout, stderr = client.exec_command(
    "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB "
    "-e \"SELECT p.name, COUNT(*) as size_variants "
    "FROM products p "
    "JOIN product_variants v ON v.product_id = p.id "
    "WHERE v.variant_type = 'size' "
    "GROUP BY p.id "
    "LIMIT 5;\""
)
print("\nProducts with size variants only:")
print(stdout.read().decode())

# Find products with color only  
stdin, stdout, stderr = client.exec_command(
    "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB "
    "-e \"SELECT p.name, p.slug, COUNT(*) as color_variants "
    "FROM products p "
    "JOIN product_variants v ON v.product_id = p.id "
    "WHERE v.variant_type = 'color' "
    "GROUP BY p.id "
    "LIMIT 5;\""
)
print("\nProducts with color variants only:")
print(stdout.read().decode())

client.close()
