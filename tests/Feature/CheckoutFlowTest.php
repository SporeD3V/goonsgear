<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{product: Product, variant: ProductVariant}
     */
    private function createCheckoutFixture(): array
    {
        $product = Product::factory()->create([
            'name' => 'Checkout Hoodie',
            'slug' => 'checkout-hoodie',
            'status' => 'active',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Large',
            'sku' => 'CO-HOODIE-L',
            'price' => 120.00,
            'track_inventory' => true,
            'stock_quantity' => 5,
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
        ];
    }

    public function test_checkout_page_requires_non_empty_cart(): void
    {
        $response = $this->get(route('checkout.index'));

        $response->assertRedirect(route('cart.index'));
    }

    public function test_checkout_page_displays_summary_for_cart_items(): void
    {
        $fixture = $this->createCheckoutFixture();
        $variant = $fixture['variant'];

        $response = $this->withSession([
            'cart.items' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => 'Checkout Hoodie',
                    'product_slug' => 'checkout-hoodie',
                    'variant_name' => 'Large',
                    'sku' => 'CO-HOODIE-L',
                    'price' => 120.00,
                    'quantity' => 2,
                    'max_quantity' => 5,
                    'image' => '/media/products/checkout-hoodie/main.webp',
                    'url' => route('shop.show', $fixture['product']),
                ],
            ],
        ])->get(route('checkout.index'));

        $response->assertOk();
        $response->assertSee('Checkout Hoodie');
        $response->assertSee('/media/products/checkout-hoodie/main.webp');
        $response->assertSee('$240.00');
    }

    public function test_checkout_creates_order_and_clears_cart(): void
    {
        $fixture = $this->createCheckoutFixture();
        $variant = $fixture['variant'];

        $response = $this->withSession([
            'cart.items' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => 'Checkout Hoodie',
                    'product_slug' => 'checkout-hoodie',
                    'variant_name' => 'Large',
                    'sku' => 'CO-HOODIE-L',
                    'price' => 120.00,
                    'quantity' => 2,
                    'max_quantity' => 5,
                    'image' => null,
                    'url' => route('shop.show', $fixture['product']),
                ],
            ],
        ])->post(route('checkout.store'), $this->validCheckoutPayload());

        $order = Order::query()->latest('id')->first();

        $this->assertNotNull($order);
        $this->assertSame('customer@example.com', $order?->email);
        $this->assertSame('DE', $order?->country);
        $this->assertSame('BE', $order?->state);
        $this->assertCount(1, $order?->items ?? []);
        $this->assertSame('CO-HOODIE-L', $order?->items->first()?->sku);
        $this->assertSame('240.00', $order?->total);

        $response->assertRedirect(route('checkout.success', $order));
        $response->assertSessionMissing('cart.items');

        $variant->refresh();
        $this->assertSame(3, $variant->stock_quantity);
    }

    public function test_checkout_rejects_when_cart_quantity_exceeds_current_stock(): void
    {
        $fixture = $this->createCheckoutFixture();
        $variant = $fixture['variant'];

        $response = $this->withSession([
            'cart.items' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => 'Checkout Hoodie',
                    'product_slug' => 'checkout-hoodie',
                    'variant_name' => 'Large',
                    'sku' => 'CO-HOODIE-L',
                    'price' => 120.00,
                    'quantity' => 6,
                    'max_quantity' => 6,
                    'image' => null,
                    'url' => route('shop.show', $fixture['product']),
                ],
            ],
        ])->post(route('checkout.store'), $this->validCheckoutPayload());

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHasErrors('cart');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_requires_valid_input(): void
    {
        $fixture = $this->createCheckoutFixture();
        $variant = $fixture['variant'];

        $response = $this->withSession([
            'cart.items' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => 'Checkout Hoodie',
                    'product_slug' => 'checkout-hoodie',
                    'variant_name' => 'Large',
                    'sku' => 'CO-HOODIE-L',
                    'price' => 120.00,
                    'quantity' => 1,
                    'max_quantity' => 5,
                    'image' => null,
                    'url' => route('shop.show', $fixture['product']),
                ],
            ],
        ])->post(route('checkout.store'), [
            'email' => 'not-an-email',
            'first_name' => '',
            'last_name' => '',
            'country' => 'DEU',
            'city' => '',
            'postal_code' => '',
            'street_name' => '',
            'street_number' => '',
        ]);

        $response->assertSessionHasErrors([
            'email',
            'first_name',
            'last_name',
            'country',
            'city',
            'postal_code',
            'street_name',
            'street_number',
        ]);
    }
}
