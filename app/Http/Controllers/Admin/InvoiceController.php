<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateInvoiceSettingsRequest;
use App\Models\IntegrationSetting;
use App\Models\Order;
use App\Support\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    /**
     * Issue an invoice for the order (idempotent — reuses an existing one).
     */
    public function generate(Order $order): RedirectResponse
    {
        if ($order->payment_status !== 'paid') {
            return back()->with('error', 'Invoices can only be issued for paid orders.');
        }

        if (! $this->invoices->settingsComplete()) {
            return redirect()
                ->route('admin.invoices.settings.edit')
                ->with('error', 'Fill in your company details before issuing invoices.');
        }

        $invoice = $this->invoices->generateFor($order);

        return back()->with('status', "Invoice {$invoice->invoice_number} is ready.");
    }

    /**
     * Download the invoice PDF.
     */
    public function download(Order $order): Response|RedirectResponse
    {
        $invoice = $order->invoice;

        if (! $invoice) {
            return back()->with('error', 'This order has no invoice yet.');
        }

        return response($this->invoices->pdfContents($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->invoices->downloadFilename($invoice).'"',
        ]);
    }

    public function editSettings(): View
    {
        return view('admin.maintenance.invoice-settings', [
            'values' => $this->invoices->settings(),
            'complete' => $this->invoices->settingsComplete(),
        ]);
    }

    public function updateSettings(UpdateInvoiceSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        IntegrationSetting::putMany([
            'invoice_company_name' => $validated['invoice_company_name'],
            'invoice_address_line1' => $validated['invoice_address_line1'],
            'invoice_address_line2' => $validated['invoice_address_line2'] ?? '',
            'invoice_postal_code' => $validated['invoice_postal_code'],
            'invoice_city' => $validated['invoice_city'],
            'invoice_country' => $validated['invoice_country'],
            'invoice_email' => $validated['invoice_email'] ?? '',
            'invoice_website' => $validated['invoice_website'] ?? '',
            'invoice_tax_identifier' => $validated['invoice_tax_identifier'],
            'invoice_zero_tax_note' => $validated['invoice_zero_tax_note'],
            'invoice_footer_note' => $validated['invoice_footer_note'] ?? '',
        ]);

        return redirect()
            ->route('admin.invoices.settings.edit')
            ->with('status', 'Invoice settings saved.');
    }
}
