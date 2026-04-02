<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Genre category slugs to convert to tags.
     *
     * @var array<string, string>
     */
    private array $genres = [
        'germanhiphop' => 'German Hip Hop',
        'indie-hip-hop' => 'Indie Hip Hop',
    ];

    /**
     * Desired sort_order for top-level categories.
     *
     * @var array<string, int>
     */
    private array $sortOrder = [
        'sale' => 1,
        'music' => 2,
        'wear' => 3,
        'accessories' => 4,
    ];

    public function up(): void
    {
        // Convert genre categories to genre tags
        foreach ($this->genres as $slug => $name) {
            $category = DB::table('categories')->where('slug', $slug)->first();

            if ($category === null) {
                continue;
            }

            $existingTag = DB::table('tags')->where('slug', $slug)->where('type', 'genre')->first();

            if ($existingTag !== null) {
                $tagId = $existingTag->id;
            } else {
                $tagId = DB::table('tags')->insertGetId([
                    'name' => $name,
                    'slug' => $slug,
                    'type' => 'genre',
                    'is_active' => true,
                    'description' => $category->description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Copy product associations from category_product to product_tag
            $productIds = DB::table('category_product')
                ->where('category_id', $category->id)
                ->pluck('product_id');

            foreach ($productIds as $productId) {
                DB::table('product_tag')->insertOrIgnore([
                    'product_id' => $productId,
                    'tag_id' => $tagId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Update import_legacy_categories → import_legacy_tags mapping
            $legacyMapping = DB::table('import_legacy_categories')
                ->where('category_id', $category->id)
                ->first();

            if ($legacyMapping !== null) {
                DB::table('import_legacy_tags')->insertOrIgnore([
                    'legacy_term_id' => $legacyMapping->legacy_term_id,
                    'tag_id' => $tagId,
                    'synced_at' => $legacyMapping->synced_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('import_legacy_categories')
                    ->where('id', $legacyMapping->id)
                    ->delete();
            }

            DB::table('category_product')
                ->where('category_id', $category->id)
                ->delete();

            DB::table('categories')
                ->where('id', $category->id)
                ->delete();
        }

        // Set sort_order for top-level categories
        foreach ($this->sortOrder as $slug => $order) {
            DB::table('categories')
                ->where('slug', $slug)
                ->update(['sort_order' => $order]);
        }

        // Push remaining top-level categories after the specified ones
        DB::table('categories')
            ->whereNull('parent_id')
            ->where('sort_order', 0)
            ->where('is_active', true)
            ->update(['sort_order' => 10]);
    }

    public function down(): void
    {
        // Reset sort_order
        DB::table('categories')
            ->whereNull('parent_id')
            ->update(['sort_order' => 0]);

        // Recreate genre categories from tags
        foreach ($this->genres as $slug => $name) {
            $tag = DB::table('tags')->where('slug', $slug)->where('type', 'genre')->first();

            if ($tag === null) {
                continue;
            }

            $categoryId = DB::table('categories')->insertGetId([
                'name' => $name,
                'slug' => $slug,
                'is_active' => true,
                'description' => $tag->description,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $productIds = DB::table('product_tag')
                ->where('tag_id', $tag->id)
                ->pluck('product_id');

            foreach ($productIds as $productId) {
                DB::table('category_product')->insertOrIgnore([
                    'category_id' => $categoryId,
                    'product_id' => $productId,
                ]);
            }

            $legacyMapping = DB::table('import_legacy_tags')
                ->where('tag_id', $tag->id)
                ->first();

            if ($legacyMapping !== null) {
                DB::table('import_legacy_categories')->insertOrIgnore([
                    'legacy_term_id' => $legacyMapping->legacy_term_id,
                    'category_id' => $categoryId,
                    'synced_at' => $legacyMapping->synced_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('import_legacy_tags')
                    ->where('id', $legacyMapping->id)
                    ->delete();
            }

            DB::table('product_tag')
                ->where('tag_id', $tag->id)
                ->delete();

            DB::table('tags')
                ->where('id', $tag->id)
                ->delete();
        }
    }
};
