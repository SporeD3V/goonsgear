<?php

namespace Database\Factories;

use App\Models\BundleDiscount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BundleDiscount>
 */
class BundleDiscountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'discount_type' => fake()->randomElement(BundleDiscount::supportedTypes()),
            'discount_value' => fake()->randomFloat(2, 5, 25),
            'is_active' => true,
        ];
    }
}
