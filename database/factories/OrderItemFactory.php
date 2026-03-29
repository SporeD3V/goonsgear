<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'product_name' => fake()->words(3, true),
            'variant_name' => fake()->word(),
            'sku' => strtoupper(fake()->bothify('SKU-###???')),
            'unit_price' => fake()->randomFloat(2, 10, 200),
            'quantity' => fake()->numberBetween(1, 5),
            'line_total' => fake()->randomFloat(2, 10, 500),
        ];
    }
}
