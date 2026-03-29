<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->bothify('SAVE##??')),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(Coupon::supportedTypes()),
            'value' => fake()->randomFloat(2, 5, 25),
            'minimum_subtotal' => fake()->optional()->randomFloat(2, 25, 150),
            'usage_limit' => fake()->optional()->numberBetween(5, 100),
            'used_count' => 0,
            'is_active' => true,
            'is_stackable' => fake()->boolean(35),
            'stack_group' => null,
            'scope_type' => Coupon::SCOPE_ALL,
            'scope_id' => null,
            'is_personal' => false,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ];
    }
}
