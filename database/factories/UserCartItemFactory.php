<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\User;
use App\Models\UserCartItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserCartItem>
 */
class UserCartItemFactory extends Factory
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
            'product_variant_id' => ProductVariant::factory(),
            'quantity' => $this->faker->numberBetween(1, 5),
        ];
    }
}
