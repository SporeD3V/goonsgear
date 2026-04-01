import paramiko  
c=paramiko.SSHClient()  
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())  
c.connect('91.98.230.33',1221,'spored3v','REDACTED_SET_GOONSGEAR_SSH_PASSWORD')  
print('PURPLE FLAKE:')  
