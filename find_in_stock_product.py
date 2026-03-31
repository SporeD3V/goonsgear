import paramiko

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect('91.98.230.33', 1221, 'spored3v', 'HNjp0cfsKOZ9PoJltRvU')

print("Finding products with in-stock size/color variants...\n")

i,o,e = c.exec_command(
    'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB '
    '-e "SELECT p.name, p.slug, v.name as variant, v.variant_type, v.stock_quantity '
    'FROM products p '
    'JOIN product_variants v ON v.product_id = p.id '
    'WHERE v.variant_type IN (\'size\', \'color\') '
    'AND v.is_active = 1 '
    'AND v.stock_quantity > 0 '
    'GROUP BY p.id '
    'HAVING COUNT(*) > 1 '
    'LIMIT 5;"'
)
print(o.read().decode())

c.close()
