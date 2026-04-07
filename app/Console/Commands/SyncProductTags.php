<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncProductTags extends Command
{
    protected $signature = 'products:sync-tags';

    protected $description = 'Sync product tags from WordPress to product_tag pivot table';

    public function handle(): int
    {
        $this->info('Syncing product tags...');

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

            // Get all term IDs for this product from both product_tag and product_cat taxonomies.
            // Category terms converted to tags during import will have entries in import_legacy_tags,
            // so they are automatically included via the mapping lookup below.
            $termIds = $legacy->table('wp_term_relationships')
                ->join('wp_term_taxonomy', 'wp_term_relationships.term_taxonomy_id', '=', 'wp_term_taxonomy.term_taxonomy_id')
                ->where('wp_term_relationships.object_id', $mapping->legacy_wp_post_id)
                ->whereIn('wp_term_taxonomy.taxonomy', ['product_tag', 'product_cat'])
                ->pluck('wp_term_taxonomy.term_id');

            $tagIds = [];
            foreach ($termIds as $termId) {
                $tagMapping = DB::table('import_legacy_tags')
                    ->where('legacy_term_id', $termId)
                    ->first();

                if ($tagMapping) {
                    $tagIds[] = $tagMapping->tag_id;
                }
            }

            if (! empty($tagIds)) {
                $product->tags()->syncWithoutDetaching($tagIds);
                $count++;
            }

            if ($count % 100 === 0 && $count > 0) {
                $this->line("  ... {$count} products");
            }
        }

        $this->info("✓ Synced tags for {$count} products.");

        return self::SUCCESS;
    }
}
