{{-- KPI Row --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="admin-card admin-card-hover rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="1">
        <p class="text-sm font-semibold uppercase tracking-wide text-stone-500">Avg Order Value</p>
        <p class="mt-1 text-3xl font-bold text-stone-800">&euro;{{ number_format($aov, 2) }}</p>
    </div>

    <div class="admin-card admin-card-hover rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="2">
        <p class="text-sm font-semibold uppercase tracking-wide text-stone-500">Repeat Customer Rate</p>
        <p class="mt-1 text-3xl font-bold text-stone-800">{{ $repeatRate['repeat_pct'] }}%</p>
        <p class="mt-1 text-sm text-stone-400">{{ $repeatRate['total'] }} unique customers</p>
    </div>

    <div class="admin-card admin-card-hover rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="3">
        <p class="text-sm font-semibold uppercase tracking-wide text-stone-500">Customer Breakdown</p>
        <div class="mt-2 space-y-1 text-[15px]">
            <div class="flex justify-between"><span class="text-stone-500">1 order</span><span class="font-medium text-stone-700">{{ $repeatRate['one_time'] }}</span></div>
            <div class="flex justify-between"><span class="text-stone-500">2 orders</span><span class="font-medium text-stone-700">{{ $repeatRate['two_orders'] }}</span></div>
            <div class="flex justify-between"><span class="text-stone-500">3+ orders</span><span class="font-medium text-stone-700">{{ $repeatRate['three_plus'] }}</span></div>
        </div>
    </div>

    <div class="admin-card admin-card-hover rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="4">
        <p class="text-sm font-semibold uppercase tracking-wide text-stone-500">Orders by Status</p>
        <div class="mt-2 space-y-1 text-[15px]">
            @foreach ($ordersByStatus as $status => $count)
                <div class="flex justify-between"><span class="text-stone-500">{{ ucfirst($status) }}</span><span class="font-medium text-stone-700">{{ $count }}</span></div>
            @endforeach
        </div>
    </div>
</div>

{{-- Charts Row --}}
<div class="grid gap-6 lg:grid-cols-2">
    {{-- Revenue Over Time --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="3">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Revenue Over Time (30d)</h3>
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
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Top Selling Products (30d)</h3>
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

        // Revenue line chart with gross/net/discounts — Chart.js palette
        new Chart(document.getElementById('salesRevenueChart'), {
            type: 'line',
            data: {
                labels: revenueData.map(r => r.day),
                datasets: [
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
                ]
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
