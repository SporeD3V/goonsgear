<?php

namespace Database\Factories;

use App\Models\UrlRedirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UrlRedirect>
 */
class UrlRedirectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_path' => '/'.fake()->unique()->slug(3),
            'to_url' => '/shop',
            'status_code' => fake()->randomElement([301, 302]),
            'is_active' => true,
        ];
    }
}
