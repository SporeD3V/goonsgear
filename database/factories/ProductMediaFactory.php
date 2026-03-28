<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductMedia>
 */
class ProductMediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'disk' => 'public',
            'path' => 'products/'.fake()->uuid().'.avif',
            'mime_type' => 'image/avif',
            'width' => 1600,
            'height' => 1600,
            'alt_text' => fake()->sentence(4),
            'is_primary' => false,
            'position' => 0,
        ];
    }

    public function forVariant(?ProductVariant $variant = null): static
    {
        return $this->state(function () use ($variant): array {
            $resolvedVariant = $variant ?? ProductVariant::factory()->create();

            return [
                'product_id' => $resolvedVariant->product_id,
                'product_variant_id' => $resolvedVariant->id,
            ];
        });
    }
}
