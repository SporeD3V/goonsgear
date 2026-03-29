<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'GG-'.strtoupper(fake()->bothify('????##??')),
            'status' => 'pending',
            'payment_method' => 'manual',
            'payment_status' => 'pending',
            'paypal_order_id' => null,
            'paypal_capture_id' => null,
            'email' => fake()->safeEmail(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->e164PhoneNumber(),
            'country' => 'DE',
            'state' => fake()->state(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'street_name' => fake()->streetName(),
            'street_number' => (string) fake()->buildingNumber(),
            'apartment_block' => null,
            'entrance' => null,
            'floor' => null,
            'apartment_number' => null,
            'currency' => 'EUR',
            'subtotal' => 99.99,
            'total' => 99.99,
            'placed_at' => now(),
        ];
    }
}
