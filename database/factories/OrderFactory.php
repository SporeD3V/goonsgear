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
            'email' => fake()->safeEmail(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->e164PhoneNumber(),
            'country' => 'DE',
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'currency' => 'EUR',
            'subtotal' => 99.99,
            'total' => 99.99,
            'placed_at' => now(),
        ];
    }
}
