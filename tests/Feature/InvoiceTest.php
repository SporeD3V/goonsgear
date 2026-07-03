<?php

namespace Tests\Feature;

use App\Models\IntegrationSetting;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
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

    private function paidOrder(array $attributes = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'order_number' => 'GG-'.strtoupper(fake()->unique()->lexify('??????????')),
            'payment_status' => 'paid',
            'status' => 'paid',
            'subtotal' => 80.00,
            'total' => 80.00,
            'placed_at' => now()->subDay(),
        ], $attributes));

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Snowgoons Hoodie',
            'sku' => 'SG-HOOD-L',
            'unit_price' => 40.00,
            'quantity' => 2,
            'line_total' => 80.00,
        ]);

        return $order;
    }

    public function test_invoice_settings_page_requires_admin(): void
    {
        $this->get(route('admin.invoices.settings.edit'))->assertRedirect();

        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->get(route('admin.invoices.settings.edit'))->assertForbidden();
    }

    public function test_invoice_settings_can_be_saved(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.invoices.settings.update'), [
            'invoice_company_name' => 'GoonsGear GmbH',
            'invoice_address_line1' => 'Musterstraße 12',
            'invoice_postal_code' => '10115',
            'invoice_city' => 'Berlin',
            'invoice_country' => 'Germany',
            'invoice_tax_identifier' => 'DE123456789',
            'invoice_zero_tax_note' => 'No VAT charged for this delivery.',
        ])->assertRedirect(route('admin.invoices.settings.edit'));

        $this->assertSame('GoonsGear GmbH', IntegrationSetting::value('invoice_company_name'));
        $this->assertTrue(app(InvoiceService::class)->settingsComplete());
    }

    public function test_invoice_settings_require_mandatory_fields(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.invoices.settings.update'), [
            'invoice_company_name' => '',
        ])->assertSessionHasErrors(['invoice_company_name', 'invoice_address_line1', 'invoice_tax_identifier']);
    }

    public function test_generate_creates_sequential_invoice_numbers(): void
    {
        $this->configureInvoiceSettings();
        $service = app(InvoiceService::class);

        $year = now()->year;
        $first = $service->generateFor($this->paidOrder());
        $second = $service->generateFor($this->paidOrder());

        $this->assertSame(sprintf('GG-%d-00001', $year), $first->invoice_number);
        $this->assertSame(sprintf('GG-%d-00002', $year), $second->invoice_number);
        $this->assertSame(1, $first->sequence);
        $this->assertSame(2, $second->sequence);
    }

    public function test_generate_is_idempotent_per_order(): void
    {
        $this->configureInvoiceSettings();
        $service = app(InvoiceService::class);
        $order = $this->paidOrder();

        $first = $service->generateFor($order);
        $second = $service->generateFor($order->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Invoice::count());
    }

    public function test_generate_fails_without_settings(): void
    {
        $this->expectException(\RuntimeException::class);

        app(InvoiceService::class)->generateFor($this->paidOrder());
    }

    public function test_snapshot_contains_totals_and_zero_tax_note(): void
    {
        $this->configureInvoiceSettings();

        $invoice = app(InvoiceService::class)->generateFor($this->paidOrder());
        $snapshot = $invoice->snapshot;

        // JSON casting may store whole floats as ints — compare numerically.
        $this->assertEqualsWithDelta(80.00, $snapshot['totals']['subtotal'], 0.001);
        $this->assertEqualsWithDelta(80.00, $snapshot['totals']['total'], 0.001);
        $this->assertEqualsWithDelta(0.0, $snapshot['totals']['tax_total'], 0.001);
        $this->assertSame('No VAT charged for this delivery.', $snapshot['tax_note']);
        $this->assertSame('GoonsGear GmbH', $snapshot['seller']['company_name']);
        $this->assertCount(1, $snapshot['items']);
        $this->assertSame(2, $snapshot['items'][0]['quantity']);
    }

    public function test_snapshot_notes_included_vat_when_tax_recorded(): void
    {
        $this->configureInvoiceSettings();

        $order = $this->paidOrder();
        $order->forceFill(['tax_total' => 12.77])->save();

        $invoice = app(InvoiceService::class)->generateFor($order->fresh());

        $this->assertEqualsWithDelta(12.77, $invoice->snapshot['totals']['tax_total'], 0.001);
        $this->assertStringContainsString('includes VAT of 12.77', $invoice->snapshot['tax_note']);
    }

    public function test_snapshot_is_immutable_after_order_changes(): void
    {
        $this->configureInvoiceSettings();
        $order = $this->paidOrder();

        $invoice = app(InvoiceService::class)->generateFor($order);

        $order->update(['first_name' => 'Changed', 'total' => 999]);
        $invoice->refresh();

        $this->assertNotSame('Changed', $invoice->snapshot['buyer']['name']);
        $this->assertEqualsWithDelta(80.00, $invoice->snapshot['totals']['total'], 0.001);
    }

    public function test_admin_can_generate_and_download_invoice(): void
    {
        $this->configureInvoiceSettings();
        $this->actingAsAdmin();
        $order = $this->paidOrder();

        $this->post(route('admin.orders.invoice.generate', $order))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNotNull($order->fresh()->invoice);

        $response = $this->get(route('admin.orders.invoice.download', $order));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_generate_rejects_unpaid_orders(): void
    {
        $this->configureInvoiceSettings();
        $this->actingAsAdmin();

        $order = $this->paidOrder(['payment_status' => 'pending']);

        $this->post(route('admin.orders.invoice.generate', $order))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Invoice::count());
    }

    public function test_generate_redirects_to_settings_when_incomplete(): void
    {
        $this->actingAsAdmin();
        $order = $this->paidOrder();

        $this->post(route('admin.orders.invoice.generate', $order))
            ->assertRedirect(route('admin.invoices.settings.edit'));

        $this->assertSame(0, Invoice::count());
    }

    public function test_download_requires_admin(): void
    {
        $this->configureInvoiceSettings();
        $order = $this->paidOrder();
        app(InvoiceService::class)->generateFor($order);

        $this->get(route('admin.orders.invoice.download', $order))->assertRedirect();
    }

    public function test_marking_order_paid_in_admin_auto_generates_invoice(): void
    {
        $this->configureInvoiceSettings();
        $this->actingAsAdmin();

        $order = $this->paidOrder(['payment_status' => 'pending', 'status' => 'pending']);

        Livewire::test('admin.order-detail', ['orderId' => $order->id])
            ->set('payment_status', 'paid')
            ->set('status', 'paid')
            ->call('saveOrder');

        $this->assertNotNull($order->fresh()->invoice);
    }

    public function test_marking_imported_wc_order_paid_does_not_auto_generate(): void
    {
        $this->configureInvoiceSettings();
        $this->actingAsAdmin();

        $order = $this->paidOrder([
            'order_number' => 'WC-12345',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);

        Livewire::test('admin.order-detail', ['orderId' => $order->id])
            ->set('payment_status', 'paid')
            ->set('status', 'paid')
            ->call('saveOrder');

        $this->assertNull($order->fresh()->invoice);
    }

    public function test_pdf_is_rendered_from_snapshot_when_file_missing(): void
    {
        $this->configureInvoiceSettings();
        $order = $this->paidOrder();
        $service = app(InvoiceService::class);

        $invoice = $service->generateFor($order);
        Storage::disk('local')->delete($invoice->pdf_path);

        $contents = $service->pdfContents($invoice->fresh());

        $this->assertStringStartsWith('%PDF', $contents);
        Storage::disk('local')->assertExists($invoice->fresh()->pdf_path);
    }
}
