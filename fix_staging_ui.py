#!/usr/bin/env python3
"""Fix staging UI - check what's deployed and redeploy correct version"""
import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('91.98.230.33', 1221, 'spored3v', 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD')

print("="*70)
print("CHECKING DEPLOYED FILE")
print("="*70)

# Check if the new variant code exists in deployed file
stdin, stdout, stderr = client.exec_command(
    "grep -n 'variant_type' /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php | head -5"
)
print("Lines with variant_type in deployed file:")
print(stdout.read().decode())

# Check file size and modification time
stdin, stdout, stderr = client.exec_command(
    "ls -lh /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php"
)
print("\nFile info:")
print(stdout.read().decode())

print("\n" + "="*70)
print("RE-UPLOADING CORRECT FILE")
print("="*70)

sftp = client.open_sftp()
sftp.put(
    'c:/Projects/goonsgear/resources/views/shop/show.blade.php',
    '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php'
)
print("✓ File uploaded")
sftp.close()

print("\n" + "="*70)
print("CLEARING ALL CACHES")
print("="*70)

commands = [
    "php artisan view:clear",
    "php artisan cache:clear",
    "php artisan config:clear",
    "php artisan route:clear"
]

for cmd in commands:
    stdin, stdout, stderr = client.exec_command(
        f"cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && {cmd}"
    )
    print(f"✓ {cmd}")
    out = stdout.read().decode()
    if out:
        print(f"  {out}")

print("\n" + "="*70)
print("VERIFYING VARIANT TYPES IN DATABASE")
print("="*70)

stdin, stdout, stderr = client.exec_command(
    "mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "
    "\"SELECT v.id, v.name, v.variant_type FROM products p "
    "JOIN product_variants v ON v.product_id = p.id "
    "WHERE p.name LIKE '%All White MadFace%' "
    "ORDER BY v.position LIMIT 10;\""
)
print(stdout.read().decode())

print("\n" + "="*70)
print("✓ COMPLETE - Please test again:")
print("https://goonsgear.macaw.studio/shop/onyx-all-white-madface-shirt")
print("="*70)

client.close()
