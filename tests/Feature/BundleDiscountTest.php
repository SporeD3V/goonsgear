<?php

namespace Tests\Feature;

use App\Models\BundleDiscount;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CartPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundleDiscountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    public function test_admin_can_create_bundle_discount(): void
    {
        $variant = ProductVariant::factory()->create();

        $response = $this->post(route('admin.bundle-discounts.store'), [
            'name' => 'Hoodie + Tee Set',
            'description' => 'Discount for matching outfit set',
            'discount_type' => BundleDiscount::TYPE_FIXED,
            'discount_value' => 12.5,
            'variant_ids' => [(string) $variant->id],
            'quantities' => [
                (string) $variant->id => 2,
            ],
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.bundle-discounts.index'));

        $bundle = BundleDiscount::query()->where('name', 'Hoodie + Tee Set')->first();
        $this->assertNotNull($bundle);
        $this->assertDatabaseHas('bundle_discount_items', [
            'bundle_discount_id' => $bundle?->id,
            'product_variant_id' => $variant->id,
            'min_quantity' => 2,
        ]);
    }

    public function test_bundle_discount_applies_when_cart_matches_requirements(): void
    {
        $variant = ProductVariant::factory()->create([
            'price' => 50.00,
        ]);

        $bundle = BundleDiscount::factory()->create([
            'discount_type' => BundleDiscount::TYPE_FIXED,
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $bundle->items()->create([
            'product_variant_id' => $variant->id,
            'min_quantity' => 2,
            'position' => 0,
        ]);

        $pricing = app(CartPricing::class)->summarize([
            [
                'variant_id' => $variant->id,
                'price' => 50.00,
                'quantity' => 2,
            ],
        ]);

        $this->assertSame(10.0, $pricing['bundle_discount_total']);
        $this->assertSame(90.0, $pricing['total']);
        $this->assertSame($bundle->id, $pricing['bundle_discount']?->id);
    }

    public function test_bundle_discount_does_not_apply_when_cart_does_not_match(): void
    {
        $variant = ProductVariant::factory()->create([
            'price' => 50.00,
        ]);

        $bundle = BundleDiscount::factory()->create([
            'discount_type' => BundleDiscount::TYPE_FIXED,
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $bundle->items()->create([
            'product_variant_id' => $variant->id,
            'min_quantity' => 3,
            'position' => 0,
        ]);

        $pricing = app(CartPricing::class)->summarize([
            [
                'variant_id' => $variant->id,
                'price' => 50.00,
                'quantity' => 2,
            ],
        ]);

        $this->assertSame(0.0, $pricing['bundle_discount_total']);
        $this->assertNull($pricing['bundle_discount']);
        $this->assertSame(100.0, $pricing['total']);
    }

    public function test_bundle_discount_selects_highest_matching_discount(): void
    {
        $variant = ProductVariant::factory()->create([
            'price' => 80.00,
        ]);

        $lowerBundle = BundleDiscount::factory()->create([
            'name' => 'Lower',
            'discount_type' => BundleDiscount::TYPE_FIXED,
            'discount_value' => 5.00,
        ]);

        $higherBundle = BundleDiscount::factory()->create([
            'name' => 'Higher',
            'discount_type' => BundleDiscount::TYPE_FIXED,
            'discount_value' => 15.00,
        ]);

        $lowerBundle->items()->create([
            'product_variant_id' => $variant->id,
            'min_quantity' => 1,
            'position' => 0,
        ]);

        $higherBundle->items()->create([
            'product_variant_id' => $variant->id,
            'min_quantity' => 1,
            'position' => 0,
        ]);

        $pricing = app(CartPricing::class)->summarize([
            [
                'variant_id' => $variant->id,
                'price' => 80.00,
                'quantity' => 1,
            ],
        ]);

        $this->assertSame(15.0, $pricing['bundle_discount_total']);
        $this->assertSame($higherBundle->id, $pricing['bundle_discount']?->id);
        $this->assertSame(65.0, $pricing['total']);
    }

    public function test_checkout_saves_bundle_discount_total_on_order(): void
    {
        $product = Product::factory()->create(['status' => 'active', 'slug' => 'bundle-hoodie']);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 100.00,
            'track_inventory' => true,
            'stock_quantity' => 5,
            'allow_backorder' => false,
            'is_active' => true,
        ]);

        $bundle = BundleDiscount::factory()->create([
            'discount_type' => BundleDiscount::TYPE_FIXED,
            'discount_value' => 12.00,
            'is_active' => true,
        ]);

        $bundle->items()->create([
            'product_variant_id' => $variant->id,
            'min_quantity' => 1,
            'position' => 0,
        ]);

        Coupon::factory()->create([
            'code' => 'SAVE5',
            'type' => Coupon::TYPE_FIXED,
            'value' => 5.00,
            'is_active' => true,
            'minimum_subtotal' => null,
            'usage_limit' => null,
        ]);

        $this->withSession([
            'cart.coupon_code' => 'SAVE5',
            'cart.items' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => 'Bundle Hoodie',
                    'product_slug' => 'bundle-hoodie',
                    'variant_name' => 'M',
                    'sku' => $variant->sku,
                    'price' => 100.00,
                    'quantity' => 1,
                    'max_quantity' => 5,
                    'image' => null,
                    'url' => route('shop.show', $product),
                ],
            ],
        ])->post(route('checkout.store'), [
            'email' => 'customer@example.com',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'country' => 'DE',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'street_name' => 'Alexanderplatz',
            'street_number' => '1',
        ]);

        $order = Order::query()->latest('id')->first();

        $this->assertNotNull($order);
        $this->assertSame('12.00', $order?->bundle_discount_total);
        $this->assertSame('83.00', $order?->total);
    }
}
