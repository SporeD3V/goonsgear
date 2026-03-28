<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->randomElement(['Default', 'Small', 'Medium', 'Large']),
            'sku' => strtoupper(fake()->bothify('GG-####-??')),
            'option_values' => [
                'size' => fake()->randomElement(['S', 'M', 'L']),
                'color' => fake()->safeColorName(),
            ],
            'price' => fake()->randomFloat(2, 9, 199),
            'compare_at_price' => null,
            'track_inventory' => true,
            'stock_quantity' => fake()->numberBetween(0, 50),
            'allow_backorder' => false,
            'is_active' => true,
            'is_preorder' => false,
            'position' => 0,
            'preorder_available_from' => null,
            'expected_ship_at' => null,
        ];
    }
}
