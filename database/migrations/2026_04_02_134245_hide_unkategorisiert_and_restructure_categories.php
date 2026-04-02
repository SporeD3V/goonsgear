<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Deactivate "Unkategorisiert" — hidden from storefront, visible in admin
        DB::table('categories')
            ->where('slug', 'unkategorisiert')
            ->update(['is_active' => false, 'updated_at' => now()]);

        // 2. Create "Music" parent category and assign children
        $musicId = DB::table('categories')->insertGetId([
            'name' => 'Music',
            'slug' => 'music',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('categories')
            ->whereIn('slug', ['cds', 'vinyl', 'tapes'])
            ->update(['parent_id' => $musicId, 'updated_at' => now()]);

        // 3. Create "Wear" parent category and assign children
        $wearId = DB::table('categories')->insertGetId([
            'name' => 'Wear',
            'slug' => 'wear',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('categories')
            ->whereIn('slug', ['tshirts', 'hoodies', 'hats', 'wu-wear', 'pants', 'shorts'])
            ->update(['parent_id' => $wearId, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Re-activate unkategorisiert
        DB::table('categories')
            ->where('slug', 'unkategorisiert')
            ->update(['is_active' => true, 'updated_at' => now()]);

        // Remove parent_id from Music children
        DB::table('categories')
            ->whereIn('slug', ['cds', 'vinyl', 'tapes'])
            ->update(['parent_id' => null, 'updated_at' => now()]);

        // Remove parent_id from Wear children
        DB::table('categories')
            ->whereIn('slug', ['tshirts', 'hoodies', 'hats', 'wu-wear', 'pants', 'shorts'])
            ->update(['parent_id' => null, 'updated_at' => now()]);

        // Delete Music and Wear parent categories
        DB::table('categories')->where('slug', 'music')->delete();
        DB::table('categories')->where('slug', 'wear')->delete();
    }
};
