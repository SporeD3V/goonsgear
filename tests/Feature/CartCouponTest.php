<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
        $response->assertSessionHas('cart.coupon_codes', ['SAVE10']);
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

    public function test_cart_applies_best_valid_combination_from_selected_coupons(): void
    {
        $product = Product::factory()->create(['status' => 'active']);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 200,
            'track_inventory' => false,
            'is_active' => true,
        ]);

        Coupon::factory()->create([
            'code' => 'EXCLUSIVE20',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 20,
            'is_stackable' => false,
        ]);

        Coupon::factory()->create([
            'code' => 'STACK10',
            'type' => Coupon::TYPE_FIXED,
            'value' => 10,
            'is_stackable' => true,
            'stack_group' => 'LOYALTY',
        ]);

        Coupon::factory()->create([
            'code' => 'STACK15',
            'type' => Coupon::TYPE_FIXED,
            'value' => 15,
            'is_stackable' => true,
            'stack_group' => 'LOYALTY',
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
                    'price' => 200.00,
                    'quantity' => 1,
                    'max_quantity' => null,
                    'image' => null,
                    'url' => route('shop.show', $product),
                ],
            ],
            'cart.coupon_codes' => ['STACK10', 'STACK15', 'EXCLUSIVE20'],
        ])->get(route('cart.index'));

        $response->assertOk();
        $response->assertSee('Best coupon applied: EXCLUSIVE20.');
        $response->assertSee('- $40.00');
    }

    public function test_cart_uses_best_coupon_even_when_more_than_fourteen_codes_are_selected(): void
    {
        $product = Product::factory()->create(['status' => 'active']);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 200,
            'track_inventory' => false,
            'is_active' => true,
        ]);

        $codes = [];

        foreach (range(1, 15) as $index) {
            $code = 'STACK'.$index;
            $codes[] = $code;

            Coupon::factory()->create([
                'code' => $code,
                'type' => Coupon::TYPE_FIXED,
                'value' => $index,
                'is_stackable' => true,
                'stack_group' => 'GROUP-A',
            ]);
        }

        Coupon::factory()->create([
            'code' => 'STACK50',
            'type' => Coupon::TYPE_FIXED,
            'value' => 50,
            'is_stackable' => true,
            'stack_group' => 'GROUP-A',
        ]);

        $codes[] = 'STACK50';

        $response = $this->withSession([
            'cart.items' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => $product->name,
                    'product_slug' => $product->slug,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'price' => 200.00,
                    'quantity' => 1,
                    'max_quantity' => null,
                    'image' => null,
                    'url' => route('shop.show', $product),
                ],
            ],
            'cart.coupon_codes' => $codes,
        ])->get(route('cart.index'));

        $response->assertOk();
        $response->assertSee('Best coupon applied: STACK50.');
        $response->assertSee('- $50.00');
    }

    public function test_cart_page_still_loads_when_coupon_assignment_table_is_missing(): void
    {
        $product = Product::factory()->create(['status' => 'active']);

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
        ]);

        Schema::dropIfExists('coupon_user');

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
            'cart.coupon_codes' => ['SAVE10'],
        ])->get(route('cart.index'));

        $response->assertOk();
        $response->assertSee('Best coupon applied: SAVE10.');
        $response->assertDontSee('Choose from your account coupons');
    }
}
