@extends('admin.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold">Order {{ $order->order_number }}</h2>
            <p class="text-sm text-slate-600">Placed {{ optional($order->placed_at)->format('Y-m-d H:i') ?? '-' }}</p>
        </div>
        <a href="{{ route('admin.orders.index') }}" class="text-sm text-blue-700 hover:underline">Back to orders</a>
    </div>

    <div class="mb-5 grid gap-4 md:grid-cols-3">
        <div class="rounded border border-slate-200 p-3">
            <h3 class="text-sm font-semibold text-slate-800">Customer</h3>
            <p class="mt-2 text-sm">{{ $order->first_name }} {{ $order->last_name }}</p>
            <p class="text-sm text-slate-600">{{ $order->email }}</p>
            <p class="text-sm text-slate-600">{{ $order->phone ?: '-' }}</p>
        </div>

        <div class="rounded border border-slate-200 p-3">
            <h3 class="text-sm font-semibold text-slate-800">Shipping Address</h3>
            <p class="mt-2 text-sm text-slate-700">{{ $order->street_name }} {{ $order->street_number }}</p>
            <p class="text-sm text-slate-700">{{ $order->postal_code }} {{ $order->city }}</p>
            <p class="text-sm text-slate-700">{{ $order->state ?: '-' }}, {{ $order->country }}</p>
        </div>

        <div class="rounded border border-slate-200 p-3">
            <h3 class="text-sm font-semibold text-slate-800">Payment</h3>
            <p class="mt-2 text-sm text-slate-700">Method: {{ ucfirst($order->payment_method) }}</p>
            <p class="text-sm text-slate-700">Status: {{ ucfirst($order->payment_status) }}</p>
            <p class="text-sm text-slate-700">PayPal Order: {{ $order->paypal_order_id ?: '-' }}</p>
            <p class="text-sm text-slate-700">PayPal Capture: {{ $order->paypal_capture_id ?: '-' }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="mb-5 grid gap-3 rounded border border-slate-200 bg-slate-50 p-3 md:grid-cols-3">
        @csrf
        @method('PATCH')

        <div>
            <label for="status" class="mb-1 block text-xs font-medium text-slate-700">Order status</label>
            <select id="status" name="status" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                @foreach ($statusOptions as $statusOption)
                    <option value="{{ $statusOption }}" @selected($order->status === $statusOption)>{{ ucfirst($statusOption) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="payment_status" class="mb-1 block text-xs font-medium text-slate-700">Payment status</label>
            <select id="payment_status" name="payment_status" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                @foreach ($paymentStatusOptions as $paymentStatusOption)
                    <option value="{{ $paymentStatusOption }}" @selected($order->payment_status === $paymentStatusOption)>{{ ucfirst($paymentStatusOption) }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-end">
            <button type="submit" class="rounded bg-slate-800 px-3 py-2 text-sm text-white hover:bg-slate-900">Save Status</button>
        </div>
    </form>

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
                @forelse ($order->items as $item)
                    <tr>
                        <td class="border border-slate-200 px-3 py-2">
                            @php($thumbnailPath = $item->product?->media->first()?->path)
                            @if ($thumbnailPath)
                                <img src="{{ route('media.show', ['path' => $thumbnailPath]) }}" alt="{{ $item->product_name }}" class="h-12 w-12 rounded object-cover">
                            @else
                                <div class="h-12 w-12 rounded bg-slate-100"></div>
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

    <div class="mt-4 flex justify-end">
        <p class="text-lg font-semibold">Grand total: ${{ number_format((float) $order->total, 2) }}</p>
    </div>
@endsection
