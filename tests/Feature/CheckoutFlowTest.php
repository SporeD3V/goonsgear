<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
        $response->assertSee('&euro;240.00', false);
    }

    public function test_checkout_prefills_saved_delivery_address_for_authenticated_user(): void
    {
        $fixture = $this->createCheckoutFixture();
        $variant = $fixture['variant'];

        $user = User::factory()->create([
            'name' => 'Jane Shopper',
            'email' => 'jane@example.com',
            'delivery_phone' => '+49111111111',
            'delivery_country' => 'DE',
            'delivery_state' => 'BE',
            'delivery_city' => 'Berlin',
            'delivery_postal_code' => '10115',
            'delivery_street_name' => 'Saved Street',
            'delivery_street_number' => '22',
        ]);

        $response = $this->actingAs($user)->withSession([
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
        ])->get(route('checkout.index'));

        $response->assertOk();
        $response->assertSee('value="jane@example.com"', false);
        $response->assertSee('value="+49111111111"', false);
        $response->assertSee('value="Saved Street"', false);
        $response->assertSee('value="22"', false);
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
        $this->assertSame('manual', $order?->payment_method);
        $this->assertSame('pending', $order?->payment_status);
        $this->assertCount(1, $order?->items ?? []);
        $this->assertSame('CO-HOODIE-L', $order?->items->first()?->sku);
        $this->assertSame('240.00', $order?->total);

        $response->assertRedirect(route('checkout.success', $order));
        $response->assertSessionMissing('cart.items');

        $variant->refresh();
        $this->assertSame(3, $variant->stock_quantity);
    }

    public function test_checkout_persists_delivery_address_to_authenticated_user_profile(): void
    {
        $fixture = $this->createCheckoutFixture();
        $variant = $fixture['variant'];
        $user = User::factory()->create();

        $this->actingAs($user)->withSession([
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
            ...$this->validCheckoutPayload(),
            'phone' => '+49222222222',
            'city' => 'Hamburg',
            'street_name' => 'Checkout Street',
            'street_number' => '77',
        ]);

        $user->refresh();

        $this->assertSame('+49222222222', $user->delivery_phone);
        $this->assertSame('DE', $user->delivery_country);
        $this->assertSame('Hamburg', $user->delivery_city);
        $this->assertSame('Checkout Street', $user->delivery_street_name);
        $this->assertSame('77', $user->delivery_street_number);
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

    public function test_checkout_applies_coupon_discount_to_order_totals(): void
    {
        $fixture = $this->createCheckoutFixture();
        $variant = $fixture['variant'];

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
            'cart.coupon_code' => 'SAVE10',
        ])->post(route('checkout.store'), $this->validCheckoutPayload());

        $order = Order::query()->latest('id')->first();

        $this->assertNotNull($order);
        $this->assertSame('SAVE10', $order?->coupon_code);
        $this->assertSame('24.00', $order?->discount_total);
        $this->assertSame('216.00', $order?->total);

        $response->assertRedirect(route('checkout.success', $order));
        $response->assertSessionMissing('cart.coupon_code');
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

    public function test_success_page_displays_product_thumbnail_for_order_items(): void
    {
        Storage::fake('public');

        $fixture = $this->createCheckoutFixture();
        $product = $fixture['product'];

        $order = Order::factory()->create([
            'status' => 'paid',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $fixture['variant']->id,
            'product_name' => $product->name,
            'variant_name' => $fixture['variant']->name,
            'sku' => $fixture['variant']->sku,
            'unit_price' => 120.00,
            'quantity' => 1,
            'line_total' => 120.00,
        ]);

        $media = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'disk' => 'public',
            'path' => 'products/checkout-hoodie/gallery/main.webp',
            'mime_type' => 'image/webp',
            'is_primary' => true,
            'position' => 0,
        ]);

        Storage::disk('public')->put($media->getThumbnailPath(), 'checkout-thumb-bytes');

        $response = $this->get(route('checkout.success', $order));

        $response->assertOk();
        $response->assertSee(route('media.show', ['path' => $media->getThumbnailPath()]));
    }
}
