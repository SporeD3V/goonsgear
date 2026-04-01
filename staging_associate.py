import paramiko
import time

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('91.98.230.33', port=1221, username='spored3v', password='REDACTED_SET_GOONSGEAR_SSH_PASSWORD', timeout=30)

base = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'
legacy_root = f'{base}/storage/app/legacy-uploads/uploads_extracted'

# Run full association (live, not dry-run)
cmd = (
    f"cd {base} && php artisan media:associate-legacy"
    f" --legacy-root='{legacy_root}'"
    f" --clear-existing"
    f" --no-interaction 2>&1"
)

print("=== Running Full Legacy Media Association ===")
print(f"Command: {cmd}")
print()

# Use a transport channel for streaming output
transport = client.get_transport()
channel = transport.open_session()
channel.settimeout(7200)  # 2 hour timeout
channel.exec_command(cmd)

# Read output in chunks
output = []
while True:
    if channel.recv_ready():
        chunk = channel.recv(4096).decode('utf-8', errors='replace')
        output.append(chunk)
        print(chunk, end='', flush=True)
    if channel.exit_status_ready():
        # Drain remaining output
        while channel.recv_ready():
            chunk = channel.recv(4096).decode('utf-8', errors='replace')
            output.append(chunk)
            print(chunk, end='', flush=True)
        break
    time.sleep(0.5)

exit_code = channel.recv_exit_status()
print(f"\n\n=== Exit Code: {exit_code} ===")

client.close()
