#!/usr/bin/env python3
"""Debug gallery path method on staging"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'

def run_cmd(client, cmd, label=None):
    if label:
        print(f"\n{'='*70}\n{label}\n{'='*70}")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=30)
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    if out:
        print(out)
    if err and 'Warning' not in err:
        print(f"Error: {err}")
    return out

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        
        # Check if getGalleryPath method exists
        run_cmd(client,
                "grep -A 5 'getGalleryPath' /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/app/Models/ProductMedia.php",
                'Checking if getGalleryPath() exists in ProductMedia.php')
        
        # Test the method via Tinker
        run_cmd(client,
                "cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && "
                "php artisan tinker --execute \""
                "\\$media = App\\\\Models\\\\ProductMedia::where('path', 'like', '%onyx-all-white%')->first(); "
                "if (\\$media) { "
                "echo 'Original path: ' . \\$media->path . PHP_EOL; "
                "echo 'Gallery path: ' . \\$media->getGalleryPath() . PHP_EOL; "
                "} else { "
                "echo 'Media not found'; "
                "}"
                "\"",
                'Testing getGalleryPath() on actual media')
        
        # Check the actual view file
        run_cmd(client,
                "grep -A 2 'primaryMediaUrl' /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php | head -5",
                'Checking show.blade.php for getGalleryPath() usage')
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
