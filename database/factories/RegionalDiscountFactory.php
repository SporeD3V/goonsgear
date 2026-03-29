<?php

namespace Database\Factories;

use App\Models\RegionalDiscount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegionalDiscount>
 */
class RegionalDiscountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_code' => fake()->unique()->countryCode(),
            'discount_type' => fake()->randomElement(RegionalDiscount::supportedTypes()),
            'discount_value' => fake()->randomFloat(2, 2, 20),
            'reason' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
