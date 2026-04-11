{{-- KPI Row --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Avg Order Value</p>
        <p class="mt-1 text-2xl font-bold text-slate-900">&euro;{{ number_format($aov, 2) }}</p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Repeat Customer Rate</p>
        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $repeatRate['repeat_pct'] }}%</p>
        <p class="mt-1 text-xs text-slate-400">{{ $repeatRate['total'] }} unique customers</p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Customer Breakdown</p>
        <div class="mt-2 space-y-1 text-sm">
            <div class="flex justify-between"><span class="text-slate-500">1 order</span><span class="font-medium text-slate-700">{{ $repeatRate['one_time'] }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">2 orders</span><span class="font-medium text-slate-700">{{ $repeatRate['two_orders'] }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">3+ orders</span><span class="font-medium text-slate-700">{{ $repeatRate['three_plus'] }}</span></div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Orders by Status</p>
        <div class="mt-2 space-y-1 text-sm">
            @foreach ($ordersByStatus as $status => $count)
                <div class="flex justify-between"><span class="text-slate-500">{{ ucfirst($status) }}</span><span class="font-medium text-slate-700">{{ $count }}</span></div>
            @endforeach
        </div>
    </div>
</div>

{{-- Charts Row --}}
<div class="grid gap-6 lg:grid-cols-2">
    {{-- Revenue Over Time --}}
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-700">Revenue Over Time (30d)</h3>
        <canvas id="salesRevenueChart" height="220"></canvas>
    </div>

    {{-- Revenue by Country --}}
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-700">Revenue by Country</h3>
        <canvas id="countryRevenueChart" height="220"></canvas>
    </div>
</div>

{{-- Top Products --}}
<div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-700">Top Selling Products (30d)</h3>
    @if (empty($topProducts))
        <p class="text-sm text-slate-500">No sales data yet.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-slate-600">#</th>
                        <th class="px-4 py-2 text-left font-medium text-slate-600">Product</th>
                        <th class="px-4 py-2 text-right font-medium text-slate-600">Units</th>
                        <th class="px-4 py-2 text-right font-medium text-slate-600">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($topProducts as $i => $product)
                        <tr class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-2 text-slate-400">{{ $i + 1 }}</td>
                            <td class="px-4 py-2 text-slate-700">{{ $product['name'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right font-medium text-slate-700">{{ $product['units'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right text-slate-700">&euro;{{ number_format($product['revenue'], 2) }}</td>
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
        const countryData = @json($revenueByCountry);

        // Revenue line chart with gross/net/discounts
        new Chart(document.getElementById('salesRevenueChart'), {
            type: 'line',
            data: {
                labels: revenueData.map(r => r.day),
                datasets: [
                    {
                        label: 'Net Revenue',
                        data: revenueData.map(r => r.revenue),
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 2,
                    },
                    {
                        label: 'Gross',
                        data: revenueData.map(r => r.gross),
                        borderColor: '#10b981',
                        borderDash: [5, 5],
                        fill: false,
                        tension: 0.3,
                        pointRadius: 0,
                    },
                    {
                        label: 'Discounts',
                        data: revenueData.map(r => r.discounts),
                        borderColor: '#ef4444',
                        borderDash: [3, 3],
                        fill: false,
                        tension: 0.3,
                        pointRadius: 0,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { padding: 16, font: { size: 11 } } } },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 8, font: { size: 11 } } },
                    y: { beginAtZero: true, ticks: { callback: v => '€' + v.toLocaleString(), font: { size: 11 } } }
                }
            }
        });

        // Revenue by country horizontal bar
        new Chart(document.getElementById('countryRevenueChart'), {
            type: 'bar',
            data: {
                labels: countryData.map(c => c.country || 'Unknown'),
                datasets: [{
                    label: 'Revenue',
                    data: countryData.map(c => c.revenue),
                    backgroundColor: '#3b82f6',
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { callback: v => '€' + v.toLocaleString(), font: { size: 11 } } },
                    y: { ticks: { font: { size: 11 } } }
                }
            }
        });
    });
</script>
@endpush
