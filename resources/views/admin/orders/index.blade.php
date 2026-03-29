@extends('admin.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Orders</h2>
    </div>

    <form method="GET" action="{{ route('admin.orders.index') }}" class="mb-4 grid gap-3 rounded border border-slate-200 bg-slate-50 p-3 md:grid-cols-4">
        <div>
            <label for="q" class="mb-1 block text-xs font-medium text-slate-700">Search</label>
            <input id="q" name="q" type="text" value="{{ $filters['q'] }}" placeholder="Order no / email / customer" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="status" class="mb-1 block text-xs font-medium text-slate-700">Order status</label>
            <select id="status" name="status" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach ($statusOptions as $statusOption)
                    <option value="{{ $statusOption }}" @selected($filters['status'] === $statusOption)>{{ ucfirst($statusOption) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="payment_status" class="mb-1 block text-xs font-medium text-slate-700">Payment status</label>
            <select id="payment_status" name="payment_status" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach ($paymentStatusOptions as $paymentStatusOption)
                    <option value="{{ $paymentStatusOption }}" @selected($filters['payment_status'] === $paymentStatusOption)>{{ ucfirst($paymentStatusOption) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="rounded bg-slate-700 px-3 py-2 text-sm text-white hover:bg-slate-800">Filter</button>
            <a href="{{ route('admin.orders.index') }}" class="rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">Reset</a>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="min-w-full border border-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="border border-slate-200 px-3 py-2 text-left">Order</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Customer</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Payment</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Items</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Total</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Placed</th>
                    <th class="border border-slate-200 px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td class="border border-slate-200 px-3 py-2">
                            <div class="font-medium text-slate-900">{{ $order->order_number }}</div>
                            <div class="text-xs text-slate-500">{{ ucfirst($order->status) }}</div>
                        </td>
                        <td class="border border-slate-200 px-3 py-2">
                            <div>{{ $order->first_name }} {{ $order->last_name }}</div>
                            <div class="text-xs text-slate-500">{{ $order->email }}</div>
                        </td>
                        <td class="border border-slate-200 px-3 py-2">
                            <div>{{ ucfirst($order->payment_method) }}</div>
                            <div class="text-xs text-slate-500">{{ ucfirst($order->payment_status) }}</div>
                        </td>
                        <td class="border border-slate-200 px-3 py-2">{{ $order->items_count }}</td>
                        <td class="border border-slate-200 px-3 py-2">${{ number_format((float) $order->total, 2) }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ optional($order->placed_at)->format('Y-m-d H:i') ?? '-' }}</td>
                        <td class="border border-slate-200 px-3 py-2 text-right">
                            <a href="{{ route('admin.orders.show', $order) }}" class="text-blue-700 hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="border border-slate-200 px-3 py-6 text-center text-slate-500">No orders found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
@endsection
