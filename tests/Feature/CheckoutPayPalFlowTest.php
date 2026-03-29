<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutPayPalFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{product: Product, variant: ProductVariant}
     */
    private function createCheckoutFixture(): array
    {
        $product = Product::factory()->create([
            'name' => 'PayPal Hoodie',
            'slug' => 'paypal-hoodie',
            'status' => 'active',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Large',
            'sku' => 'PP-HOODIE-L',
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
            'email' => 'paypal@example.com',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '+49123456789',
            'country' => 'DE',
            'state' => 'BE',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'street_name' => 'Alexanderplatz',
            'street_number' => '1',
        ];
    }

    public function test_paypal_create_order_endpoint_returns_order_id(): void
    {
        config()->set('services.paypal.client_id', 'test-client-id');
        config()->set('services.paypal.client_secret', 'test-client-secret');
        config()->set('services.paypal.base_url', 'https://api-m.sandbox.paypal.com');

        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL-ORDER-1',
            ]),
        ]);

        $fixture = $this->createCheckoutFixture();
        $variant = $fixture['variant'];

        $session = [
            'cart.items' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => 'PayPal Hoodie',
                    'product_slug' => 'paypal-hoodie',
                    'variant_name' => 'Large',
                    'sku' => 'PP-HOODIE-L',
                    'price' => 99.00,
                    'quantity' => 2,
                    'max_quantity' => 4,
                    'image' => null,
                    'url' => route('shop.show', $fixture['product']),
                ],
            ],
        ];

        $createOrderResponse = $this->withSession($session)
            ->postJson(route('checkout.paypal.create-order'), $this->validCheckoutPayload());

        $createOrderResponse->assertOk();
        $createOrderResponse->assertJsonPath('id', 'PAYPAL-ORDER-1');
    }

    public function test_paypal_capture_endpoint_creates_paid_order_and_updates_stock(): void
    {
        config()->set('services.paypal.client_id', 'test-client-id');
        config()->set('services.paypal.client_secret', 'test-client-secret');
        config()->set('services.paypal.base_url', 'https://api-m.sandbox.paypal.com');

        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-ORDER-1/capture' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [
                    [
                        'payments' => [
                            'captures' => [
                                ['id' => 'CAPTURE-1'],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $fixture = $this->createCheckoutFixture();
        $variant = $fixture['variant'];

        $pendingPayload = $this->validCheckoutPayload();
        $pendingItems = [
            [
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'product_name' => 'PayPal Hoodie',
                'variant_name' => 'Large',
                'sku' => 'PP-HOODIE-L',
                'unit_price' => 99.00,
                'quantity' => 2,
                'line_total' => 198.00,
            ],
        ];

        $captureResponse = $this->withSession([
            'cart.items' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => 'PayPal Hoodie',
                    'product_slug' => 'paypal-hoodie',
                    'variant_name' => 'Large',
                    'sku' => 'PP-HOODIE-L',
                    'price' => 99.00,
                    'quantity' => 2,
                    'max_quantity' => 4,
                    'image' => null,
                    'url' => route('shop.show', $fixture['product']),
                ],
            ],
            'checkout.paypal.pending_orders.PAYPAL-ORDER-1' => [
                'payload' => $pendingPayload,
                'normalized_items' => $pendingItems,
                'subtotal' => 198.00,
            ],
        ])->postJson(route('checkout.paypal.capture-order'), [
            'paypal_order_id' => 'PAYPAL-ORDER-1',
        ]);

        $captureResponse->assertOk();

        $order = Order::query()->latest('id')->first();

        $this->assertNotNull($order);
        $this->assertSame('paypal', $order?->payment_method);
        $this->assertSame('paid', $order?->payment_status);
        $this->assertSame('PAYPAL-ORDER-1', $order?->paypal_order_id);
        $this->assertSame('CAPTURE-1', $order?->paypal_capture_id);
        $this->assertSame('paid', $order?->status);

        $variant->refresh();
        $this->assertSame(2, $variant->stock_quantity);
    }
}
