<?php

use App\Models\AdminActivityLog;
use App\Models\EditHistory;
use App\Models\Order;
use App\Support\DhlTracking;
use Illuminate\Database\Eloquent\Collection;
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
        return ['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled', 'refunded'];
    }

    /** @return list<string> */
    #[Computed]
    public function paymentStatusOptions(): array
    {
        return ['pending', 'paid', 'failed', 'refunded'];
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

        // Clear computed caches so the view reflects the new values
        unset($this->order, $this->dhlTrackingUrl, $this->editHistories);

        $this->tracking_number = $trackingNumber ?? '';

        session()->flash('status', 'Order updated successfully.');
    }
}; ?>

<div>
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold">Order {{ $this->order->order_number }}</h2>
            <p class="text-sm text-slate-600">Placed {{ optional($this->order->placed_at)->format('Y-m-d H:i') ?? '-' }}</p>
        </div>
        <a href="{{ route('admin.orders.index') }}" class="text-sm text-blue-700 hover:underline">Back to orders</a>
    </div>

    @if (session()->has('status'))
        <div class="mb-4 rounded border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-5 grid gap-4 md:grid-cols-4">
        <div class="rounded border border-slate-200 p-3">
            <h3 class="text-sm font-semibold text-slate-800">Customer</h3>
            <p class="mt-2 text-sm">{{ $this->order->first_name }} {{ $this->order->last_name }}</p>
            <p class="text-sm text-slate-600">{{ $this->order->email }}</p>
            <p class="text-sm text-slate-600">{{ $this->order->phone ?: '-' }}</p>
        </div>

        <div class="rounded border border-slate-200 p-3">
            <h3 class="text-sm font-semibold text-slate-800">Shipping Address</h3>
            <p class="mt-2 text-sm text-slate-700">{{ $this->order->street_name }} {{ $this->order->street_number }}</p>
            <p class="text-sm text-slate-700">{{ $this->order->postal_code }} {{ $this->order->city }}</p>
            <p class="text-sm text-slate-700">{{ $this->order->state ?: '-' }}, {{ $this->order->country }}</p>
        </div>

        <div class="rounded border border-slate-200 p-3">
            <h3 class="text-sm font-semibold text-slate-800">Payment</h3>
            <p class="mt-2 text-sm text-slate-700">Method: {{ ucfirst($this->order->payment_method) }}</p>
            <p class="text-sm text-slate-700">Status: {{ ucfirst($this->order->payment_status) }}</p>
            <p class="text-sm text-slate-700">Coupon: {{ $this->order->coupon_code ?: '-' }}</p>
            <p class="text-sm text-slate-700">Discount: ${{ number_format((float) $this->order->discount_total, 2) }}</p>
            <p class="text-sm text-slate-700">PayPal Order: {{ $this->order->paypal_order_id ?: '-' }}</p>
            <p class="text-sm text-slate-700">PayPal Capture: {{ $this->order->paypal_capture_id ?: '-' }}</p>
        </div>

        <div class="rounded border border-slate-200 p-3">
            <h3 class="text-sm font-semibold text-slate-800">Shipment</h3>
            <p class="mt-2 text-sm text-slate-700">Carrier: {{ $this->order->shipping_carrier ? strtoupper($this->order->shipping_carrier) : '-' }}</p>
            <p class="text-sm text-slate-700">Tracking: {{ $this->order->tracking_number ?: '-' }}</p>
            <p class="text-sm text-slate-700">Shipped At: {{ optional($this->order->shipped_at)->format('Y-m-d H:i') ?? '-' }}</p>
            @if ($this->dhlTrackingUrl)
                <a href="{{ $this->dhlTrackingUrl }}" target="_blank" rel="noreferrer" class="mt-2 inline-block text-sm text-blue-700 hover:underline">Track with DHL</a>
            @endif
        </div>
    </div>

    <div class="mb-5 grid gap-3 rounded border border-slate-200 bg-slate-50 p-3 md:grid-cols-4">
        <div>
            <label for="status" class="mb-1 block text-xs font-medium text-slate-700">Order status</label>
            <select id="status" wire:model="status" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                @foreach ($this->statusOptions as $statusOption)
                    <option value="{{ $statusOption }}">{{ ucfirst($statusOption) }}</option>
                @endforeach
            </select>
            @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="payment_status" class="mb-1 block text-xs font-medium text-slate-700">Payment status</label>
            <select id="payment_status" wire:model="payment_status" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                @foreach ($this->paymentStatusOptions as $paymentStatusOption)
                    <option value="{{ $paymentStatusOption }}">{{ ucfirst($paymentStatusOption) }}</option>
                @endforeach
            </select>
            @error('payment_status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="tracking_number" class="mb-1 block text-xs font-medium text-slate-700">DHL tracking number</label>
            <input id="tracking_number" type="text" wire:model="tracking_number" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. 00340434161094000000">
            @error('tracking_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-end">
            <button type="button" wire:click="saveOrder" class="rounded bg-slate-800 px-3 py-2 text-sm text-white hover:bg-slate-900">
                <span wire:loading.remove wire:target="saveOrder">Save Order</span>
                <span wire:loading wire:target="saveOrder">Saving…</span>
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full border border-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="border border-slate-200 px-3 py-2 text-left">Thumb</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Item</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">SKU</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Qty</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Unit</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->order->items as $item)
                    <tr wire:key="item-{{ $item->id }}">
                        <td class="border border-slate-200 px-3 py-2">
                            @php($thumbnailPath = $item->product?->media->first()?->path)
                            @if ($thumbnailPath)
                                <img src="{{ route('media.show', ['path' => $thumbnailPath]) }}" alt="{{ $item->product_name }}" class="h-12 w-12 rounded object-cover">
                            @else
                                <img src="{{ asset('images/placeholder-product.svg') }}" alt="No image available" class="h-12 w-12 rounded object-cover">
                            @endif
                        </td>
                        <td class="border border-slate-200 px-3 py-2">{{ $item->product_name }} @if($item->variant_name)({{ $item->variant_name }})@endif</td>
                        <td class="border border-slate-200 px-3 py-2">{{ $item->sku }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ $item->quantity }}</td>
                        <td class="border border-slate-200 px-3 py-2">${{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="border border-slate-200 px-3 py-2">${{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="border border-slate-200 px-3 py-6 text-center text-slate-500">No order items.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 ml-auto max-w-sm space-y-2">
        <div class="flex items-center justify-between text-sm text-slate-600">
            <p>Subtotal</p>
            <p>${{ number_format((float) $this->order->subtotal, 2) }}</p>
        </div>
        @if ((float) $this->order->discount_total > 0)
            <div class="flex items-center justify-between text-sm text-emerald-700">
                <p>Discount @if ($this->order->coupon_code)( {{ $this->order->coupon_code }} )@endif</p>
                <p>- ${{ number_format((float) $this->order->discount_total, 2) }}</p>
            </div>
        @endif
        <div class="flex items-center justify-between border-t border-slate-200 pt-3">
            <p class="text-lg font-semibold">Grand total</p>
            <p class="text-lg font-semibold">${{ number_format((float) $this->order->total, 2) }}</p>
        </div>
    </div>

    @include('admin.partials.edit-history', ['histories' => $this->editHistories])
</div>
