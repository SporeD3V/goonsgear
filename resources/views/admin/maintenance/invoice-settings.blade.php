@extends('admin.layout')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Invoice Settings</h2>
            <a href="{{ route('admin.orders.index') }}" class="text-sm text-blue-700 hover:underline">Back to Orders</a>
        </div>

        @if (session('status'))
            <div class="rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @if (! $complete)
            <div class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <strong>Almost there:</strong> fill in the fields marked with * below. Invoices can be issued once they are saved —
                these details appear on every invoice and are required by German law.
            </div>
        @endif

        <p class="text-sm text-slate-600">
            These details are printed on every invoice. Once an invoice is issued it never changes,
            even if you update these settings later — new settings only apply to future invoices.
        </p>

        <form method="POST" action="{{ route('admin.invoices.settings.update') }}" class="grid gap-6">
            @csrf

            <section class="grid gap-3 rounded border border-slate-200 bg-white p-4">
                <h3 class="text-base font-semibold text-slate-900">Your company</h3>

                <div>
                    <label for="invoice_company_name" class="mb-1 block text-sm font-medium text-slate-700">Company name *</label>
                    <input id="invoice_company_name" name="invoice_company_name" type="text" value="{{ old('invoice_company_name', $values['invoice_company_name']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    @error('invoice_company_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label for="invoice_address_line1" class="mb-1 block text-sm font-medium text-slate-700">Street and number *</label>
                        <input id="invoice_address_line1" name="invoice_address_line1" type="text" value="{{ old('invoice_address_line1', $values['invoice_address_line1']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        @error('invoice_address_line1')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="invoice_address_line2" class="mb-1 block text-sm font-medium text-slate-700">Address extra (optional)</label>
                        <input id="invoice_address_line2" name="invoice_address_line2" type="text" value="{{ old('invoice_address_line2', $values['invoice_address_line2']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <label for="invoice_postal_code" class="mb-1 block text-sm font-medium text-slate-700">Postal code *</label>
                        <input id="invoice_postal_code" name="invoice_postal_code" type="text" value="{{ old('invoice_postal_code', $values['invoice_postal_code']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        @error('invoice_postal_code')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="invoice_city" class="mb-1 block text-sm font-medium text-slate-700">City *</label>
                        <input id="invoice_city" name="invoice_city" type="text" value="{{ old('invoice_city', $values['invoice_city']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        @error('invoice_city')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="invoice_country" class="mb-1 block text-sm font-medium text-slate-700">Country *</label>
                        <input id="invoice_country" name="invoice_country" type="text" value="{{ old('invoice_country', $values['invoice_country']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        @error('invoice_country')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label for="invoice_email" class="mb-1 block text-sm font-medium text-slate-700">Contact email (optional)</label>
                        <input id="invoice_email" name="invoice_email" type="email" value="{{ old('invoice_email', $values['invoice_email']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        @error('invoice_email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="invoice_website" class="mb-1 block text-sm font-medium text-slate-700">Website (optional)</label>
                        <input id="invoice_website" name="invoice_website" type="text" value="{{ old('invoice_website', $values['invoice_website']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
            </section>

            <section class="grid gap-3 rounded border border-slate-200 bg-white p-4">
                <h3 class="text-base font-semibold text-slate-900">Tax details</h3>

                <div>
                    <label for="invoice_tax_identifier" class="mb-1 block text-sm font-medium text-slate-700">Tax number or VAT ID *</label>
                    <input id="invoice_tax_identifier" name="invoice_tax_identifier" type="text" value="{{ old('invoice_tax_identifier', $values['invoice_tax_identifier']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm md:max-w-sm" placeholder="e.g. DE123456789">
                    <p class="mt-1 text-xs text-slate-500">Your Steuernummer or USt-IdNr. — required on every German invoice.</p>
                    @error('invoice_tax_identifier')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="invoice_zero_tax_note" class="mb-1 block text-sm font-medium text-slate-700">Note for orders without VAT *</label>
                    <input id="invoice_zero_tax_note" name="invoice_zero_tax_note" type="text" value="{{ old('invoice_zero_tax_note', $values['invoice_zero_tax_note']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Printed on invoices where no VAT was charged. Ask your accountant for the exact wording that applies to you.</p>
                    @error('invoice_zero_tax_note')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="invoice_footer_note" class="mb-1 block text-sm font-medium text-slate-700">Footer note (optional)</label>
                    <textarea id="invoice_footer_note" name="invoice_footer_note" rows="2" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">{{ old('invoice_footer_note', $values['invoice_footer_note']) }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Shown at the bottom of every invoice — e.g. a thank-you line or bank details.</p>
                </div>
            </section>

            <div>
                <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save settings</button>
            </div>
        </form>
    </div>
@endsection
