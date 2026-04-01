import os
import paramiko

password = os.environ.get('GOONSGEAR_SSH_PASSWORD', '')
if password == '':
	raise RuntimeError('Missing GOONSGEAR_SSH_PASSWORD environment variable.')

c=paramiko.SSHClient()  
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())  
c.connect('91.98.230.33',1221,'spored3v',password)  
s=c.open_sftp()  
s.put('resources/views/shop/show.blade.php','/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/resources/views/shop/show.blade.php')  
s.close()  
c.exec_command('rm -rf /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/storage/framework/views/*')  
i,o,e=c.exec_command('cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio && php artisan optimize:clear')  
print('Combo variant UI deployed')  
c.close()  
