<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDashboardTest extends TestCase
{
    use RefreshDatabase;

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

        $response = $this->get(route('admin.orders.index'));

        $response->assertOk();
        $response->assertSee($orderA->order_number);
        $response->assertSee($orderB->order_number);
    }

    public function test_admin_orders_index_filters_by_status_and_search(): void
    {
        $matchingOrder = Order::factory()->create([
            'order_number' => 'GG-MATCH-001',
            'email' => 'match@example.com',
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);

        $nonMatchingOrder = Order::factory()->create([
            'order_number' => 'GG-OTHER-999',
            'email' => 'other@example.com',
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $response = $this->get(route('admin.orders.index', [
            'status' => 'processing',
            'q' => 'MATCH',
        ]));

        $response->assertOk();
        $response->assertSee($matchingOrder->order_number);
        $response->assertDontSee($nonMatchingOrder->order_number);
    }

    public function test_admin_order_show_displays_order_items(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'GG-SHOW-123',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Dashboard Hoodie',
            'variant_name' => 'Large',
            'sku' => 'DASH-HOODIE-L',
            'quantity' => 2,
            'unit_price' => 50,
            'line_total' => 100,
        ]);

        $response = $this->get(route('admin.orders.show', $order));

        $response->assertOk();
        $response->assertSee('GG-SHOW-123');
        $response->assertSee('Dashboard Hoodie');
        $response->assertSee('DASH-HOODIE-L');
    }

    public function test_admin_can_update_order_and_payment_status(): void
    {
        $order = Order::factory()->create([
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $response = $this->patch(route('admin.orders.update', $order), [
            'status' => 'shipped',
            'payment_status' => 'paid',
        ]);

        $response->assertRedirect(route('admin.orders.show', $order));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'shipped',
            'payment_status' => 'paid',
        ]);
    }

    public function test_admin_order_update_validates_status_values(): void
    {
        $order = Order::factory()->create();

        $response = $this->from(route('admin.orders.show', $order))
            ->patch(route('admin.orders.update', $order), [
                'status' => 'not-valid',
                'payment_status' => 'wrong',
            ]);

        $response->assertRedirect(route('admin.orders.show', $order));
        $response->assertSessionHasErrors(['status', 'payment_status']);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
        ]);
    }
}
