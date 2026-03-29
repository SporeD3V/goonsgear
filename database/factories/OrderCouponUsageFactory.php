<?php

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderCouponUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderCouponUsage>
 */
class OrderCouponUsageFactory extends Factory
{
    protected $model = OrderCouponUsage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'coupon_id' => Coupon::factory(),
            'coupon_code' => strtoupper(fake()->bothify('SAVE##??')),
            'discount_total' => fake()->randomFloat(2, 1, 100),
            'applied_position' => 0,
        ];
    }
}
