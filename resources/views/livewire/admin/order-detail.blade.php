<?php

use App\Models\AdminActivityLog;
use App\Models\EditHistory;
use App\Models\Order;
use App\Support\DhlTracking;
use App\Support\InvoiceService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $orderId;

    public string $status = '';

    public string $payment_status = '';

    public ?string $tracking_number = '';

    public function mount(int $orderId): void
    {
        $this->orderId = $orderId;

        $order = $this->order;
        $this->status = $order->status;
        $this->payment_status = $order->payment_status;
        $this->tracking_number = $order->tracking_number ?? '';
    }

    /** @return list<string> */
    #[Computed]
    public function statusOptions(): array
    {
        return ['pending', 'paid', 'processing', 'on-hold', 'pre-ordered', 'shipped', 'completed', 'cancelled', 'refunded'];
    }

    /** @return list<string> */
    #[Computed]
    public function paymentStatusOptions(): array
    {
        return ['pending', 'paid', 'failed', 'refunded', 'cancelled'];
    }

    #[Computed]
    public function order(): Order
    {
        return Order::with([
            'items' => fn ($query) => $query->orderBy('id'),
            'items.product.media' => fn ($query) => $query
                ->orderByDesc('is_primary')
                ->orderBy('position')
                ->orderBy('id'),
        ])->findOrFail($this->orderId);
    }

    #[Computed]
    public function dhlTrackingUrl(): ?string
    {
        $order = $this->order;

        if ($order->shipping_carrier !== 'dhl') {
            return null;
        }

        return app(DhlTracking::class)->trackingUrl($order->tracking_number);
    }

    #[Computed]
    public function editHistories(): Collection
    {
        return $this->order->editHistories()
            ->with('user:id,name,email')
            ->latest('id')
            ->limit(20)
            ->get();
    }

    public function saveOrder(): void
    {
        $this->validate([
            'status' => ['required', 'string', \Illuminate\Validation\Rule::in($this->statusOptions)],
            'payment_status' => ['required', 'string', \Illuminate\Validation\Rule::in($this->paymentStatusOptions)],
            'tracking_number' => ['nullable', 'string', 'max:100'],
        ]);

        $order = $this->order;

        $trackingNumber = strtoupper(trim($this->tracking_number ?? ''));
        $trackingNumber = $trackingNumber !== '' ? $trackingNumber : null;
        $shippingCarrier = $trackingNumber !== null ? 'dhl' : null;

        $validated = [
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'tracking_number' => $trackingNumber,
            'shipping_carrier' => $shippingCarrier,
        ];

        if ($trackingNumber !== null && in_array($this->status, ['shipped', 'completed'], true)) {
            $validated['shipped_at'] = $order->shipped_at ?? now();
        }

        if ($trackingNumber === null) {
            $validated['shipped_at'] = null;
        }

        // Record edit history for tracked fields
        foreach (['status', 'payment_status', 'tracking_number', 'shipping_carrier'] as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $oldValue = $order->getOriginal($field);
            $newValue = $validated[$field];
            $oldStr = (string) ($oldValue ?? '');
            $newStr = (string) ($newValue ?? '');

            if ($oldStr !== $newStr) {
                EditHistory::recordChange($order, $field, $oldValue, $newValue);
            }
        }

        $order->update($validated);

        AdminActivityLog::log(
            AdminActivityLog::ACTION_UPDATED,
            $order,
            "Updated order #{$order->order_number}",
        );

        // Auto-issue the invoice when payment lands on a native order.
        // Imported WC orders received their invoices from the old store, so
        // those only get one via the explicit button on this page.
        if ($this->payment_status === 'paid'
            && str_starts_with($order->order_number, 'GG-')
            && $order->invoice()->doesntExist()
            && app(InvoiceService::class)->settingsComplete()) {
            try {
                app(InvoiceService::class)->generateFor($order->refresh());
            } catch (\Throwable $e) {
                Log::error("Invoice auto-generation failed for order #{$order->order_number}: {$e->getMessage()}");
            }
        }

        // Clear computed caches so the view reflects the new values
        unset($this->order, $this->dhlTrackingUrl, $this->editHistories);

        $this->tracking_number = $trackingNumber ?? '';

        session()->flash('status', 'Order updated successfully.');
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold">Order {{ $this->order->order_number }}</h2>
            <p class="text-sm text-stone-600">Placed {{ optional($this->order->placed_at)->format('Y-m-d H:i') ?? '-' }}</p>
        </div>
        <a href="{{ route('admin.orders.index') }}" class="text-sm text-[#36a2eb] hover:underline">&larr; Back to orders</a>
    </div>

    @if (session()->has('status'))
        <div class="rounded border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    {{-- Customer & Order Info --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-stone-700">Order Information</h3>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-stone-200 p-3">
                <h4 class="text-xs font-semibold uppercase text-stone-500">Customer</h4>
                <p class="mt-2 text-sm">{{ $this->order->first_name }} {{ $this->order->last_name }}</p>
                <p class="text-sm text-stone-600">{{ $this->order->email }}</p>
                <p class="text-sm text-stone-600">{{ $this->order->phone ?: '-' }}</p>
            </div>

            <div class="rounded-lg border border-stone-200 p-3">
                <h4 class="text-xs font-semibold uppercase text-stone-500">Shipping Address</h4>
                <p class="mt-2 text-sm text-stone-700">{{ $this->order->street_name }} {{ $this->order->street_number }}</p>
                <p class="text-sm text-stone-700">{{ $this->order->postal_code }} {{ $this->order->city }}</p>
                <p class="text-sm text-stone-700">{{ $this->order->state ?: '-' }}, {{ $this->order->country }}</p>
            </div>

            <div class="rounded-lg border border-stone-200 p-3">
                <h4 class="text-xs font-semibold uppercase text-stone-500">Payment</h4>
                <p class="mt-2 text-sm text-stone-700">Method: {{ ucfirst($this->order->payment_method) }}</p>
                <p class="text-sm text-stone-700">Status: {{ ucfirst($this->order->payment_status) }}</p>
                <p class="text-sm text-stone-700">Coupon: {{ $this->order->coupon_code ?: '-' }}</p>
                <p class="text-sm text-stone-700">Discount: ${{ number_format((float) $this->order->discount_total, 2) }}</p>
                <p class="text-sm text-stone-700">PayPal Order: {{ $this->order->paypal_order_id ?: '-' }}</p>
                <p class="text-sm text-stone-700">PayPal Capture: {{ $this->order->paypal_capture_id ?: '-' }}</p>
            </div>

            <div class="rounded-lg border border-stone-200 p-3">
                <h4 class="text-xs font-semibold uppercase text-stone-500">Shipment</h4>
                <p class="mt-2 text-sm text-stone-700">Carrier: {{ $this->order->shipping_carrier ? strtoupper($this->order->shipping_carrier) : '-' }}</p>
                <p class="text-sm text-stone-700">Tracking: {{ $this->order->tracking_number ?: '-' }}</p>
                <p class="text-sm text-stone-700">Shipped At: {{ optional($this->order->shipped_at)->format('Y-m-d H:i') ?? '-' }}</p>
                @if ($this->dhlTrackingUrl)
                    <a href="{{ $this->dhlTrackingUrl }}" target="_blank" rel="noreferrer" class="mt-2 inline-block text-sm text-[#36a2eb] hover:underline">Track with DHL</a>
                @endif
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-stone-700">Actions</h3>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="status" class="mb-1 block text-xs font-medium text-stone-700">Order status</label>
                <select id="status" wire:model="status" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm">
                    @foreach ($this->statusOptions as $statusOption)
                        <option value="{{ $statusOption }}">{{ ucfirst($statusOption) }}</option>
                    @endforeach
                </select>
                @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="payment_status" class="mb-1 block text-xs font-medium text-stone-700">Payment status</label>
                <select id="payment_status" wire:model="payment_status" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm">
                    @foreach ($this->paymentStatusOptions as $paymentStatusOption)
                        <option value="{{ $paymentStatusOption }}">{{ ucfirst($paymentStatusOption) }}</option>
                    @endforeach
                </select>
                @error('payment_status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="tracking_number" class="mb-1 block text-xs font-medium text-stone-700">DHL tracking number</label>
                <input id="tracking_number" type="text" wire:model="tracking_number" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm" placeholder="e.g. 00340434161094000000">
                @error('tracking_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-end">
                <button type="button" wire:click="saveOrder" class="rounded bg-stone-800 px-3 py-2 text-sm text-white hover:bg-stone-900">
                    <span wire:loading.remove wire:target="saveOrder">Save Order</span>
                    <span wire:loading wire:target="saveOrder">Saving…</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Invoice --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-stone-700">Invoice</h3>
        @if ($this->order->invoice)
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-medium text-stone-800">{{ $this->order->invoice->invoice_number }}</p>
                    <p class="text-sm text-stone-600">Issued {{ $this->order->invoice->issued_at->format('Y-m-d') }}</p>
                </div>
                <a href="{{ route('admin.orders.invoice.download', $this->order) }}"
                   class="inline-flex items-center gap-2 rounded bg-[#36a2eb] px-4 py-2 text-sm font-medium text-white hover:bg-[#2b8ac9]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Download Invoice (PDF)
                </a>
            </div>
        @elseif ($this->order->payment_status === 'paid')
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-stone-600">No invoice has been issued for this order yet.</p>
                <form method="POST" action="{{ route('admin.orders.invoice.generate', $this->order) }}">
                    @csrf
                    <button type="submit" class="rounded bg-[#36a2eb] px-4 py-2 text-sm font-medium text-white hover:bg-[#2b8ac9]">
                        Generate Invoice
                    </button>
                </form>
            </div>
        @else
            <p class="text-sm text-stone-500">An invoice can be issued once this order is marked as paid.</p>
        @endif
    </div>

    @php
        $orderNoteOptions = $this->order->items
            ->map(fn ($item) => [
                'key' => 'order-item::' . $item->id,
                'label' => 'Item - ' . $item->product_name,
                'value' => $item->quantity . ' × €' . number_format((float) $item->price, 2),
                'meta' => [
                    'order_id' => $this->order->id,
                    'order_number' => $this->order->order_number,
                    'order_item_id' => $item->id,
                    'sku' => $item->sku,
                ],
            ])
            ->values()
            ->all();
    @endphp

    @include('admin._page-notes-card', [
        'context' => 'order-' . $this->order->id,
        'label' => 'Order ' . $this->order->order_number,
        'anchorOptions' => $orderNoteOptions,
    ])

    {{-- Order Items --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-stone-700">Order Items</h3>
        <div class="-mx-5 overflow-x-auto px-5">
            <table class="admin-mobile-table min-w-full border border-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="border border-stone-200 px-3 py-2 text-left">Thumb</th>
                        <th class="border border-stone-200 px-3 py-2 text-left">Item</th>
                        <th class="border border-stone-200 px-3 py-2 text-left">SKU</th>
                        <th class="border border-stone-200 px-3 py-2 text-left">Qty</th>
                        <th class="border border-stone-200 px-3 py-2 text-left">Unit</th>
                        <th class="border border-stone-200 px-3 py-2 text-left">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->order->items as $item)
                        <tr wire:key="item-{{ $item->id }}">
                            <td class="border border-stone-200 px-3 py-2">
                                @php($thumbnailPath = $item->product?->media->first()?->path)
                                @if ($thumbnailPath)
                                    <img src="{{ route('media.show', ['path' => $thumbnailPath]) }}" alt="{{ $item->product_name }}" class="h-12 w-12 rounded object-cover">
                                @else
                                    <img src="{{ asset('images/placeholder-product.svg') }}" alt="No image available" class="h-12 w-12 rounded object-cover">
                                @endif
                            </td>
                            <td class="border border-stone-200 px-3 py-2">{{ $item->product_name }} @if($item->variant_name)({{ $item->variant_name }})@endif</td>
                            <td class="border border-stone-200 px-3 py-2">{{ $item->sku }}</td>
                            <td class="border border-stone-200 px-3 py-2">{{ $item->quantity }}</td>
                            <td class="border border-stone-200 px-3 py-2">${{ number_format((float) $item->unit_price, 2) }}</td>
                            <td class="border border-stone-200 px-3 py-2">${{ number_format((float) $item->line_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="border border-stone-200 px-3 py-6 text-center text-stone-500">No order items.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pricing Summary --}}
        <div class="mt-4 ml-auto max-w-sm space-y-2">
            <div class="flex items-center justify-between text-sm text-stone-600">
                <p>Subtotal</p>
                <p>${{ number_format((float) $this->order->subtotal, 2) }}</p>
            </div>
            @if ((float) $this->order->discount_total > 0)
                <div class="flex items-center justify-between text-sm text-emerald-700">
                    <p>Discount @if ($this->order->coupon_code)( {{ $this->order->coupon_code }} )@endif</p>
                    <p>- ${{ number_format((float) $this->order->discount_total, 2) }}</p>
                </div>
            @endif
            @if ((float) $this->order->bundle_discount_total > 0)
                <div class="flex items-center justify-between text-sm text-emerald-700">
                    <p>Bundle Discount @if ($this->order->bundle_sku)( {{ $this->order->bundle_sku }} )@endif</p>
                    <p>- ${{ number_format((float) $this->order->bundle_discount_total, 2) }}</p>
                </div>
            @endif
            <div class="flex items-center justify-between border-t border-stone-200 pt-3">
                <p class="text-lg font-semibold">Grand total</p>
                <p class="text-lg font-semibold">${{ number_format((float) $this->order->total, 2) }}</p>
            </div>
        </div>
    </div>

    {{-- Edit History --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
        @include('admin.partials.edit-history', ['histories' => $this->editHistories])
    </div>
</div>

