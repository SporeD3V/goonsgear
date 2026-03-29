<?php

namespace Database\Factories;

use App\Models\CartAbandonment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CartAbandonment>
 */
class CartAbandonmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->safeEmail(),
            'cart_data' => [
                1 => [
                    'variant_id' => 1,
                    'product_id' => 1,
                    'product_name' => 'Test Product',
                    'product_slug' => 'test-product',
                    'variant_name' => 'Default',
                    'sku' => 'TEST-01',
                    'price' => 49.99,
                    'quantity' => 1,
                    'max_quantity' => 10,
                    'image' => null,
                    'url' => null,
                ],
            ],
            'token' => Str::uuid()->toString(),
            'abandoned_at' => now()->subHours(2),
        ];
    }

    public function recovered(): static
    {
        return $this->state(['recovered_at' => now()]);
    }

    public function reminded(): static
    {
        return $this->state(['reminder_sent_at' => now()->subMinutes(30)]);
    }

    public function recent(): static
    {
        return $this->state(['abandoned_at' => now()->subMinutes(10)]);
    }
}
