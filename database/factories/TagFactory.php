<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $type = fake()->randomElement(['artist', 'brand']);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'type' => $type,
            'is_active' => true,
            'description' => fake()->optional()->sentence(),
        ];
    }
}
