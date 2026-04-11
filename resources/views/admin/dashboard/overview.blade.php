{{-- KPI Row --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="admin-card admin-card-hover rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="1">
        <p class="text-sm font-semibold uppercase tracking-wide text-stone-500">Total Revenue</p>
        <p class="mt-1 text-3xl font-bold text-stone-800">&euro;{{ number_format($overview['revenue'], 2) }}</p>
    </div>

    <div class="admin-card admin-card-hover rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="2">
        <p class="text-sm font-semibold uppercase tracking-wide text-stone-500">Orders Today</p>
        <p class="mt-1 text-3xl font-bold text-stone-800">{{ $overview['orders_today'] }}</p>
    </div>

    <div class="admin-card admin-card-hover rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="3">
        <p class="text-sm font-semibold uppercase tracking-wide text-stone-500">Products</p>
        <p class="mt-1 text-3xl font-bold text-stone-800">{{ $overview['active_products'] }} <span class="text-base font-normal text-stone-400">/ {{ $overview['total_products'] }}</span></p>
    </div>

    <a href="{{ route('admin.orders.index', ['status' => 'pending']) }}" class="admin-card admin-card-hover group rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="4">
        <p class="text-sm font-semibold uppercase tracking-wide text-stone-500 group-hover:text-[#36a2eb]">Pending Orders</p>
        <p class="mt-1 text-3xl font-bold {{ $overview['pending_orders'] > 0 ? 'text-[#ff9f40]' : 'text-stone-800' }}">{{ $overview['pending_orders'] }}</p>
        @if ($overview['pending_orders'] > 0)
            <p class="mt-1 text-sm text-[#ff9f40] opacity-0 transition group-hover:opacity-100">View orders &rarr;</p>
        @endif
    </a>
</div>

{{-- Attention Items with Quick Links --}}
@if ($overview['low_stock'] > 0 || $overview['out_of_stock'] > 0 || $overview['pending_orders'] > 0 || $overview['stock_alert_waiting'] > 0)
    <div class="admin-card rounded-xl border border-[#ff9f40]/30 bg-[#ff9f40]/10 p-5" data-delay="2">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-[#ff9f40]">Needs Attention</h3>
        <div class="space-y-2">
            @if ($overview['pending_orders'] > 0)
                <a href="{{ route('admin.orders.index', ['status' => 'pending']) }}" class="flex items-center justify-between rounded-lg bg-white/60 px-4 py-3 text-[15px] text-stone-700 transition hover:bg-white hover:shadow-sm">
                    <span>{{ $overview['pending_orders'] }} {{ Str::plural('order', $overview['pending_orders']) }} pending processing</span>
                    <svg class="h-5 w-5 shrink-0 text-[#ff9f40]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </a>
            @endif
            @if ($overview['low_stock'] > 0)
                <a href="{{ route('admin.products.index') }}" class="flex items-center justify-between rounded-lg bg-white/60 px-4 py-3 text-[15px] text-stone-700 transition hover:bg-white hover:shadow-sm">
                    <span>{{ $overview['low_stock'] }} {{ Str::plural('variant', $overview['low_stock']) }} with low stock (1–5 remaining)</span>
                    <svg class="h-5 w-5 shrink-0 text-[#ff9f40]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </a>
            @endif
            @if ($overview['out_of_stock'] > 0)
                <a href="{{ route('admin.products.index') }}" class="flex items-center justify-between rounded-lg bg-white/60 px-4 py-3 text-[15px] text-stone-700 transition hover:bg-white hover:shadow-sm">
                    <span>{{ $overview['out_of_stock'] }} {{ Str::plural('variant', $overview['out_of_stock']) }} out of stock</span>
                    <svg class="h-5 w-5 shrink-0 text-[#ff9f40]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </a>
            @endif
            @if ($overview['stock_alert_waiting'] > 0)
                <a href="{{ route('admin.products.index') }}" class="flex items-center justify-between rounded-lg bg-white/60 px-4 py-3 text-[15px] text-amber-700 transition hover:bg-white hover:shadow-sm">
                    <span>{{ $overview['stock_alert_waiting'] }} {{ Str::plural('customer', $overview['stock_alert_waiting']) }} waiting on stock alerts</span>
                    <svg class="h-5 w-5 shrink-0 text-[#ff9f40]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </a>
            @endif
        </div>
    </div>
@endif

{{-- Charts Row --}}
<div class="grid gap-6 lg:grid-cols-2">
    {{-- Revenue (30d) --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="3">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Revenue (30 days)</h3>
        <div class="h-[280px]">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    {{-- Orders by Status --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="4">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Orders by Status</h3>
        <div class="mx-auto h-[250px] max-w-xs">
            <canvas id="statusChart"></canvas>
        </div>
    </div>
</div>

{{-- Notes + Recent Orders side by side --}}
<div class="grid gap-6 lg:grid-cols-3">
    {{-- Notes --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm lg:col-span-1" data-delay="3">
        <livewire:admin.dashboard-notes />
    </div>

    {{-- Recent Orders --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm lg:col-span-2" data-delay="4">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Recent Orders</h3>
        @if ($recentOrders->isEmpty())
            <p class="text-[15px] text-stone-500">No orders yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-medium text-stone-600">Order</th>
                            <th class="px-4 py-2.5 text-left font-medium text-stone-600">Customer</th>
                            <th class="px-4 py-2.5 text-left font-medium text-stone-600">Status</th>
                            <th class="px-4 py-2.5 text-left font-medium text-stone-600">Payment</th>
                            <th class="px-4 py-2.5 text-right font-medium text-stone-600">Total</th>
                            <th class="px-4 py-2.5 text-left font-medium text-stone-600">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($recentOrders as $order)
                            <tr class="transition hover:bg-stone-50">
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-[#36a2eb] hover:underline">
                                        {{ $order->order_number }}
                                    </a>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-stone-700">
                                    {{ $order->first_name }} {{ $order->last_name }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-[#ff9f40]/15 text-[#ff9f40]',
                                            'processing' => 'bg-[#36a2eb]/15 text-[#36a2eb]',
                                            'shipped' => 'bg-[#9966ff]/15 text-[#9966ff]',
                                            'delivered' => 'bg-[#4bc0c0]/15 text-[#4bc0c0]',
                                            'cancelled' => 'bg-[#ff6384]/15 text-[#ff6384]',
                                            'refunded' => 'bg-stone-100 text-stone-700',
                                        ];
                                    @endphp
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusColors[$order->status] ?? 'bg-stone-100 text-stone-700' }}">
                                        {{ ucfirst($order->status) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $order->payment_status === 'paid' ? 'bg-[#4bc0c0]/15 text-[#4bc0c0]' : 'bg-[#ff9f40]/15 text-[#ff9f40]' }}">
                                        {{ ucfirst($order->payment_status) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700">
                                    &euro;{{ number_format($order->total, 2) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-stone-500">
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

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const revenueData = @json($revenueOverTime);
        const statusData = @json($ordersByStatus);

        // Revenue Line Chart — Chart.js blue
        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: revenueData.map(r => r.day),
                datasets: [{
                    label: 'Revenue',
                    data: revenueData.map(r => r.revenue),
                    borderColor: '#36a2eb',
                    backgroundColor: 'rgba(54, 162, 235, 0.08)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointBackgroundColor: '#36a2eb',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 8, font: { size: 12 }, color: '#78716c' } },
                    y: { beginAtZero: true, ticks: { callback: v => '€' + v.toLocaleString(), font: { size: 12 }, color: '#78716c' }, grid: { color: '#f5f5f4' } }
                }
            }
        });

        // Orders by Status Donut — Chart.js palette
        const statusColors = {
            pending: '#ff9f40', processing: '#36a2eb', shipped: '#9966ff',
            delivered: '#4bc0c0', cancelled: '#ff6384', refunded: '#c9cbcf'
        };
        const statusLabels = Object.keys(statusData);
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusLabels.map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                datasets: [{
                    data: statusLabels.map(s => statusData[s]),
                    backgroundColor: statusLabels.map(s => statusColors[s] || '#a8a29e'),
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 }, color: '#57534e' } }
                }
            }
        });
    });
</script>
@endpush
