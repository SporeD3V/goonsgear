<?php

namespace Tests\Feature\Mail;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_confirmation_email_contains_order_details(): void
    {
        $product = Product::factory()->create(['name' => 'Test Hoodie']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Medium']);

        $order = Order::factory()->create([
            'order_number' => 'ORD-20260329-0001',
            'email' => 'customer@example.com',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'street_name' => 'Alexanderplatz',
            'street_number' => '1',
            'postal_code' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
            'subtotal' => 120.00,
            'discount_total' => 0,
            'total' => 120.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Test Hoodie',
            'variant_name' => 'Medium',
            'sku' => 'TH-M',
            'unit_price' => 120.00,
            'quantity' => 1,
            'line_total' => 120.00,
        ]);

        $order->load('items');

        $mailable = new OrderConfirmation($order);

        $mailable->assertSeeInHtml('ORD-20260329-0001');
        $mailable->assertSeeInHtml('Max');
        $mailable->assertSeeInHtml('Test Hoodie');
        $mailable->assertSeeInHtml('Medium');
        $mailable->assertSeeInHtml('120.00');
        $mailable->assertSeeInHtml('Berlin');
    }

    public function test_order_confirmation_email_shows_discount_when_coupon_applied(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'ORD-20260329-0002',
            'email' => 'customer@example.com',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'subtotal' => 120.00,
            'discount_total' => 12.00,
            'coupon_code' => 'SAVE10',
            'total' => 108.00,
        ]);

        $order->load('items');

        $mailable = new OrderConfirmation($order);

        $mailable->assertSeeInHtml('SAVE10');
        $mailable->assertSeeInHtml('12.00');
        $mailable->assertSeeInHtml('108.00');
    }

    public function test_order_confirmation_is_queued_on_manual_checkout(): void
    {
        Mail::fake();

        $product = Product::factory()->create(['name' => 'Hoodie', 'slug' => 'hoodie', 'status' => 'active']);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'L',
            'sku' => 'H-L',
            'price' => 50.00,
            'track_inventory' => true,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $this->withSession([
            'cart.items' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => 'Hoodie',
                    'product_slug' => 'hoodie',
                    'variant_name' => 'L',
                    'sku' => 'H-L',
                    'price' => 50.00,
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

        Mail::assertQueued(OrderConfirmation::class, function (OrderConfirmation $mail): bool {
            return $mail->order->email === 'customer@example.com';
        });
    }
}
