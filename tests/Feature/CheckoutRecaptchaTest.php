<?php

namespace Tests\Feature;

use App\Models\IntegrationSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutRecaptchaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{product: Product, variant: ProductVariant}
     */
    private function createCheckoutFixture(): array
    {
        $product = Product::factory()->create([
            'name' => 'Recaptcha Hoodie',
            'slug' => 'recaptcha-hoodie',
            'status' => 'active',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Large',
            'sku' => 'RC-HOODIE-L',
            'price' => 99.00,
            'track_inventory' => true,
            'stock_quantity' => 4,
            'allow_backorder' => false,
            'is_active' => true,
        ]);

        return [
            'product' => $product,
            'variant' => $variant,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validCheckoutPayload(): array
    {
        return [
            'email' => 'customer@example.com',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'phone' => '+49123456789',
            'country' => 'DE',
            'state' => 'BE',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'street_name' => 'Alexanderplatz',
            'street_number' => '1',
            'recaptcha_token' => 'token-123',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutSession(ProductVariant $variant, Product $product): array
    {
        return [
            'cart.items' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => 'Recaptcha Hoodie',
                    'product_slug' => 'recaptcha-hoodie',
                    'variant_name' => 'Large',
                    'sku' => 'RC-HOODIE-L',
                    'price' => 99.00,
                    'quantity' => 2,
                    'max_quantity' => 4,
                    'image' => null,
                    'url' => route('shop.show', $product),
                ],
            ],
        ];
    }

    public function test_checkout_is_rejected_when_recaptcha_verification_fails(): void
    {
        IntegrationSetting::putMany([
            'recaptcha_enabled' => '1',
            'recaptcha_secret_key' => 'recaptcha-secret',
            'recaptcha_min_score' => '0.5',
            'recaptcha_trigger_after_attempts' => '0',
        ]);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => false,
                'score' => 0.1,
                'action' => 'checkout',
            ], 200),
        ]);

        $fixture = $this->createCheckoutFixture();

        $response = $this->withSession($this->checkoutSession($fixture['variant'], $fixture['product']))
            ->post(route('checkout.store'), $this->validCheckoutPayload());

        $response->assertSessionHasErrors('recaptcha_token');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_succeeds_when_recaptcha_verification_passes(): void
    {
        IntegrationSetting::putMany([
            'recaptcha_enabled' => '1',
            'recaptcha_secret_key' => 'recaptcha-secret',
            'recaptcha_min_score' => '0.5',
            'recaptcha_trigger_after_attempts' => '0',
        ]);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'checkout',
            ], 200),
        ]);

        $fixture = $this->createCheckoutFixture();

        $response = $this->withSession($this->checkoutSession($fixture['variant'], $fixture['product']))
            ->post(route('checkout.store'), $this->validCheckoutPayload());

        $order = Order::query()->latest('id')->first();

        $this->assertNotNull($order);
        $response->assertRedirect(route('checkout.success', $order));
    }

    public function test_paypal_create_order_returns_validation_error_when_recaptcha_fails(): void
    {
        IntegrationSetting::putMany([
            'recaptcha_enabled' => '1',
            'recaptcha_secret_key' => 'recaptcha-secret',
            'recaptcha_min_score' => '0.5',
            'recaptcha_trigger_after_attempts' => '0',
        ]);

        config()->set('services.paypal.client_id', 'test-client-id');
        config()->set('services.paypal.client_secret', 'test-client-secret');
        config()->set('services.paypal.base_url', 'https://api-m.sandbox.paypal.com');

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => false,
                'score' => 0.1,
                'action' => 'checkout',
            ], 200),
            'https://api-m.sandbox.paypal.com/*' => Http::response([
                'id' => 'PAYPAL-ORDER-1',
            ], 200),
        ]);

        $fixture = $this->createCheckoutFixture();

        $response = $this->withSession($this->checkoutSession($fixture['variant'], $fixture['product']))
            ->postJson(route('checkout.paypal.create-order'), $this->validCheckoutPayload());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('recaptcha_token');
    }
}
