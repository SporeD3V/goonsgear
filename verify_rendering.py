#!/usr/bin/env python3
import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('91.98.230.33', 1221, 'spored3v', 'HNjp0cfsKOZ9PoJltRvU')

# Check compiled views
print("Checking compiled views...")
stdin, stdout, stderr = client.exec_command(
    'rm -rf /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/storage/framework/views/*'
)
print("Deleted compiled views")

# Clear all Laravel caches again
stdin, stdout, stderr = client.exec_command(
    'cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && '
    'php artisan view:clear && '
    'php artisan cache:clear && '
    'rm -rf bootstrap/cache/*.php'
)
print("Cleared Laravel caches and bootstrap cache")

# Fetch actual page and check for new code
print("\nFetching actual page HTML...")
stdin, stdout, stderr = client.exec_command(
    'curl -s "https://goonsgear.macaw.studio/shop/snowgoons-purple-flake-shirt" | grep -o "sizeVariants\\|Available Variants" | head -5'
)
output = stdout.read().decode().strip()
print(f"Page contains: {output if output else 'OLD UI (Available Variants)'}")

# Check view file one more time
stdin, stdout, stderr = client.exec_command(
    'head -280 /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php | tail -10'
)
print("\nView file around line 275:")
print(stdout.read().decode())

print("\n" + "="*70)
print("FINAL CACHE CLEAR")
print("="*70)
stdin, stdout, stderr = client.exec_command(
    'cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && php artisan optimize:clear'
)
print(stdout.read().decode())

client.close()
print("\nPlease try again in a new incognito window")
