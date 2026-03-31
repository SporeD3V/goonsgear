import paramiko
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('91.98.230.33', port=1221, username='spored3v', password='HNjp0cfsKOZ9PoJltRvU')

stdin, stdout, stderr = client.exec_command("grep -n 'getGalleryPath' /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/app/Models/ProductMedia.php")
print("=== ProductMedia.php check ===")
print(stdout.read().decode())

stdin, stdout, stderr = client.exec_command("grep 'getGalleryPath' /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php | head -3")
print("\n=== show.blade.php check ===")
print(stdout.read().decode())

client.close()
