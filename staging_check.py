import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('91.98.230.33', port=1221, username='spored3v', password='REDACTED_SET_GOONSGEAR_SSH_PASSWORD', timeout=30)

base = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

# Check current state
cmd = (
    f"cd {base} && php artisan tinker --execute="
    "'dump([\"active\" => \\App\\Models\\Product::where(\"status\",\"active\")->count(),"
    " \"with_media\" => \\App\\Models\\Product::where(\"status\",\"active\")->whereHas(\"media\")->count(),"
    " \"total_media\" => \\App\\Models\\ProductMedia::count()]);'"
)
stdin, stdout, stderr = client.exec_command(cmd, timeout=30)
print("=== Current State ===")
print(stdout.read().decode())
err = stderr.read().decode()
if err:
    print("STDERR:", err)

client.close()
