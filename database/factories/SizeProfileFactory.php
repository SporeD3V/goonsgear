<?php

namespace Database\Factories;

use App\Models\SizeProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SizeProfile>
 */
class SizeProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->firstName(),
            'is_self' => false,
            'top_size' => fake()->randomElement(['XS', 'S', 'M', 'L', 'XL', 'XXL']),
            'bottom_size' => fake()->randomElement(['S', 'M', 'L', 'XL']),
            'shoe_size' => fake()->randomElement(['38', '39', '40', '41', '42', '43', '44', '45']),
        ];
    }

    public function self(): static
    {
        return $this->state(fn () => ['is_self' => true]);
    }
}
