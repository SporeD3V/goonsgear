<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartCouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_coupon_can_be_applied_to_cart(): void
    {
        $product = Product::factory()->create([
            'status' => 'active',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 120,
            'track_inventory' => false,
            'is_active' => true,
        ]);

        Coupon::factory()->create([
            'code' => 'SAVE10',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 10,
            'minimum_subtotal' => 100,
        ]);

        $response = $this->withSession([
            'cart.items' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => $product->name,
                    'product_slug' => $product->slug,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'price' => 120.00,
                    'quantity' => 1,
                    'max_quantity' => null,
                    'image' => null,
                    'url' => route('shop.show', $product),
                ],
            ],
        ])->post(route('cart.coupon.apply'), [
            'coupon_code' => 'save10',
        ]);

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHas('cart.coupon_code', 'SAVE10');
    }

    public function test_invalid_coupon_is_rejected(): void
    {
        $product = Product::factory()->create([
            'status' => 'active',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 50,
            'track_inventory' => false,
            'is_active' => true,
        ]);

        Coupon::factory()->create([
            'code' => 'BIGSAVE',
            'type' => Coupon::TYPE_FIXED,
            'value' => 10,
            'minimum_subtotal' => 100,
        ]);

        $response = $this->from(route('cart.index'))->withSession([
            'cart.items' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => $product->name,
                    'product_slug' => $product->slug,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'price' => 50.00,
                    'quantity' => 1,
                    'max_quantity' => null,
                    'image' => null,
                    'url' => route('shop.show', $product),
                ],
            ],
        ])->post(route('cart.coupon.apply'), [
            'coupon_code' => 'BIGSAVE',
        ]);

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHasErrors('coupon_code');
    }
}
