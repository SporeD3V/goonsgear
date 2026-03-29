<?php

namespace Database\Factories;

use App\Models\BundleDiscount;
use App\Models\BundleDiscountItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BundleDiscountItem>
 */
class BundleDiscountItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bundle_discount_id' => BundleDiscount::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'min_quantity' => fake()->numberBetween(1, 3),
            'position' => 0,
        ];
    }
}
