<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    public function test_admin_orders_index_lists_orders(): void
    {
        $orderA = Order::factory()->create([
            'order_number' => 'GG-ORDER-A',
            'email' => 'a@example.com',
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $orderB = Order::factory()->create([
            'order_number' => 'GG-ORDER-B',
            'email' => 'b@example.com',
            'status' => 'paid',
            'payment_status' => 'paid',
        ]);

        Livewire::test('admin.order-manager')
            ->assertSee($orderA->order_number)
            ->assertSee($orderB->order_number);
    }

    public function test_admin_orders_index_filters_by_status_and_search(): void
    {
        $matchingOrder = Order::factory()->create([
            'order_number' => 'GG-MATCH-001',
            'email' => 'match@example.com',
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);

        Order::factory()->create([
            'order_number' => 'GG-OTHER-999',
            'email' => 'other@example.com',
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        Livewire::test('admin.order-manager')
            ->set('filterStatus', 'processing')
            ->set('search', 'MATCH')
            ->assertSee($matchingOrder->order_number)
            ->assertDontSee('GG-OTHER-999');
    }

    public function test_admin_order_show_displays_order_items(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'GG-SHOW-123',
        ]);

        $product = Product::factory()->create();

        ProductMedia::factory()->create([
            'product_id' => $product->id,
            'path' => 'products/dashboard/gallery/thumb.avif',
            'is_primary' => true,
            'position' => 0,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Dashboard Hoodie',
            'variant_name' => 'Large',
            'sku' => 'DASH-HOODIE-L',
            'quantity' => 2,
            'unit_price' => 50,
            'line_total' => 100,
        ]);

        Livewire::test('admin.order-detail', ['orderId' => $order->id])
            ->assertSee('GG-SHOW-123')
            ->assertSee('Dashboard Hoodie')
            ->assertSee('DASH-HOODIE-L');
    }

    public function test_admin_can_update_order_payment_and_dhl_tracking(): void
    {
        $order = Order::factory()->create([
            'status' => 'pending',
            'payment_status' => 'pending',
            'tracking_number' => null,
            'shipping_carrier' => null,
            'shipped_at' => null,
        ]);

        Livewire::test('admin.order-detail', ['orderId' => $order->id])
            ->set('status', 'shipped')
            ->set('payment_status', 'paid')
            ->set('tracking_number', '00340434161094000000')
            ->call('saveOrder');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'shipped',
            'payment_status' => 'paid',
            'shipping_carrier' => 'dhl',
            'tracking_number' => '00340434161094000000',
        ]);

        $this->assertNotNull($order->fresh()?->shipped_at);
    }

    public function test_admin_order_show_displays_dhl_tracking_link_when_present(): void
    {
        $order = Order::factory()->create([
            'tracking_number' => '00340434161094000000',
            'shipping_carrier' => 'dhl',
            'shipped_at' => now(),
        ]);

        Livewire::test('admin.order-detail', ['orderId' => $order->id])
            ->assertSee('Track with DHL');
    }

    public function test_admin_order_update_validates_status_values(): void
    {
        $order = Order::factory()->create();

        Livewire::test('admin.order-detail', ['orderId' => $order->id])
            ->set('status', 'not-valid')
            ->set('payment_status', 'wrong')
            ->call('saveOrder')
            ->assertHasErrors(['status', 'payment_status']);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
        ]);
    }
}
