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

{{-- Multi-Year Revenue Overlay & Best Month Benchmark --}}
<div class="grid gap-6 lg:grid-cols-3">
    {{-- Multi-Year Line Overlay --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm lg:col-span-2" data-delay="6">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Monthly Revenue — Multi-Year Overlay</h3>
        <div class="h-[300px]">
            <canvas id="yearlyRevenueChart"></canvas>
        </div>
    </div>

    {{-- Best-in-Class Month Benchmark --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="7">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">{{ $bestMonthBenchmark['month_name'] }} Benchmark</h3>

        <div class="mt-4 space-y-5">
            {{-- Current Month Revenue --}}
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-stone-400">{{ $bestMonthBenchmark['month_name'] }} {{ $bestMonthBenchmark['current_year'] }}</p>
                <p class="mt-1 text-2xl font-bold text-stone-800">&euro;{{ number_format($bestMonthBenchmark['current_revenue'], 2) }}</p>
            </div>

            @if ($bestMonthBenchmark['best_year'])
                {{-- All-Time Best --}}
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-stone-400">All-Time Best {{ $bestMonthBenchmark['month_name'] }}</p>
                    <p class="mt-1 text-lg font-semibold text-stone-700">&euro;{{ number_format($bestMonthBenchmark['best_revenue'], 2) }}</p>
                    <p class="text-sm text-stone-500">Set in {{ $bestMonthBenchmark['best_year'] }}</p>
                </div>

                {{-- Gap Indicator --}}
                @if ($bestMonthBenchmark['gap_pct'] !== null)
                    <div class="rounded-lg p-3 {{ $bestMonthBenchmark['gap_pct'] >= 0 ? 'bg-[#4bc0c0]/10' : 'bg-[#ff6384]/10' }}">
                        <p class="text-sm font-medium {{ $bestMonthBenchmark['gap_pct'] >= 0 ? 'text-[#4bc0c0]' : 'text-[#ff6384]' }}">
                            @if ($bestMonthBenchmark['gap_pct'] >= 0)
                                ▲ {{ number_format(abs($bestMonthBenchmark['gap_pct']), 1) }}% above record
                            @else
                                ▼ {{ number_format(abs($bestMonthBenchmark['gap_pct']), 1) }}% below record
                            @endif
                        </p>
                    </div>
                @endif
            @else
                <p class="text-sm text-stone-500">First {{ $bestMonthBenchmark['month_name'] }} on record — this will become the benchmark.</p>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const revenueData = @json($revenueOverTime);
        const countryData = @json($revenueByCountry);
        const prevRevenueData = @json($prevRevenueOverTime ?? null);
        const yearlyRevenue = @json($yearlyRevenue);

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

        // Multi-Year Revenue Overlay
        if (yearlyRevenue && document.getElementById('yearlyRevenueChart')) {
            const yearColors = ['#c9cbcf', '#ff9f40', '#9966ff', '#4bc0c0', '#36a2eb'];
            const years = Object.keys(yearlyRevenue.years).map(Number).sort();
            const currentYear = years[years.length - 1];
            const yearDatasets = [];

            years.forEach((year, idx) => {
                const isCurrent = year === currentYear;
                yearDatasets.push({
                    label: String(year),
                    data: Object.values(yearlyRevenue.years[year]),
                    borderColor: yearColors[idx % yearColors.length],
                    backgroundColor: 'transparent',
                    borderWidth: isCurrent ? 3 : 1.5,
                    borderDash: isCurrent ? [] : [4, 3],
                    tension: 0.35,
                    pointRadius: isCurrent ? 4 : 0,
                    pointBackgroundColor: isCurrent ? yearColors[idx % yearColors.length] : undefined,
                    pointBorderColor: isCurrent ? '#fff' : undefined,
                    pointBorderWidth: isCurrent ? 2 : 0,
                });
            });

            // Historical average line
            yearDatasets.push({
                label: 'Hist. Average',
                data: Object.values(yearlyRevenue.average),
                borderColor: '#ff6384',
                borderWidth: 2,
                borderDash: [8, 4],
                backgroundColor: 'rgba(255, 99, 132, 0.05)',
                fill: true,
                tension: 0.35,
                pointRadius: 0,
            });

            new Chart(document.getElementById('yearlyRevenueChart'), {
                type: 'line',
                data: {
                    labels: yearlyRevenue.months,
                    datasets: yearDatasets,
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { padding: 16, font: { size: 11 }, color: '#57534e' } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 12 }, color: '#78716c' } },
                        y: { beginAtZero: true, ticks: { callback: v => '€' + v.toLocaleString(), font: { size: 12 }, color: '#78716c' }, grid: { color: '#f5f5f4' } }
                    }
                }
            });
        }
    });
</script>
@endpush
