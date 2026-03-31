#!/usr/bin/env python3
import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('91.98.230.33', 1221, 'spored3v', 'HNjp0cfsKOZ9PoJltRvU')

print("="*70)
print("CHECKING ONYX ALL WHITE MADFACE VARIANTS")
print("="*70)

stdin, stdout, stderr = client.exec_command(
    "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB "
    "-e 'SELECT v.name, v.variant_type FROM products p "
    "JOIN product_variants v ON v.product_id = p.id "
    "WHERE p.slug = \"onyx-all-white-madface-shirt\" "
    "ORDER BY v.position LIMIT 12;'"
)
result = stdout.read().decode()
print(result)

if 'NULL' in result or not result.strip():
    print("\n⚠️  Variants have NULL types! Running assignment again...")
    stdin, stdout, stderr = client.exec_command(
        'cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && '
        'php artisan variants:assign-types',
        timeout=120
    )
    print(stdout.read().decode())
else:
    print("\n✓ Variant types are set")

client.close()
