<?php

namespace Database\Factories;

use App\Models\WcSyncPayload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WcSyncPayload>
 */
class WcSyncPayloadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event' => 'order.created',
            'wc_entity_type' => 'order',
            'wc_entity_id' => fake()->unique()->numberBetween(1000, 99999),
            'payload' => [
                'event' => 'order.created',
                'timestamp' => now()->toIso8601String(),
                'data' => [],
            ],
            'received_at' => now(),
            'processed_at' => null,
            'processing_error' => null,
            'attempts' => 0,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processed_at' => now(),
        ]);
    }

    public function failed(string $error = 'Test error'): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_error' => $error,
            'attempts' => 3,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function forEvent(string $event, array $data = []): static
    {
        $prefix = explode('.', $event)[0];
        $entityType = match ($prefix) {
            'order' => 'order',
            'product' => 'product',
            'coupon' => 'coupon',
            'customer' => 'customer',
            'note' => 'note',
            default => null,
        };

        return $this->state(fn (array $attributes) => [
            'event' => $event,
            'wc_entity_type' => $entityType,
            'payload' => [
                'event' => $event,
                'timestamp' => now()->toIso8601String(),
                'data' => $data,
            ],
        ]);
    }
}
