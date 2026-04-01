#!/usr/bin/env python3
import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('91.98.230.33', 1221, 'spored3v', 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD')

print("="*70)
print("DIAGNOSING VIEW FILE DEPLOYMENT")
print("="*70)

# Check if new variant code exists
stdin, stdout, stderr = client.exec_command(
    "grep -n 'sizeVariants->isNotEmpty' /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php | head -3"
)
new_code = stdout.read().decode()
print("\nNew variant code (should show line numbers):")
print(new_code if new_code else "❌ NOT FOUND")

# Check if old variant code exists
stdin, stdout, stderr = client.exec_command(
    "grep -n 'variantsWithStockState as' /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php | head -5"
)
old_code = stdout.read().decode()
print("\nOld variant loop code:")
print(old_code if old_code else "✓ Not found")

# Get line count
stdin, stdout, stderr = client.exec_command(
    "wc -l /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php"
)
print("\nDeployed file lines:", stdout.read().decode().strip())

# Check local file
with open('resources/views/shop/show.blade.php', 'r', encoding='utf-8') as f:
    print(f"Local file lines: {len(f.readlines())}")

print("\n" + "="*70)
print("RE-UPLOADING FILE")
print("="*70)

# Upload fresh copy
sftp = client.open_sftp()
sftp.put(
    'resources/views/shop/show.blade.php',
    '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php'
)
print("✓ File uploaded")
sftp.close()

# Clear ALL caches
print("\nClearing caches...")
commands = [
    'php artisan view:clear',
    'php artisan cache:clear',
    'php artisan config:clear',
    'php artisan route:clear',
    'php artisan optimize:clear'
]

for cmd in commands:
    stdin, stdout, stderr = client.exec_command(
        f'cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && {cmd}'
    )
    print(f"  ✓ {cmd}")

# Verify upload
stdin, stdout, stderr = client.exec_command(
    "grep -c 'sizeVariants->isNotEmpty' /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php"
)
count = stdout.read().decode().strip()
print(f"\n✓ New code verification: Found {count} instances of new variant code")

print("\n" + "="*70)
print("DEPLOYMENT COMPLETE")
print("Please hard refresh (Ctrl+Shift+R) and test:")
print("https://goonsgear.macaw.studio/shop/snowgoons-purple-flake-shirt")
print("="*70)

client.close()
