import paramiko

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect('91.98.230.33', 1221, 'spored3v', 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD')

print("="*70)
print("CHECKING ONYX ALL WHITE MADFACE VARIANTS")
print("="*70)

# Check variant types for this product
stdin, stdout, stderr = c.exec_command(
    'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e '
    '"SELECT v.id, v.name, v.variant_type FROM products p '
    'JOIN product_variants v ON v.product_id = p.id '
    'WHERE p.slug = \'onyx-all-white-madface-shirt\' '
    'ORDER BY v.position LIMIT 12;"'
)
print(stdout.read().decode())

print("\n" + "="*70)
print("CHECKING DEPLOYED VIEW FILE (variant_type lines)")
print("="*70)

stdin, stdout, stderr = c.exec_command(
    'grep -n "variant_type" /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php | head -10'
)
print(stdout.read().decode())

print("\n" + "="*70)
print("VERIFYING VIEW FILE SIZE")
print("="*70)

stdin, stdout, stderr = c.exec_command(
    'wc -l /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php'
)
print(f"Deployed file: {stdout.read().decode()}")

stdin, stdout, stderr = c.exec_command(
    'wc -l /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php'
)

# Check local file
with open('c:/Projects/goonsgear/resources/views/shop/show.blade.php', 'r', encoding='utf-8') as f:
    local_lines = len(f.readlines())
print(f"Local file: {local_lines} lines")

print("\n" + "="*70)
print("CHECKING FOR OLD VARIANT CODE")
print("="*70)

stdin, stdout, stderr = c.exec_command(
    'grep -A5 "Filter gallery by variant" /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php'
)
old_code = stdout.read().decode()
if old_code:
    print("⚠️  OLD CODE FOUND:")
    print(old_code)
else:
    print("✓ No old variant filter code found")

c.close()
