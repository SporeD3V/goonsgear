<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Models\TagNotificationDispatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TagNotificationDispatch>
 */
class TagNotificationDispatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tag_id' => Tag::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'notification_type' => fake()->randomElement(['drop', 'discount']),
            'reference' => (string) fake()->numberBetween(1, 9999),
            'dispatched_at' => now(),
        ];
    }
}
