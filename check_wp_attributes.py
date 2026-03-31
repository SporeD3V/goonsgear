#!/usr/bin/env python3
"""Check WordPress product attributes structure"""
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
    if out:
        print(out)
    return out

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        
        # Check WP product attributes taxonomy
        run_cmd(client,
                "mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e \""
                "SELECT taxonomy, COUNT(*) as count "
                "FROM wp_term_taxonomy "
                "WHERE taxonomy LIKE 'pa_%' "
                "GROUP BY taxonomy;"
                "\"",
                'WooCommerce Product Attributes')
        
        # Check attribute terms (sizes, colors, etc)
        run_cmd(client,
                "mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e \""
                "SELECT tt.taxonomy, t.name, COUNT(DISTINCT tr.object_id) as products "
                "FROM wp_terms t "
                "JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id "
                "JOIN wp_term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id "
                "WHERE tt.taxonomy LIKE 'pa_%' "
                "GROUP BY tt.taxonomy, t.name "
                "ORDER BY tt.taxonomy, products DESC "
                "LIMIT 50;"
                "\"",
                'Attribute Terms and Usage')
        
        # Check product meta for variations
        run_cmd(client,
                "mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e \""
                "SELECT meta_key, COUNT(*) as count "
                "FROM wp_postmeta "
                "WHERE meta_key LIKE 'attribute_%' "
                "GROUP BY meta_key "
                "ORDER BY count DESC "
                "LIMIT 20;"
                "\"",
                'Product Attribute Meta Keys')
        
        # Sample variation with attributes
        run_cmd(client,
                "mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e \""
                "SELECT p.post_title, pm.meta_key, pm.meta_value "
                "FROM wp_posts p "
                "JOIN wp_postmeta pm ON p.ID = pm.post_id "
                "WHERE p.post_type = 'product_variation' "
                "AND pm.meta_key LIKE 'attribute_%' "
                "LIMIT 30;"
                "\"",
                'Sample Variation Attributes')
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
