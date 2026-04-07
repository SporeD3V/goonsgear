<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RegionalDiscount;
use App\Support\CartPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegionalDiscountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    // ─── Admin CRUD ───────────────────────────────────────────────────────────

    public function test_admin_can_list_regional_discounts(): void
    {
        RegionalDiscount::factory()->create(['country_code' => 'AU', 'reason' => 'High shipping cost']);

        Livewire::test('admin.regional-discount-manager')
            ->assertSee('AU')
            ->assertSee('High shipping cost');
    }

    public function test_admin_can_create_regional_discount(): void
    {
        Livewire::test('admin.regional-discount-manager')
            ->call('openCreate')
            ->set('country_code', 'AU')
            ->set('discount_type', 'fixed')
            ->set('discount_value', '10.00')
            ->set('reason', 'High shipping to Australia')
            ->set('is_active', true)
            ->call('save')
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('regional_discounts', [
            'country_code' => 'AU',
            'discount_type' => 'fixed',
            'discount_value' => '10.00',
            'reason' => 'High shipping to Australia',
            'is_active' => true,
        ]);
    }

    public function test_admin_store_normalises_country_code_to_uppercase(): void
    {
        Livewire::test('admin.regional-discount-manager')
            ->call('openCreate')
            ->set('country_code', 'au')
            ->set('discount_type', 'fixed')
            ->set('discount_value', '5.00')
            ->set('reason', 'Test')
            ->set('is_active', true)
            ->call('save');

        $this->assertDatabaseHas('regional_discounts', ['country_code' => 'AU']);
    }

    public function test_admin_store_rejects_invalid_data(): void
    {
        Livewire::test('admin.regional-discount-manager')
            ->call('openCreate')
            ->set('country_code', '')
            ->set('discount_type', 'invalid')
            ->set('discount_value', '-1')
            ->set('reason', '')
            ->call('save')
            ->assertHasErrors(['country_code', 'discount_type', 'discount_value', 'reason']);

        $this->assertSame(0, RegionalDiscount::query()->count());
    }

    public function test_admin_store_rejects_duplicate_country_code(): void
    {
        RegionalDiscount::factory()->create(['country_code' => 'AU']);

        Livewire::test('admin.regional-discount-manager')
            ->call('openCreate')
            ->set('country_code', 'AU')
            ->set('discount_type', 'fixed')
            ->set('discount_value', '5.00')
            ->set('reason', 'Dupe')
            ->set('is_active', true)
            ->call('save')
            ->assertHasErrors('country_code');

        $this->assertSame(1, RegionalDiscount::query()->count());
    }

    public function test_admin_can_update_regional_discount(): void
    {
        $discount = RegionalDiscount::factory()->create(['country_code' => 'AU', 'discount_value' => 5.00]);

        Livewire::test('admin.regional-discount-manager')
            ->call('openEdit', $discount->id)
            ->set('discount_type', 'percent')
            ->set('discount_value', '15.00')
            ->set('reason', 'Updated reason')
            ->set('is_active', true)
            ->call('save')
            ->assertSet('showModal', false);

        $discount->refresh();
        $this->assertSame('percent', $discount->discount_type);
        $this->assertSame('15.00', $discount->discount_value);
        $this->assertSame('Updated reason', $discount->reason);
    }

    public function test_admin_can_delete_regional_discount(): void
    {
        $discount = RegionalDiscount::factory()->create(['country_code' => 'AU']);

        Livewire::test('admin.regional-discount-manager')
            ->call('delete', $discount->id);

        $this->assertDatabaseMissing('regional_discounts', ['id' => $discount->id]);
    }

    // ─── Model Logic ─────────────────────────────────────────────────────────

    public function test_find_for_country_returns_active_discount(): void
    {
        RegionalDiscount::factory()->create([
            'country_code' => 'AU',
            'discount_type' => 'fixed',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $result = RegionalDiscount::findForCountry('AU');

        $this->assertNotNull($result);
        $this->assertSame('AU', $result?->country_code);
    }

    public function test_find_for_country_ignores_inactive_discount(): void
    {
        RegionalDiscount::factory()->create([
            'country_code' => 'AU',
            'is_active' => false,
        ]);

        $this->assertNull(RegionalDiscount::findForCountry('AU'));
    }

    public function test_find_for_country_is_case_insensitive(): void
    {
        RegionalDiscount::factory()->create(['country_code' => 'AU', 'is_active' => true]);

        $this->assertNotNull(RegionalDiscount::findForCountry('au'));
    }

    public function test_discount_for_fixed_type(): void
    {
        $discount = RegionalDiscount::factory()->make([
            'discount_type' => RegionalDiscount::TYPE_FIXED,
            'discount_value' => 10.00,
        ]);

        $this->assertSame(10.0, $discount->discountFor(100.0));
    }

    public function test_discount_for_percent_type(): void
    {
        $discount = RegionalDiscount::factory()->make([
            'discount_type' => RegionalDiscount::TYPE_PERCENT,
            'discount_value' => 10.00,
        ]);

        $this->assertSame(10.0, $discount->discountFor(100.0));
    }

    public function test_discount_for_does_not_exceed_subtotal(): void
    {
        $discount = RegionalDiscount::factory()->make([
            'discount_type' => RegionalDiscount::TYPE_FIXED,
            'discount_value' => 200.00,
        ]);

        $this->assertSame(50.0, $discount->discountFor(50.0));
    }

    // ─── CartPricing ─────────────────────────────────────────────────────────

    public function test_cart_pricing_applies_regional_discount(): void
    {
        RegionalDiscount::factory()->create([
            'country_code' => 'AU',
            'discount_type' => 'fixed',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $pricing = app(CartPricing::class)->summarizeFromSubtotal(100.0, null, 'AU');

        $this->assertSame(10.0, $pricing['regional_discount_total']);
        $this->assertSame(90.0, $pricing['total']);
        $this->assertNotNull($pricing['regional_discount']);
    }

    public function test_cart_pricing_stacks_coupon_and_regional_discount(): void
    {
        RegionalDiscount::factory()->create([
            'country_code' => 'AU',
            'discount_type' => 'fixed',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        Coupon::factory()->create([
            'code' => 'SAVE5',
            'type' => Coupon::TYPE_FIXED,
            'value' => 5.00,
            'is_active' => true,
            'minimum_subtotal' => null,
            'usage_limit' => null,
        ]);

        $pricing = app(CartPricing::class)->summarizeFromSubtotal(100.0, 'SAVE5', 'AU');

        $this->assertSame(5.0, $pricing['discount_total']);
        $this->assertSame(10.0, $pricing['regional_discount_total']);
        $this->assertSame(85.0, $pricing['total']);
    }

    public function test_cart_pricing_returns_zero_regional_discount_when_no_rule_exists(): void
    {
        $pricing = app(CartPricing::class)->summarizeFromSubtotal(100.0, null, 'AU');

        $this->assertSame(0.0, $pricing['regional_discount_total']);
        $this->assertNull($pricing['regional_discount']);
        $this->assertSame(100.0, $pricing['total']);
    }

    // ─── API endpoint ─────────────────────────────────────────────────────────

    public function test_api_returns_discount_for_country(): void
    {
        RegionalDiscount::factory()->create([
            'country_code' => 'AU',
            'discount_type' => 'fixed',
            'discount_value' => 10.00,
            'reason' => 'High shipping to Australia',
            'is_active' => true,
        ]);

        $response = $this->getJson(route('api.regional-discount', ['country' => 'AU', 'subtotal' => 100]));

        $response->assertOk();
        $response->assertJsonPath('discount_total', 10);
        $response->assertJsonPath('reason', 'High shipping to Australia');
    }

    public function test_api_returns_null_when_no_rule_exists(): void
    {
        $response = $this->getJson(route('api.regional-discount', ['country' => 'AU', 'subtotal' => 100]));

        $response->assertOk();
        $response->assertJsonPath('discount_total', null);
    }

    public function test_api_returns_null_for_missing_country_param(): void
    {
        $response = $this->getJson(route('api.regional-discount', ['subtotal' => 100]));

        $response->assertOk();
        $response->assertJsonPath('discount_total', null);
    }

    // ─── Checkout integration ──────────────────────────────────────────────────

    public function test_checkout_applies_regional_discount_to_order(): void
    {
        RegionalDiscount::factory()->create([
            'country_code' => 'DE',
            'discount_type' => 'fixed',
            'discount_value' => 15.00,
            'is_active' => true,
        ]);

        $product = Product::factory()->create(['status' => 'active', 'slug' => 'rd-hoodie']);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 100.00,
            'track_inventory' => true,
            'stock_quantity' => 5,
            'allow_backorder' => false,
            'is_active' => true,
        ]);

        $this->withSession([
            'cart.items' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => 'RD Hoodie',
                    'product_slug' => 'rd-hoodie',
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
        $this->assertSame('15.00', $order?->regional_discount_total);
        $this->assertSame('85.00', $order?->total);
    }

    public function test_checkout_without_regional_discount_saves_zero(): void
    {
        $product = Product::factory()->create(['status' => 'active', 'slug' => 'rd-hoodie-2']);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 100.00,
            'track_inventory' => true,
            'stock_quantity' => 5,
            'allow_backorder' => false,
            'is_active' => true,
        ]);

        $this->withSession([
            'cart.items' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => 'RD Hoodie 2',
                    'product_slug' => 'rd-hoodie-2',
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
        $this->assertSame('0.00', $order?->regional_discount_total);
        $this->assertSame('100.00', $order?->total);
    }
}
