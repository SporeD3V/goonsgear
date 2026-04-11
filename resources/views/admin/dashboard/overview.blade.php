{{-- KPI Row --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Revenue</p>
        <p class="mt-1 text-2xl font-bold text-slate-900">&euro;{{ number_format($overview['revenue'], 2) }}</p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Orders Today</p>
        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $overview['orders_today'] }}</p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Products</p>
        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $overview['active_products'] }} <span class="text-sm font-normal text-slate-400">/ {{ $overview['total_products'] }}</span></p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending Orders</p>
        <p class="mt-1 text-2xl font-bold {{ $overview['pending_orders'] > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ $overview['pending_orders'] }}</p>
    </div>
</div>

{{-- Attention Items --}}
@if ($overview['low_stock'] > 0 || $overview['out_of_stock'] > 0 || $overview['pending_orders'] > 0 || $overview['stock_alert_waiting'] > 0)
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-5">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-amber-800">Needs Attention</h3>
        <ul class="space-y-1 text-sm text-amber-700">
            @if ($overview['pending_orders'] > 0)
                <li>{{ $overview['pending_orders'] }} {{ Str::plural('order', $overview['pending_orders']) }} pending processing</li>
            @endif
            @if ($overview['low_stock'] > 0)
                <li>{{ $overview['low_stock'] }} {{ Str::plural('variant', $overview['low_stock']) }} with low stock (1–5 remaining)</li>
            @endif
            @if ($overview['out_of_stock'] > 0)
                <li>{{ $overview['out_of_stock'] }} {{ Str::plural('variant', $overview['out_of_stock']) }} out of stock</li>
            @endif
            @if ($overview['stock_alert_waiting'] > 0)
                <li>{{ $overview['stock_alert_waiting'] }} {{ Str::plural('customer', $overview['stock_alert_waiting']) }} waiting on stock alerts</li>
            @endif
        </ul>
    </div>
@endif

{{-- Charts Row --}}
<div class="grid gap-6 lg:grid-cols-2">
    {{-- Revenue (30d) --}}
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-700">Revenue (30 days)</h3>
        <canvas id="revenueChart" height="200"></canvas>
    </div>

    {{-- Orders by Status --}}
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-700">Orders by Status</h3>
        <div class="mx-auto max-w-xs">
            <canvas id="statusChart" height="200"></canvas>
        </div>
    </div>
</div>

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

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const revenueData = @json($revenueOverTime);
        const statusData = @json($ordersByStatus);

        // Revenue Line Chart
        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: revenueData.map(r => r.day),
                datasets: [{
                    label: 'Revenue',
                    data: revenueData.map(r => r.revenue),
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 8, font: { size: 11 } } },
                    y: { beginAtZero: true, ticks: { callback: v => '€' + v.toLocaleString(), font: { size: 11 } } }
                }
            }
        });

        // Orders by Status Donut
        const statusColors = {
            pending: '#f59e0b', processing: '#3b82f6', shipped: '#6366f1',
            delivered: '#10b981', cancelled: '#ef4444', refunded: '#64748b'
        };
        const statusLabels = Object.keys(statusData);
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusLabels.map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                datasets: [{
                    data: statusLabels.map(s => statusData[s]),
                    backgroundColor: statusLabels.map(s => statusColors[s] || '#94a3b8'),
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 16, font: { size: 11 } } }
                }
            }
        });
    });
</script>
@endpush
