#!/usr/bin/env python3
"""Manually populate category_product pivot table from WP data"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

def run_cmd(client, cmd, label=None, timeout=300):
    if label:
        print(f"\n{'='*70}\n{label}\n{'='*70}")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=timeout)
    out = stdout.read().decode().strip()
    if out:
        print(out)
    return out

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        print(f"✓ Connected\n")
        
        # Run Artisan command to sync categories
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan tinker --execute="'
                '$count = 0; '
                '$products = \\App\\Models\\Product::all(); '
                'foreach($products as $product) {{ '
                '  $mapping = DB::table(\\\"import_legacy_products\\\")->where(\\\"product_id\\\", $product->id)->first(); '
                '  if(!$mapping) continue; '
                '  $legacy = DB::connection(\\\"legacy\\\"); '
                '  $catTerms = $legacy->table(\\\"wp_term_relationships\\\")'
                '    ->join(\\\"wp_term_taxonomy\\\", \\\"wp_term_relationships.term_taxonomy_id\\\", \\\"=\\\", \\\"wp_term_taxonomy.term_taxonomy_id\\\")'
                '    ->where(\\\"wp_term_relationships.object_id\\\", $mapping->legacy_wp_post_id)'
                '    ->where(\\\"wp_term_taxonomy.taxonomy\\\", \\\"product_cat\\\")'
                '    ->pluck(\\\"wp_term_taxonomy.term_id\\\"); '
                '  $categoryIds = []; '
                '  foreach($catTerms as $termId) {{ '
                '    $catMapping = DB::table(\\\"import_legacy_categories\\\")->where(\\\"legacy_term_id\\\", $termId)->first(); '
                '    if($catMapping) {{ '
                '      $categoryIds[] = $catMapping->category_id; '
                '    }} '
                '  }} '
                '  if(!empty($categoryIds)) {{ '
                '    $product->categories()->sync($categoryIds); '
                '    $count++; '
                '  }} '
                '}} '
                'echo \\\"Synced categories for \\\" . $count . \\\" products\\\"'
                ';"',
                'Syncing Categories to Pivot Table',
                timeout=600)
        
        # Verify results
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT COUNT(*) as pivot_entries FROM category_product;'
                '"',
                'Verify Category Pivot Entries')
        
        # Check German Hip Hop category
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  c.name, '
                '  COUNT(cp.product_id) as product_count '
                'FROM categories c '
                'LEFT JOIN category_product cp ON cp.category_id = c.id '
                'WHERE c.slug LIKE \\\"%german%\\\" '
                'GROUP BY c.id;'
                '"',
                'German Hip Hop Category Product Count')
        
        # Sample products with multiple categories
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  p.id, '
                '  p.name, '
                '  COUNT(cp.category_id) as category_count '
                'FROM products p '
                'JOIN category_product cp ON cp.product_id = p.id '
                'GROUP BY p.id '
                'HAVING category_count > 1 '
                'LIMIT 10;'
                '"',
                'Sample Products with Multiple Categories')
        
        print("\n" + "="*70)
        print("✓ Category pivot fix completed")
        print("="*70)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
