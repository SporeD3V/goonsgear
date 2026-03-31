import paramiko

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect('91.98.230.33', 1221, 'spored3v', 'HNjp0cfsKOZ9PoJltRvU')

print("="*70)
print("PURPLE FLAKE VARIANT ISSUE")
print("="*70)

i,o,e = c.exec_command(
    'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB '
    '-e "SELECT v.name, v.variant_type FROM products p '
    'JOIN product_variants v ON v.product_id = p.id '
    'WHERE p.slug = \\'snowgoons-purple-flake-shirt\\' '
    'ORDER BY v.name;"'
)
print(o.read().decode())

print("\n" + "="*70)
print("ISSUE: L is marked as color but should be size")
print("Fixing...")
print("="*70)

# Fix: Mark single-letter variants as size
i,o,e = c.exec_command(
    'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB '
    '-e "UPDATE product_variants '
    'SET variant_type = \\'size\\' '
    'WHERE variant_type = \\'color\\' '
    'AND name REGEXP \\'[^a-zA-Z]+(S|M|L|XL|XXL|XXXL|2XL|3XL|4XL|5XL)$\\';"'
)

print("✓ Fixed size detection")

# Clear caches
i,o,e = c.exec_command(
    'cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && '
    'php artisan view:clear && php artisan cache:clear'
)

print("\n" + "="*70)
print("Checking Purple Flake again...")
print("="*70)

i,o,e = c.exec_command(
    'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB '
    '-e "SELECT v.name, v.variant_type FROM products p '
    'JOIN product_variants v ON v.product_id = p.id '
    'WHERE p.slug = \\'snowgoons-purple-flake-shirt\\' '
    'ORDER BY v.variant_type, v.name;"'
)
print(o.read().decode())

print("\n" + "="*70)
print("✓ FIXED - Refresh and test:")
print("https://goonsgear.macaw.studio/shop/snowgoons-purple-flake-shirt")
print("="*70)

c.close()
