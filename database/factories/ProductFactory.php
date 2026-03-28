<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'primary_category_id' => Category::factory(),
            'name' => fake()->unique()->words(3, true),
            'slug' => fake()->unique()->slug(),
            'status' => 'active',
            'excerpt' => fake()->sentence(),
            'description' => fake()->paragraphs(3, true),
            'meta_title' => fake()->sentence(5),
            'meta_description' => fake()->sentence(10),
            'is_featured' => false,
            'is_preorder' => false,
            'published_at' => now(),
            'preorder_available_from' => null,
            'expected_ship_at' => null,
        ];
    }
}
