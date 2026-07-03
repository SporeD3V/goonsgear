<?php

namespace Tests\Feature;

use App\Models\IntegrationSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountOrderTest extends TestCase
{
    use RefreshDatabase;

    private function orderFor(string $email, array $attributes = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'order_number' => 'GG-'.strtoupper(fake()->unique()->lexify('??????????')),
            'email' => $email,
            'payment_status' => 'paid',
            'status' => 'paid',
            'subtotal' => 60.00,
            'total' => 60.00,
            'placed_at' => now()->subDays(2),
        ], $attributes));

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'MadFace Hoodie',
            'variant_name' => 'L',
            'unit_price' => 30.00,
            'quantity' => 2,
            'line_total' => 60.00,
        ]);

        return $order;
    }

    private function configureInvoiceSettings(): void
    {
        IntegrationSetting::putMany([
            'invoice_company_name' => 'GoonsGear GmbH',
            'invoice_address_line1' => 'Musterstraße 12',
            'invoice_postal_code' => '10115',
            'invoice_city' => 'Berlin',
            'invoice_country' => 'Germany',
            'invoice_tax_identifier' => 'DE123456789',
            'invoice_zero_tax_note' => 'No VAT charged for this delivery.',
        ]);
    }

    public function test_guest_is_redirected_from_order_detail(): void
    {
        $order = $this->orderFor('someone@example.com');

        $this->get(route('account.orders.show', $order))->assertRedirect(route('login'));
    }

    public function test_owner_can_view_their_order_detail(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $order = $this->orderFor('Buyer@Example.com');

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('MadFace Hoodie')
            ->assertSee('60.00')
            ->assertSee('Back to my orders');
    }

    public function test_order_of_another_customer_is_not_found(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        $order = $this->orderFor('someone-else@example.com');

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertNotFound();
    }

    public function test_order_detail_offers_invoice_download_when_issued(): void
    {
        Storage::fake('local');
        $this->configureInvoiceSettings();

        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $order = $this->orderFor('buyer@example.com');
        app(InvoiceService::class)->generateFor($order);

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('Download invoice')
            ->assertSee($order->fresh()->invoice->invoice_number);
    }

    public function test_owner_can_download_their_invoice(): void
    {
        Storage::fake('local');
        $this->configureInvoiceSettings();

        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $order = $this->orderFor('buyer@example.com');
        app(InvoiceService::class)->generateFor($order);

        $response = $this->actingAs($user)->get(route('account.orders.invoice', $order));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_other_customer_cannot_download_invoice(): void
    {
        Storage::fake('local');
        $this->configureInvoiceSettings();

        $user = User::factory()->create(['email' => 'me@example.com']);
        $order = $this->orderFor('someone-else@example.com');
        app(InvoiceService::class)->generateFor($order);

        $this->actingAs($user)
            ->get(route('account.orders.invoice', $order))
            ->assertNotFound();
    }

    public function test_invoice_download_is_not_found_without_invoice(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $order = $this->orderFor('buyer@example.com');

        $this->actingAs($user)
            ->get(route('account.orders.invoice', $order))
            ->assertNotFound();
    }

    public function test_account_orders_list_links_to_order_detail(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $order = $this->orderFor('buyer@example.com');

        $this->actingAs($user)
            ->get(route('account.index'))
            ->assertOk()
            ->assertSee(route('account.orders.show', $order), false);
    }
}
