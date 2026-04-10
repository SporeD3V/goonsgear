<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sale = Category::where('slug', 'sale')->first();

        if ($sale === null) {
            return;
        }

        Category::firstOrCreate(
            ['slug' => 'discounts'],
            [
                'parent_id' => $sale->id,
                'name' => 'Discounts',
                'is_active' => true,
                'sort_order' => 0,
            ],
        );

        Category::firstOrCreate(
            ['slug' => 'bundles'],
            [
                'parent_id' => $sale->id,
                'name' => 'Bundles',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Category::where('slug', 'discounts')
            ->whereHas('parent', fn ($q) => $q->where('slug', 'sale'))
            ->delete();

        Category::where('slug', 'bundles')
            ->whereHas('parent', fn ($q) => $q->where('slug', 'sale'))
            ->delete();
    }
};
