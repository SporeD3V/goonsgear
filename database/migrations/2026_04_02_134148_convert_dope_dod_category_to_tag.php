<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Artist slug/name to convert from category to tag.
     *
     * @var array<string, string>
     */
    private array $artists = [
        'dod' => 'Dope D.O.D',
    ];

    public function up(): void
    {
        foreach ($this->artists as $slug => $name) {
            $category = DB::table('categories')->where('slug', $slug)->first();

            if ($category === null) {
                continue;
            }

            // Create the artist tag (or find existing)
            $existingTag = DB::table('tags')->where('slug', $slug)->where('type', 'artist')->first();

            if ($existingTag !== null) {
                $tagId = $existingTag->id;
            } else {
                $tagId = DB::table('tags')->insertGetId([
                    'name' => $name,
                    'slug' => $slug,
                    'type' => 'artist',
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

            // Remove category_product entries
            DB::table('category_product')
                ->where('category_id', $category->id)
                ->delete();

            // Delete the category
            DB::table('categories')
                ->where('id', $category->id)
                ->delete();
        }
    }

    public function down(): void
    {
        foreach ($this->artists as $slug => $name) {
            $tag = DB::table('tags')->where('slug', $slug)->where('type', 'artist')->first();

            if ($tag === null) {
                continue;
            }

            // Recreate the category
            $categoryId = DB::table('categories')->insertGetId([
                'name' => $name,
                'slug' => $slug,
                'description' => $tag->description,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Move product associations back
            $productIds = DB::table('product_tag')
                ->where('tag_id', $tag->id)
                ->pluck('product_id');

            foreach ($productIds as $productId) {
                DB::table('category_product')->insertOrIgnore([
                    'category_id' => $categoryId,
                    'product_id' => $productId,
                    'position' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Restore import_legacy mapping
            $legacyTagMapping = DB::table('import_legacy_tags')
                ->where('tag_id', $tag->id)
                ->first();

            if ($legacyTagMapping !== null) {
                DB::table('import_legacy_categories')->insertOrIgnore([
                    'legacy_term_id' => $legacyTagMapping->legacy_term_id,
                    'category_id' => $categoryId,
                    'synced_at' => $legacyTagMapping->synced_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('import_legacy_tags')
                    ->where('id', $legacyTagMapping->id)
                    ->delete();
            }

            // Remove product_tag entries
            DB::table('product_tag')
                ->where('tag_id', $tag->id)
                ->delete();

            // Delete the tag
            DB::table('tags')
                ->where('id', $tag->id)
                ->delete();
        }
    }
};
