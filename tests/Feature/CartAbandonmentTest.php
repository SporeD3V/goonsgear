<?php

namespace Tests\Feature;

use App\Mail\AbandonedCartReminder;
use App\Models\AbandonedCartSetting;
use App\Models\CartAbandonment;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CartAbandonmentTest extends TestCase
{
    use RefreshDatabase;

    private function cartSession(ProductVariant $variant): array
    {
        return [
            'cart.items' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => 'Test Product',
                    'product_slug' => 'test-product',
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'price' => (float) $variant->price,
                    'quantity' => 1,
                    'max_quantity' => 5,
                    'image' => null,
                    'url' => null,
                ],
            ],
        ];
    }

    public function test_track_email_creates_cart_abandonment_record(): void
    {
        $product = Product::factory()->create(['status' => 'active']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);

        $this->withSession($this->cartSession($variant))
            ->postJson(route('cart.track-email'), ['email' => 'shopper@example.com'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('cart_abandonments', [
            'email' => 'shopper@example.com',
            'recovered_at' => null,
        ]);
    }

    public function test_track_email_updates_existing_unrecovered_record(): void
    {
        $product = Product::factory()->create(['status' => 'active']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);

        $existing = CartAbandonment::factory()->create([
            'email' => 'shopper@example.com',
            'reminder_sent_at' => now()->subMinutes(5),
        ]);

        $this->withSession($this->cartSession($variant))
            ->postJson(route('cart.track-email'), ['email' => 'shopper@example.com'])
            ->assertOk();

        $this->assertSame(1, CartAbandonment::query()->where('email', 'shopper@example.com')->count());

        $existing->refresh();
        $this->assertNull($existing->reminder_sent_at);
    }

    public function test_track_email_does_nothing_with_empty_cart(): void
    {
        $this->postJson(route('cart.track-email'), ['email' => 'shopper@example.com'])
            ->assertOk();

        $this->assertDatabaseMissing('cart_abandonments', ['email' => 'shopper@example.com']);
    }

    public function test_recover_cart_restores_session_and_marks_recovered(): void
    {
        $abandonment = CartAbandonment::factory()->create(['email' => 'shopper@example.com']);

        $response = $this->get(route('cart.recover', $abandonment->token));

        $response->assertRedirect(route('checkout.index'));
        $response->assertSessionHas('cart.items');

        $abandonment->refresh();
        $this->assertNotNull($abandonment->recovered_at);
    }

    public function test_recover_cart_applies_coupon_from_recovery_link_when_valid(): void
    {
        Coupon::factory()->create([
            'code' => 'SAVE10',
            'is_active' => true,
            'type' => Coupon::TYPE_PERCENT,
            'value' => 10,
        ]);

        $abandonment = CartAbandonment::factory()->create(['email' => 'shopper@example.com']);

        $response = $this->get(route('cart.recover', ['abandonment' => $abandonment->token, 'coupon' => 'save10']));

        $response->assertRedirect(route('checkout.index'));
        $response->assertSessionHas('cart.coupon_code', 'SAVE10');
    }

    public function test_recover_cart_rejects_already_recovered_link(): void
    {
        $abandonment = CartAbandonment::factory()->recovered()->create();

        $response = $this->get(route('cart.recover', $abandonment->token));

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHasErrors('cart');
    }

    public function test_recover_cart_rejects_expired_link(): void
    {
        $abandonment = CartAbandonment::factory()->create([
            'abandoned_at' => now()->subDays(8),
        ]);

        $response = $this->get(route('cart.recover', $abandonment->token));

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHasErrors('cart');
    }

    public function test_send_reminders_command_emails_eligible_abandonments(): void
    {
        Mail::fake();

        CartAbandonment::factory()->count(2)->create();
        CartAbandonment::factory()->reminded()->create();
        CartAbandonment::factory()->recovered()->create();
        CartAbandonment::factory()->recent()->create();

        $this->artisan('app:send-abandoned-cart-reminders')->assertSuccessful();

        Mail::assertQueued(AbandonedCartReminder::class, 2);
    }

    public function test_send_reminders_command_respects_settings_toggle_and_delay(): void
    {
        Mail::fake();

        AbandonedCartSetting::current()->update([
            'is_enabled' => false,
            'delay_minutes' => 180,
        ]);

        CartAbandonment::factory()->create([
            'abandoned_at' => now()->subHours(6),
        ]);

        $this->artisan('app:send-abandoned-cart-reminders')->assertSuccessful();
        Mail::assertNothingQueued();

        AbandonedCartSetting::current()->update([
            'is_enabled' => true,
            'delay_minutes' => 180,
        ]);

        $this->artisan('app:send-abandoned-cart-reminders')->assertSuccessful();
        Mail::assertQueued(AbandonedCartReminder::class, 1);
    }

    public function test_admin_can_update_abandoned_cart_settings(): void
    {
        Coupon::factory()->create(['code' => 'SAVE15']);

        $this->post(route('admin.maintenance.abandoned-cart.update'), [
            'is_enabled' => '1',
            'delay_minutes' => '90',
            'coupon_code' => 'save15',
        ])->assertRedirect(route('admin.maintenance.abandoned-cart.edit'));

        $settings = AbandonedCartSetting::current();

        $this->assertTrue($settings->is_enabled);
        $this->assertSame(90, $settings->delay_minutes);
        $this->assertSame('SAVE15', $settings->coupon_code);
    }

    public function test_send_reminders_command_marks_sent_records(): void
    {
        Mail::fake();

        $abandonment = CartAbandonment::factory()->create();

        $this->artisan('app:send-abandoned-cart-reminders')->assertSuccessful();

        $abandonment->refresh();
        $this->assertNotNull($abandonment->reminder_sent_at);
    }

    public function test_checkout_marks_abandonment_as_recovered(): void
    {
        $abandonment = CartAbandonment::factory()->create(['email' => 'customer@example.com']);

        $product = Product::factory()->create(['name' => 'Hoodie', 'slug' => 'hoodie', 'status' => 'active']);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 50.00,
            'track_inventory' => true,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $this->withSession($this->cartSession($variant))
            ->post(route('checkout.store'), [
                'email' => 'customer@example.com',
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'country' => 'DE',
                'city' => 'Berlin',
                'postal_code' => '10115',
                'street_name' => 'Alexanderplatz',
                'street_number' => '1',
            ]);

        $abandonment->refresh();
        $this->assertNotNull($abandonment->recovered_at);
    }
}
