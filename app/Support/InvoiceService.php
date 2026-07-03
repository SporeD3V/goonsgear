<?php

namespace App\Support;

use App\Models\IntegrationSetting;
use App\Models\Invoice;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    /**
     * Seller fields that must be filled before invoices can be issued
     * (§14 UStG requires the seller's full name, address, and tax number
     * or VAT ID on every invoice).
     *
     * @var list<string>
     */
    private const REQUIRED_SETTINGS = [
        'invoice_company_name',
        'invoice_address_line1',
        'invoice_postal_code',
        'invoice_city',
        'invoice_tax_identifier',
    ];

    /**
     * Seller details as configured on the admin Invoice Settings page.
     *
     * @return array<string, string>
     */
    public function settings(): array
    {
        return [
            'invoice_company_name' => (string) IntegrationSetting::value('invoice_company_name', ''),
            'invoice_address_line1' => (string) IntegrationSetting::value('invoice_address_line1', ''),
            'invoice_address_line2' => (string) IntegrationSetting::value('invoice_address_line2', ''),
            'invoice_postal_code' => (string) IntegrationSetting::value('invoice_postal_code', ''),
            'invoice_city' => (string) IntegrationSetting::value('invoice_city', ''),
            'invoice_country' => (string) IntegrationSetting::value('invoice_country', 'Germany'),
            'invoice_email' => (string) IntegrationSetting::value('invoice_email', ''),
            'invoice_website' => (string) IntegrationSetting::value('invoice_website', ''),
            'invoice_tax_identifier' => (string) IntegrationSetting::value('invoice_tax_identifier', ''),
            'invoice_zero_tax_note' => (string) IntegrationSetting::value(
                'invoice_zero_tax_note',
                'No VAT charged for this delivery.'
            ),
            'invoice_footer_note' => (string) IntegrationSetting::value('invoice_footer_note', ''),
        ];
    }

    /**
     * Whether the legally required seller details are configured.
     */
    public function settingsComplete(): bool
    {
        $settings = $this->settings();

        foreach (self::REQUIRED_SETTINGS as $key) {
            if (trim($settings[$key]) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Issue an invoice for the order, or return the existing one.
     * The invoice number is sequential per year and allocated inside a
     * transaction so concurrent checkouts cannot collide.
     */
    public function generateFor(Order $order): Invoice
    {
        if ($existing = $order->invoice) {
            return $existing;
        }

        if (! $this->settingsComplete()) {
            throw new \RuntimeException(
                'Invoice settings are incomplete. Fill in the company details under Settings → Invoices first.'
            );
        }

        $invoice = DB::transaction(function () use ($order): Invoice {
            $issuedAt = Carbon::now();
            $year = (int) $issuedAt->year;

            $sequence = 1 + (int) Invoice::query()
                ->where('year', $year)
                ->lockForUpdate()
                ->max('sequence');

            $number = sprintf('GG-%d-%05d', $year, $sequence);

            return Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => $number,
                'year' => $year,
                'sequence' => $sequence,
                'issued_at' => $issuedAt,
                'snapshot' => $this->buildSnapshot($order, $number, $issuedAt),
            ]);
        });

        $this->storePdf($invoice);

        return $invoice;
    }

    /**
     * The rendered PDF contents for an invoice — from the stored file when
     * available, otherwise re-rendered from the immutable snapshot.
     */
    public function pdfContents(Invoice $invoice): string
    {
        if ($invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path)) {
            return (string) Storage::disk('local')->get($invoice->pdf_path);
        }

        return $this->storePdf($invoice);
    }

    public function downloadFilename(Invoice $invoice): string
    {
        return "Invoice-{$invoice->invoice_number}.pdf";
    }

    /**
     * Render the PDF from the snapshot and persist it alongside the record.
     */
    private function storePdf(Invoice $invoice): string
    {
        $contents = Pdf::loadView('invoices.pdf', ['snapshot' => $invoice->snapshot])->output();

        $path = "invoices/{$invoice->year}/{$invoice->invoice_number}.pdf";
        Storage::disk('local')->put($path, $contents);

        if ($invoice->pdf_path !== $path) {
            $invoice->forceFill(['pdf_path' => $path])->save();
        }

        return $contents;
    }

    /**
     * Everything the invoice document needs, frozen at issuance so later
     * order or catalog edits can never alter an issued invoice (GoBD).
     *
     * @return array<string, mixed>
     */
    private function buildSnapshot(Order $order, string $number, Carbon $issuedAt): array
    {
        $order->loadMissing('items');
        $settings = $this->settings();

        $taxTotal = (float) ($order->tax_total ?? 0);
        $discounts = (float) $order->discount_total
            + (float) $order->regional_discount_total
            + (float) $order->bundle_discount_total;

        return [
            'invoice_number' => $number,
            'issued_at' => $issuedAt->format('Y-m-d'),
            'seller' => [
                'company_name' => $settings['invoice_company_name'],
                'address_line1' => $settings['invoice_address_line1'],
                'address_line2' => $settings['invoice_address_line2'],
                'postal_code' => $settings['invoice_postal_code'],
                'city' => $settings['invoice_city'],
                'country' => $settings['invoice_country'],
                'email' => $settings['invoice_email'],
                'website' => $settings['invoice_website'],
                'tax_identifier' => $settings['invoice_tax_identifier'],
            ],
            'buyer' => [
                'name' => trim("{$order->first_name} {$order->last_name}"),
                'street' => trim("{$order->street_name} {$order->street_number}"),
                'address_extra' => trim(implode(' ', array_filter([
                    $order->apartment_block,
                    $order->entrance,
                    $order->floor,
                    $order->apartment_number,
                ]))),
                'postal_code' => (string) $order->postal_code,
                'city' => (string) $order->city,
                'state' => (string) ($order->state ?? ''),
                'country' => (string) $order->country,
                'email' => (string) $order->email,
            ],
            'order' => [
                'number' => (string) $order->order_number,
                'placed_at' => $order->placed_at ? Carbon::parse($order->placed_at)->format('Y-m-d') : null,
                'payment_method' => (string) $order->payment_method,
                // §14 UStG requires the date of supply; for goods shipped
                // later this is stated as the shipping date when known.
                'supply_date' => $order->shipped_at
                    ? Carbon::parse($order->shipped_at)->format('Y-m-d')
                    : $issuedAt->format('Y-m-d'),
            ],
            'items' => $order->items->map(fn ($item): array => [
                'name' => (string) $item->product_name,
                'variant' => (string) ($item->variant_name ?? ''),
                'sku' => (string) $item->sku,
                'quantity' => (int) $item->quantity,
                'unit_price' => round((float) $item->unit_price, 2),
                'line_total' => round((float) $item->line_total, 2),
            ])->values()->all(),
            'totals' => [
                'currency' => (string) $order->currency,
                'subtotal' => round((float) $order->subtotal, 2),
                'discount_total' => round($discounts, 2),
                'coupon_code' => (string) ($order->coupon_code ?? ''),
                'shipping_total' => round((float) ($order->shipping_total ?? 0), 2),
                'tax_total' => round($taxTotal, 2),
                'total' => round((float) $order->total, 2),
            ],
            // Recorded-tax mode: show the VAT actually charged on the order;
            // zero-tax orders carry the configurable explanatory note.
            'tax_note' => $taxTotal > 0
                ? 'Total includes VAT of '.number_format($taxTotal, 2).' '.$order->currency.'.'
                : $settings['invoice_zero_tax_note'],
            'footer_note' => $settings['invoice_footer_note'],
        ];
    }
}
