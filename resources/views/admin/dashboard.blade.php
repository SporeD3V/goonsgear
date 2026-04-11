@extends('admin.layout')

@section('content')
    <div class="space-y-6">
        <h1 class="text-2xl font-bold">Dashboard</h1>

        {{-- Stat Cards --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Orders</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($totalOrders) }}</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Revenue</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">&euro;{{ number_format($revenue, 2) }}</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Products</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $activeProducts }} <span class="text-sm font-normal text-slate-400">/ {{ $totalProducts }}</span></p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending Orders</p>
                <p class="mt-1 text-2xl font-bold {{ $pendingOrders > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ $pendingOrders }}</p>
            </div>
        </div>

        {{-- Attention Items --}}
        @if ($lowStockVariants > 0 || $outOfStockVariants > 0 || $pendingOrders > 0)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-5">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-amber-800">Needs Attention</h3>
                <ul class="space-y-1 text-sm text-amber-700">
                    @if ($pendingOrders > 0)
                        <li>{{ $pendingOrders }} {{ Str::plural('order', $pendingOrders) }} pending processing</li>
                    @endif
                    @if ($lowStockVariants > 0)
                        <li>{{ $lowStockVariants }} {{ Str::plural('variant', $lowStockVariants) }} with low stock (1–5 remaining)</li>
                    @endif
                    @if ($outOfStockVariants > 0)
                        <li>{{ $outOfStockVariants }} {{ Str::plural('variant', $outOfStockVariants) }} out of stock</li>
                    @endif
                </ul>
            </div>
        @endif

        {{-- Recent Orders --}}
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-700">Recent Orders</h3>
            @if ($recentOrders->isEmpty())
                <p class="text-sm text-slate-500">No orders yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-slate-600">Order</th>
                                <th class="px-4 py-2 text-left font-medium text-slate-600">Customer</th>
                                <th class="px-4 py-2 text-left font-medium text-slate-600">Status</th>
                                <th class="px-4 py-2 text-left font-medium text-slate-600">Payment</th>
                                <th class="px-4 py-2 text-right font-medium text-slate-600">Total</th>
                                <th class="px-4 py-2 text-left font-medium text-slate-600">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recentOrders as $order)
                                <tr class="hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-4 py-2">
                                        <a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-blue-700 hover:underline">
                                            {{ $order->order_number }}
                                        </a>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-slate-700">
                                        {{ $order->first_name }} {{ $order->last_name }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2">
                                        @php
                                            $statusColors = [
                                                'pending' => 'bg-amber-100 text-amber-700',
                                                'processing' => 'bg-blue-100 text-blue-700',
                                                'shipped' => 'bg-indigo-100 text-indigo-700',
                                                'delivered' => 'bg-emerald-100 text-emerald-700',
                                                'cancelled' => 'bg-red-100 text-red-700',
                                                'refunded' => 'bg-slate-100 text-slate-700',
                                            ];
                                        @endphp
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColors[$order->status] ?? 'bg-slate-100 text-slate-700' }}">
                                            {{ ucfirst($order->status) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $order->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                            {{ ucfirst($order->payment_status) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-slate-700">
                                        &euro;{{ number_format($order->total, 2) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-slate-500">
                                        {{ $order->placed_at?->format('M d, Y') ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
