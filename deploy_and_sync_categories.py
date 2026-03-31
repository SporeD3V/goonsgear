#!/usr/bin/env python3
"""Upload SyncProductCategories command and run it"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

COMMAND_CODE = """<?php

namespace App\\Console\\Commands;

use App\\Models\\Product;
use Illuminate\\Console\\Command;
use Illuminate\\Support\\Facades\\DB;

class SyncProductCategories extends Command
{
    protected $signature = 'products:sync-categories';
    protected $description = 'Sync product categories to pivot table';

    public function handle(): int
    {
        $this->info('Syncing product categories...');

        $legacy = DB::connection('legacy');
        $count = 0;
        $products = Product::all();

        foreach ($products as $product) {
            $mapping = DB::table('import_legacy_products')
                ->where('product_id', $product->id)
                ->first();

            if (!$mapping) {
                continue;
            }

            $catTerms = $legacy->table('wp_term_relationships')
                ->join('wp_term_taxonomy', 'wp_term_relationships.term_taxonomy_id', '=', 'wp_term_taxonomy.term_taxonomy_id')
                ->where('wp_term_relationships.object_id', $mapping->legacy_wp_post_id)
                ->where('wp_term_taxonomy.taxonomy', 'product_cat')
                ->pluck('wp_term_taxonomy.term_id');

            $categoryIds = [];
            foreach ($catTerms as $termId) {
                $catMapping = DB::table('import_legacy_categories')
                    ->where('legacy_term_id', $termId)
                    ->first();
                
                if ($catMapping) {
                    $categoryIds[] = $catMapping->category_id;
                }
            }

            if (!empty($categoryIds)) {
                $product->categories()->sync($categoryIds);
                $count++;
            }

            if ($count % 100 === 0) {
                $this->line("  ... {$count} products");
            }
        }

        $this->info("✓ Synced categories for {$count} products.");
        return 0;
    }
}
"""

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
        
        # Upload command
        sftp = client.open_sftp()
        remote_path = f'{BASE_PATH}/app/Console/Commands/SyncProductCategories.php'
        with sftp.open(remote_path, 'w') as f:
            f.write(COMMAND_CODE)
        sftp.close()
        print(f"✓ Uploaded command to {remote_path}\n")
        
        # Run command
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan products:sync-categories',
                'Running Category Sync',
                timeout=300)
        
        # Verify
        result = run_cmd(client,
                f'cd {BASE_PATH} && mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "'
                'SELECT '
                '  (SELECT COUNT(*) FROM category_product) as pivot_entries, '
                '  (SELECT COUNT(cp.product_id) FROM category_product cp JOIN categories c ON c.id = cp.category_id WHERE c.name LIKE \\\"%German%\\\") as german_products;'
                '"')
        
        print("\n" + "="*70)
        print("CATEGORY SYNC RESULTS")
        print("="*70)
        print(result)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
