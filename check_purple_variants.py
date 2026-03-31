#!/usr/bin/env python3
import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('91.98.230.33', 1221, 'spored3v', 'HNjp0cfsKOZ9PoJltRvU')

print("="*70)
print("CHECKING PURPLE FLAKE SHIRT VARIANT TYPES")
print("="*70)

stdin, stdout, stderr = client.exec_command(
    'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB '
    '-e "SELECT v.id, v.name, v.variant_type, v.is_active '
    'FROM products p '
    'JOIN product_variants v ON v.product_id = p.id '
    'WHERE p.slug = \'snowgoons-purple-flake-shirt\' '
    'ORDER BY v.id;"'
)
result = stdout.read().decode()
print(result)

if 'NULL' in result:
    print("\n❌ PROBLEM: Variants have NULL variant_type!")
    print("Running variant assignment command...")
    
    stdin, stdout, stderr = client.exec_command(
        'cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && '
        'php artisan variants:assign-types',
        timeout=120
    )
    print(stdout.read().decode())
    
    print("\nRechecking after assignment...")
    stdin, stdout, stderr = client.exec_command(
        'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB '
        '-e "SELECT v.name, v.variant_type '
        'FROM products p '
        'JOIN product_variants v ON v.product_id = p.id '
        'WHERE p.slug = \'snowgoons-purple-flake-shirt\';"'
    )
    print(stdout.read().decode())
else:
    print("\n✓ Variant types are set correctly")

# Clear caches one final time
stdin, stdout, stderr = client.exec_command(
    'cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && '
    'php artisan view:clear && php artisan cache:clear'
)
print("\n✓ Caches cleared")

print("\n" + "="*70)
print("Please test in NEW incognito window:")
print("https://goonsgear.macaw.studio/shop/snowgoons-purple-flake-shirt")
print("="*70)

client.close()
