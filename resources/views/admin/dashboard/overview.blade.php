{{-- KPI Row --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
    @include('admin.dashboard._kpi-card', [
        'label' => 'Revenue (' . $periodLabel . ')',
        'value' => '&euro;' . number_format($overview['revenue'], 2),
        'delta' => $deltas['revenue'] ?? null,
        'subtitle' => 'Gross: total paid order value',
        'delay' => 1,
    ])

    @include('admin.dashboard._kpi-card', [
        'label' => 'Net Revenue (' . $periodLabel . ')',
        'value' => '&euro;' . number_format($overview['net_revenue'], 2),
        'delta' => $deltas['net_revenue'] ?? null,
        'subtitle' => 'Revenue minus tax & shipping',
        'delay' => 1,
    ])

    @include('admin.dashboard._kpi-card', [
        'label' => 'Orders (' . $periodLabel . ')',
        'value' => number_format($overview['total_orders']),
        'delta' => $deltas['total_orders'] ?? null,
        'subtitle' => 'All orders placed (any status)',
        'delay' => 2,
    ])

    @include('admin.dashboard._kpi-card', [
        'label' => 'Products',
        'value' => $overview['active_products'] . ' <span class="text-base font-normal text-stone-400">/ ' . $overview['total_products'] . '</span>',
        'subtitle' => 'Active products / Total products',
        'delay' => 3,
    ])

    @include('admin.dashboard._kpi-card', [
        'label' => 'Pending Orders',
        'value' => $overview['pending_orders'],
        'href' => $overview['pending_orders'] > 0 ? route('admin.orders.index', ['status' => 'pending']) : null,
        'subtitle' => 'Orders waiting to be processed',
        'delay' => 4,
    ])
</div>

{{-- Site Conversion Rate --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="2">
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Site Conversion ({{ $periodLabel }})</h3>
    <p class="mb-3 text-[13px] text-stone-500">How efficiently are visitors turning into paying customers?</p>
    @if ($siteConversion['visitors'] === 0)
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-stone-100 bg-stone-50 p-4 text-center">
                <div class="text-[13px] font-medium uppercase tracking-wide text-stone-500">Paid Orders</div>
                <div class="mt-1 text-2xl font-bold" style="color: #36a2eb">{{ number_format($siteConversion['orders']) }}</div>
                <div class="mt-1 text-[11px] text-stone-400">Orders with payment confirmed</div>
            </div>
            <div class="rounded-lg border border-stone-100 bg-stone-50 p-4 text-center">
                <div class="text-[13px] font-medium uppercase tracking-wide text-stone-500">Revenue</div>
                <div class="mt-1 text-2xl font-bold" style="color: #4bc0c0">&euro;{{ number_format($siteConversion['revenue'], 2) }}</div>
                <div class="mt-1 text-[11px] text-stone-400">Total from paid orders</div>
            </div>
        </div>
        <div class="mt-3 rounded-lg border border-stone-100 bg-stone-50 p-4">
            <p class="text-[13px] text-stone-500">Visitor tracking started on April 11, 2026. Conversion rate and revenue-per-visitor metrics require visitor data and will appear for periods after this date.</p>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-lg border border-stone-100 bg-stone-50 p-4 text-center">
                <div class="text-[13px] font-medium uppercase tracking-wide text-stone-500">Visitors</div>
                <div class="mt-1 text-2xl font-bold" style="color: #9966ff">{{ number_format($siteConversion['visitors']) }}</div>
                <div class="mt-1 text-[11px] text-stone-400">Unique sessions per day</div>
            </div>
            <div class="rounded-lg border border-stone-100 bg-stone-50 p-4 text-center">
                <div class="text-[13px] font-medium uppercase tracking-wide text-stone-500">Paid Orders</div>
                <div class="mt-1 text-2xl font-bold" style="color: #36a2eb">{{ number_format($siteConversion['orders']) }}</div>
                <div class="mt-1 text-[11px] text-stone-400">Orders with payment confirmed</div>
            </div>
            <div class="rounded-lg border border-stone-100 bg-stone-50 p-4 text-center">
                <div class="text-[13px] font-medium uppercase tracking-wide text-stone-500">CR% <span class="normal-case font-normal">(Conversion Rate)</span></div>
                <div class="mt-1 text-2xl font-bold {{ $siteConversion['conversion_pct'] >= 3 ? 'text-[#4bc0c0]' : ($siteConversion['conversion_pct'] >= 1 ? 'text-[#ff9f40]' : 'text-[#ff6384]') }}">{{ $siteConversion['conversion_pct'] }}%</div>
                <div class="mt-1 text-[11px] text-stone-400">Orders ÷ Visitors × 100</div>
            </div>
            <div class="rounded-lg border border-stone-100 bg-stone-50 p-4 text-center">
                <div class="text-[13px] font-medium uppercase tracking-wide text-stone-500">Rev / Visitor</div>
                <div class="mt-1 text-2xl font-bold" style="color: #4bc0c0">&euro;{{ number_format($siteConversion['revenue_per_visitor'], 2) }}</div>
                <div class="mt-1 text-[11px] text-stone-400">Revenue ÷ Visitors</div>
            </div>
        </div>
        <div class="mt-2 text-[11px] text-stone-400">
            Industry benchmark: 1–3% CR is typical for e-commerce. Above 3% is excellent. Niche music/merch stores often see lower conversion rates due to browsing-heavy traffic — this is normal and does not indicate a problem.
        </div>
    @endif
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

{{-- Operational Metrics: Pre-order Liability + Fulfillment Speed --}}
<div class="grid gap-6 lg:grid-cols-2">
    {{-- Pre-order Liability --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="3">
        <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Pre-order Liability</h3>
        <p class="mb-3 text-[12px] text-stone-400">Cash already collected for pre-ordered items that haven't shipped yet. This is money you owe in goods until the order is fulfilled.</p>
        @if ($preorderLiability['order_count'] === 0)
            <p class="text-[15px] text-stone-500">No active pre-orders with payment confirmed.</p>
        @else
            <div class="flex items-baseline gap-3">
                <p class="text-3xl font-bold text-[#ff9f40]">&euro;{{ number_format($preorderLiability['total_liability'], 2) }}</p>
                <span class="text-[15px] text-stone-500">outstanding</span>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <div class="rounded-lg border border-stone-100 bg-stone-50 p-3 text-center">
                    <div class="text-[13px] text-stone-500">Orders</div>
                    <div class="text-lg font-bold text-stone-700">{{ $preorderLiability['order_count'] }}</div>
                </div>
                <div class="rounded-lg border border-stone-100 bg-stone-50 p-3 text-center">
                    <div class="text-[13px] text-stone-500">Total Items</div>
                    <div class="text-lg font-bold text-stone-700">{{ $preorderLiability['item_count'] }}</div>
                </div>
            </div>
            <p class="mt-2 text-[11px] text-stone-400">Formula: SUM(order total) where status = "pre-ordered" and payment is confirmed.</p>
        @endif
    </div>

    {{-- Fulfillment Speed --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="3">
        <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Fulfillment Speed ({{ $periodLabel }})</h3>
        <p class="mb-3 text-[12px] text-stone-400">How long from when an order is placed until it's shipped? Faster = happier customers.</p>
        @if ($fulfillmentSpeed['shipped_count'] === 0)
            <p class="text-[15px] text-stone-500">No shipped orders with both placed and shipped dates in this period.</p>
        @else
            <div class="flex items-baseline gap-3">
                <p class="text-3xl font-bold" style="color: #36a2eb">{{ $fulfillmentSpeed['median_days'] }}</p>
                <span class="text-[15px] text-stone-500">median days to ship</span>
            </div>
            <div class="mt-3 grid grid-cols-3 gap-3">
                <div class="rounded-lg border border-stone-100 bg-stone-50 p-3 text-center">
                    <div class="text-[13px] text-stone-500">Average</div>
                    <div class="text-lg font-bold text-stone-700">{{ $fulfillmentSpeed['avg_days'] }}d</div>
                </div>
                <div class="rounded-lg border border-stone-100 bg-stone-50 p-3 text-center">
                    <div class="text-[13px] text-stone-500">Fastest</div>
                    <div class="text-lg font-bold text-[#4bc0c0]">{{ $fulfillmentSpeed['fastest_days'] }}d</div>
                </div>
                <div class="rounded-lg border border-stone-100 bg-stone-50 p-3 text-center">
                    <div class="text-[13px] text-stone-500">Slowest</div>
                    <div class="text-lg font-bold text-[#ff6384]">{{ $fulfillmentSpeed['slowest_days'] }}d</div>
                </div>
            </div>
            <p class="mt-2 text-[11px] text-stone-400">Based on {{ $fulfillmentSpeed['shipped_count'] }} shipped orders. Formula: shipped_at − placed_at in days.</p>
        @endif
    </div>
</div>

{{-- Charts Row --}}
<div class="grid gap-6 lg:grid-cols-2">
    {{-- Revenue (30d) --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="3">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Revenue ({{ $periodLabel }})</h3>
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
        const prevRevenueData = @json($prevRevenueOverTime ?? null);

        // Revenue Line Chart — with optional comparison overlay
        const revenueDatasets = [{
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
        }];

        if (prevRevenueData) {
            revenueDatasets.push({
                label: 'Previous Period',
                data: prevRevenueData.map(r => r.revenue),
                borderColor: '#9966ff',
                borderDash: [5, 5],
                backgroundColor: 'transparent',
                fill: false,
                tension: 0.35,
                pointRadius: 0,
            });
        }

        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: revenueData.map(r => r.day),
                datasets: revenueDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: !!prevRevenueData, position: 'bottom', labels: { padding: 16, font: { size: 12 }, color: '#57534e' } } },
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
