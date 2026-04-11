{{-- KPI Row --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @include('admin.dashboard._kpi-card', [
        'label' => 'Avg Order Value',
        'value' => '&euro;' . number_format($aov, 2),
        'delta' => $deltas['aov'] ?? null,
        'delay' => 1,
    ])

    @include('admin.dashboard._kpi-card', [
        'label' => 'Repeat Customer Rate',
        'value' => $repeatRate['repeat_pct'] . '%',
        'delta' => $deltas['repeat_pct'] ?? null,
        'subtitle' => $repeatRate['total'] . ' unique customers',
        'delay' => 2,
    ])

    @include('admin.dashboard._kpi-card', [
        'label' => 'Items per Order',
        'value' => number_format($itemsPerOrder, 1),
        'delta' => $deltas['items_per_order'] ?? null,
        'delay' => 3,
    ])

    <div class="admin-card admin-card-hover rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="4">
        <p class="text-sm font-semibold uppercase tracking-wide text-stone-500">Customer Breakdown</p>
        <div class="mt-2 space-y-1 text-[15px]">
            <div class="flex justify-between"><span class="text-stone-500">1 order</span><span class="font-medium text-stone-700">{{ $repeatRate['one_time'] }}</span></div>
            <div class="flex justify-between"><span class="text-stone-500">2 orders</span><span class="font-medium text-stone-700">{{ $repeatRate['two_orders'] }}</span></div>
            <div class="flex justify-between"><span class="text-stone-500">3+ orders</span><span class="font-medium text-stone-700">{{ $repeatRate['three_plus'] }}</span></div>
        </div>
    </div>
</div>

{{-- Charts Row --}}
<div class="grid gap-6 lg:grid-cols-2">
    {{-- Revenue Over Time --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="3">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Revenue Over Time ({{ $periodLabel }})</h3>
        <div class="h-[280px]">
            <canvas id="salesRevenueChart"></canvas>
        </div>
    </div>

    {{-- Revenue by Country --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="4">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Revenue by Country</h3>
        <div class="h-[280px]">
            <canvas id="countryRevenueChart"></canvas>
        </div>
    </div>
</div>

{{-- Top Products --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="5">
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Top Selling Products ({{ $periodLabel }})</h3>
    @if (empty($topProducts))
        <p class="text-[15px] text-stone-500">No sales data yet.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-medium text-stone-600">#</th>
                        <th class="px-4 py-2.5 text-left font-medium text-stone-600">Product</th>
                        <th class="px-4 py-2.5 text-right font-medium text-stone-600">Units</th>
                        <th class="px-4 py-2.5 text-right font-medium text-stone-600">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($topProducts as $i => $product)
                        <tr class="transition hover:bg-stone-50">
                            <td class="whitespace-nowrap px-4 py-2.5 text-stone-400">{{ $i + 1 }}</td>
                            <td class="px-4 py-2.5 text-stone-700">{{ $product['name'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right font-medium text-stone-700">{{ $product['units'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700">&euro;{{ number_format($product['revenue'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const revenueData = @json($revenueOverTime);
        const countryData = @json($revenueByCountry);
        const prevRevenueData = @json($prevRevenueOverTime ?? null);

        // Revenue line chart with gross/net/discounts — Chart.js palette
        const salesDatasets = [
            {
                label: 'Net Revenue',
                data: revenueData.map(r => r.revenue),
                borderColor: '#36a2eb',
                backgroundColor: 'rgba(54, 162, 235, 0.08)',
                fill: true,
                tension: 0.35,
                pointRadius: 3,
                pointBackgroundColor: '#36a2eb',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
            },
            {
                label: 'Gross',
                data: revenueData.map(r => r.gross),
                borderColor: '#4bc0c0',
                borderDash: [5, 5],
                fill: false,
                tension: 0.35,
                pointRadius: 0,
            },
            {
                label: 'Discounts',
                data: revenueData.map(r => r.discounts),
                borderColor: '#ff6384',
                borderDash: [3, 3],
                fill: false,
                tension: 0.35,
                pointRadius: 0,
            }
        ];

        if (prevRevenueData) {
            salesDatasets.push({
                label: 'Prev. Net Revenue',
                data: prevRevenueData.map(r => r.revenue),
                borderColor: '#9966ff',
                borderDash: [6, 4],
                backgroundColor: 'transparent',
                fill: false,
                tension: 0.35,
                pointRadius: 0,
            });
        }

        new Chart(document.getElementById('salesRevenueChart'), {
            type: 'line',
            data: {
                labels: revenueData.map(r => r.day),
                datasets: salesDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 }, color: '#57534e' } } },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 8, font: { size: 12 }, color: '#78716c' } },
                    y: { beginAtZero: true, ticks: { callback: v => '€' + v.toLocaleString(), font: { size: 12 }, color: '#78716c' }, grid: { color: '#f5f5f4' } }
                }
            }
        });

        // Revenue by country horizontal bar — Chart.js orange
        new Chart(document.getElementById('countryRevenueChart'), {
            type: 'bar',
            data: {
                labels: countryData.map(c => c.country || 'Unknown'),
                datasets: [{
                    label: 'Revenue',
                    data: countryData.map(c => c.revenue),
                    backgroundColor: '#ff9f40',
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { callback: v => '€' + v.toLocaleString(), font: { size: 12 }, color: '#78716c' }, grid: { color: '#f5f5f4' } },
                    y: { ticks: { font: { size: 12 }, color: '#57534e' } }
                }
            }
        });
    });
</script>
@endpush
