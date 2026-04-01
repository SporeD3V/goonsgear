#!/usr/bin/env python3
import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('91.98.230.33', 1221, 'spored3v', 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD')

print("="*70)
print("CHECKING PURPLE FLAKE SHIRT VARIANTS")
print("="*70)

stdin, stdout, stderr = client.exec_command(
    "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB "
    "-e 'SELECT v.name, v.variant_type, v.is_active, v.stock_quantity "
    "FROM products p "
    "JOIN product_variants v ON v.product_id = p.id "
    "WHERE p.slug = \"snowgoons-purple-flake-shirt\" "
    "ORDER BY v.variant_type, v.position;'"
)
print(stdout.read().decode())

print("\n" + "="*70)
print("Stock status explains the grayed out appearance!")
print("Out of stock items are styled as disabled/grayed.")
print("="*70)

client.close()
