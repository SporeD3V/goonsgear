<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rename existing genre tags to custom
        DB::table('tags')
            ->where('type', 'genre')
            ->update(['type' => 'custom']);

        // Convert 90s Hip Hop category to custom tag
        $category = DB::table('categories')->where('slug', '90shiphop')->first();

        if ($category !== null) {
            $existingTag = DB::table('tags')->where('slug', '90shiphop')->where('type', 'custom')->first();

            if ($existingTag !== null) {
                $tagId = $existingTag->id;
            } else {
                $tagId = DB::table('tags')->insertGetId([
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'type' => 'custom',
                    'is_active' => true,
                    'description' => $category->description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

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
    }

    public function down(): void
    {
        // Recreate 90s Hip Hop category from tag
        $tag = DB::table('tags')->where('slug', '90shiphop')->where('type', 'custom')->first();

        if ($tag !== null) {
            $categoryId = DB::table('categories')->insertGetId([
                'name' => '90s Hip Hop',
                'slug' => '90shiphop',
                'is_active' => true,
                'description' => $tag->description,
                'sort_order' => 10,
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

        // Rename custom tags back to genre
        DB::table('tags')
            ->where('type', 'custom')
            ->update(['type' => 'genre']);
    }
};
