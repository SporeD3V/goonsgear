<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\StockAlertSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAlertSubscription>
 */
class StockAlertSubscriptionFactory extends Factory
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
            'is_active' => true,
            'notified_at' => null,
        ];
    }
}
