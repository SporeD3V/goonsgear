<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncProductCategories extends Command
{
    protected $signature = 'products:sync-categories';

    protected $description = 'Sync product categories from WordPress to category_product pivot table';

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

            if (! $mapping) {
                continue;
            }

            // Get all category term IDs from WordPress
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

            if (! empty($categoryIds)) {
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
