<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Codify manual category restructuring done on staging:
 *
 * - Wear (slug: wear) → Gear (slug: gear)
 * - Hats (slug: hats) → Headgear (slug: headgear) — new record, products reassigned
 * - T-Shirts → Shirts (slug: tshirts unchanged)
 * - Pants (slug: pants) → Bottoms (slug: bottoms) — Shorts merged in
 * - Wu-Wear → Socks (slug: wu-wear unchanged)
 * - Shorts deleted, products moved to Bottoms
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // 1. Rename Wear → Gear
        DB::table('categories')
            ->where('slug', 'wear')
            ->update(['name' => 'Gear', 'slug' => 'gear', 'updated_at' => $now]);

        // Find the parent (works whether slug is 'wear' already renamed to 'gear')
        $gearId = DB::table('categories')
            ->where('slug', 'gear')
            ->value('id');

        if ($gearId === null) {
            return;
        }

        // 2. Hats → Headgear: create new category, reassign products, remove old
        $hats = DB::table('categories')->where('slug', 'hats')->first();

        if ($hats !== null) {
            $headgearId = DB::table('categories')->insertGetId([
                'name' => 'Headgear',
                'slug' => 'headgear',
                'parent_id' => $gearId,
                'is_active' => true,
                'description' => 'Hats, beanies, snapbacks and headwear',
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('category_product')
                ->where('category_id', $hats->id)
                ->update(['category_id' => $headgearId]);

            DB::table('categories')->where('id', $hats->id)->delete();
        }

        // 3. T-Shirts → Shirts (keep slug tshirts)
        DB::table('categories')
            ->where('slug', 'tshirts')
            ->whereNot('name', 'Shirts')
            ->update(['name' => 'Shirts', 'updated_at' => $now]);

        // 4. Pants → Bottoms, absorb Shorts
        $pants = DB::table('categories')->where('slug', 'pants')->first();

        if ($pants !== null) {
            DB::table('categories')
                ->where('id', $pants->id)
                ->update([
                    'name' => 'Bottoms',
                    'slug' => 'bottoms',
                    'description' => 'Pants, shorts and bottoms',
                    'updated_at' => $now,
                ]);

            $bottomsId = $pants->id;
        } else {
            $bottomsId = DB::table('categories')->where('slug', 'bottoms')->value('id');
        }

        // Move Shorts products into Bottoms, then delete Shorts
        $shorts = DB::table('categories')->where('slug', 'shorts')->first();

        if ($shorts !== null && $bottomsId !== null) {
            $shortProductIds = DB::table('category_product')
                ->where('category_id', $shorts->id)
                ->pluck('product_id');

            $existingBottomProductIds = DB::table('category_product')
                ->where('category_id', $bottomsId)
                ->pluck('product_id');

            $toInsert = $shortProductIds->diff($existingBottomProductIds);

            foreach ($toInsert as $productId) {
                DB::table('category_product')->insert([
                    'category_id' => $bottomsId,
                    'product_id' => $productId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('category_product')->where('category_id', $shorts->id)->delete();
            DB::table('categories')->where('id', $shorts->id)->delete();
        }

        // 5. Wu-Wear → Socks (keep slug wu-wear)
        DB::table('categories')
            ->where('slug', 'wu-wear')
            ->whereNot('name', 'Socks')
            ->update(['name' => 'Socks', 'updated_at' => $now]);

        // 6. Update sort orders to match desired structure
        $sortOrders = [
            'headgear' => 0,
            'hoodies' => 9,
            'tshirts' => 10,
            'bottoms' => 12,
            'wu-wear' => 13,
        ];

        foreach ($sortOrders as $slug => $order) {
            DB::table('categories')
                ->where('slug', $slug)
                ->where('parent_id', $gearId)
                ->update(['sort_order' => $order, 'updated_at' => $now]);
        }

        // Set Gear parent sort order
        DB::table('categories')
            ->where('id', $gearId)
            ->update(['sort_order' => 7, 'updated_at' => $now]);
    }

    public function down(): void
    {
        $now = now();

        $gearId = DB::table('categories')->where('slug', 'gear')->value('id');

        if ($gearId === null) {
            return;
        }

        // Reverse: Gear → Wear
        DB::table('categories')
            ->where('id', $gearId)
            ->update(['name' => 'Wear', 'slug' => 'wear', 'sort_order' => 0, 'updated_at' => $now]);

        // Headgear → recreate Hats, move products back
        $headgear = DB::table('categories')->where('slug', 'headgear')->first();

        if ($headgear !== null) {
            $hatsId = DB::table('categories')->insertGetId([
                'name' => 'Hats',
                'slug' => 'hats',
                'parent_id' => $gearId,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('category_product')
                ->where('category_id', $headgear->id)
                ->update(['category_id' => $hatsId]);

            DB::table('categories')->where('id', $headgear->id)->delete();
        }

        // Shirts → T-Shirts
        DB::table('categories')
            ->where('slug', 'tshirts')
            ->update(['name' => 'T-Shirts', 'updated_at' => $now]);

        // Bottoms → Pants, recreate Shorts
        DB::table('categories')
            ->where('slug', 'bottoms')
            ->update(['name' => 'Pants', 'slug' => 'pants', 'description' => null, 'updated_at' => $now]);

        DB::table('categories')->insertGetId([
            'name' => 'Shorts',
            'slug' => 'shorts',
            'parent_id' => $gearId,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Socks → Wu-Wear
        DB::table('categories')
            ->where('slug', 'wu-wear')
            ->update(['name' => 'Wu-Wear', 'updated_at' => $now]);
    }
};
