{{-- KPI Row --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @include('admin.dashboard._kpi-card', [
        'label' => 'Total Customers',
        'value' => number_format($customerStats['total']),
        'delay' => 1,
    ])

    @include('admin.dashboard._kpi-card', [
        'label' => 'New (' . $periodLabel . ')',
        'value' => number_format($customerStats['new_in_period']),
        'delta' => $deltas['new_in_period'] ?? null,
        'delay' => 2,
    ])

    @include('admin.dashboard._kpi-card', [
        'label' => 'Newsletter Subscribers',
        'value' => number_format($customerStats['total_newsletter']),
        'delay' => 3,
    ])
</div>

<div class="grid gap-6 lg:grid-cols-2">
    {{-- Customer Geography --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="2">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Customers by Country</h3>
        @if (empty($customerGeo))
            <p class="text-[15px] text-stone-500">No customer data yet.</p>
        @else
            <div class="h-[280px]">
                <canvas id="customerGeoChart"></canvas>
            </div>
        @endif
    </div>

    {{-- Tag Follow Popularity --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="3">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Tag Follow Popularity</h3>
        @if (empty($tagFollows))
            <p class="text-[15px] text-stone-500">No tag follow data yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-medium text-stone-600">Tag</th>
                            <th class="px-4 py-2.5 text-left font-medium text-stone-600">Type</th>
                            <th class="px-4 py-2.5 text-right font-medium text-stone-600">Followers</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($tagFollows as $tag)
                            <tr class="transition hover:bg-stone-50">
                                <td class="px-4 py-2.5 font-medium text-stone-700">{{ $tag['name'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    @php
                                        $typeColors = ['artist' => 'bg-[#ff9f40]/15 text-[#ff9f40]', 'brand' => 'bg-[#4bc0c0]/15 text-[#4bc0c0]', 'custom' => 'bg-stone-100 text-stone-700'];
                                    @endphp
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $typeColors[$tag['type']] ?? 'bg-stone-100 text-stone-700' }}">
                                        {{ ucfirst($tag['type']) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right font-medium text-stone-700">{{ $tag['followers'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Cohort Retention History --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="4">
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Cohort Retention History</h3>
    <p class="mb-3 text-[13px] text-stone-500">12-month return rate by acquisition year — is the brand getting stickier?</p>
    @if (empty($cohortRetention))
        <p class="text-[15px] text-stone-500">Not enough order history yet.</p>
    @else
        <div class="h-[300px]">
            <canvas id="cohortRetentionChart"></canvas>
        </div>
        <p class="mt-2 text-[12px] text-stone-400">* = cohort still within 12-month window (retention may still grow)</p>
    @endif
</div>

{{-- AOV Inflation Adjuster --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="5">
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">AOV Inflation Adjuster</h3>
    <p class="mb-3 text-[13px] text-stone-500">Are customers buying more items per order, or are price increases driving AOV?</p>
    @if (empty($aovBreakdown))
        <p class="text-[15px] text-stone-500">Not enough order data yet.</p>
    @else
        <div class="mb-4 h-[260px]">
            <canvas id="aovBreakdownChart"></canvas>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-medium text-stone-600">Year</th>
                        <th class="px-4 py-2.5 text-right font-medium text-stone-600">Orders</th>
                        <th class="px-4 py-2.5 text-right font-medium text-stone-600">AOV</th>
                        <th class="px-4 py-2.5 text-right font-medium text-stone-600">Avg Items / Order</th>
                        <th class="px-4 py-2.5 text-right font-medium text-stone-600">Avg Price / Item</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($aovBreakdown as $row)
                        <tr class="transition hover:bg-stone-50">
                            <td class="px-4 py-2.5 font-medium text-stone-700">{{ $row['year'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700">{{ number_format($row['total_orders']) }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right font-medium text-stone-700">€{{ number_format($row['aov'], 2) }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700">{{ $row['avg_items_per_order'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700">€{{ number_format($row['avg_price_per_item'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Waitlist Conversion Benchmarking --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="6">
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Waitlist Conversion Benchmarking</h3>
    <p class="mb-3 text-[13px] text-stone-500">Back-in-stock alert conversion: new product launches vs restocks of existing products.</p>
    @if ($waitlistConversion['first_release']['notified'] === 0 && $waitlistConversion['restock']['notified'] === 0)
        <p class="text-[15px] text-stone-500">No back-in-stock alerts have been sent yet.</p>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            {{-- First Release --}}
            <div class="rounded-lg border border-stone-100 bg-stone-50 p-4">
                <h4 class="mb-2 text-[13px] font-semibold uppercase tracking-wide text-stone-500">New Products</h4>
                <p class="text-[12px] text-stone-400 mb-3">Alert signed up within 90 days of product launch</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-bold" style="color: #4bc0c0">{{ $waitlistConversion['first_release']['conversion_pct'] }}%</span>
                    <span class="text-[13px] text-stone-500">conversion rate</span>
                </div>
                <div class="mt-2 text-[13px] text-stone-600">
                    {{ $waitlistConversion['first_release']['converted'] }} of {{ $waitlistConversion['first_release']['notified'] }} notified subscribers purchased
                </div>
            </div>

            {{-- Restock --}}
            <div class="rounded-lg border border-stone-100 bg-stone-50 p-4">
                <h4 class="mb-2 text-[13px] font-semibold uppercase tracking-wide text-stone-500">Restocks</h4>
                <p class="text-[12px] text-stone-400 mb-3">Alert signed up on established products (90+ days old)</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-bold" style="color: #ff9f40">{{ $waitlistConversion['restock']['conversion_pct'] }}%</span>
                    <span class="text-[13px] text-stone-500">conversion rate</span>
                </div>
                <div class="mt-2 text-[13px] text-stone-600">
                    {{ $waitlistConversion['restock']['converted'] }} of {{ $waitlistConversion['restock']['notified'] }} notified subscribers purchased
                </div>
            </div>
        </div>
    @endif
</div>

{{-- RFM Segmentation --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="7">
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Customer Segments (RFM)</h3>
    <p class="mb-3 text-[13px] text-stone-500">Customers grouped by Recency, Frequency & Monetary value. Based on {{ number_format($rfmSegmentation['customers_analyzed']) }} customers.</p>
    @if (empty($rfmSegmentation['segments']))
        <p class="text-[15px] text-stone-500">Not enough order data for segmentation.</p>
    @else
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="h-[260px]">
                <canvas id="rfmChart"></canvas>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-medium text-stone-600">Segment</th>
                            <th class="px-4 py-2.5 text-right font-medium text-stone-600">Customers</th>
                            <th class="px-4 py-2.5 text-right font-medium text-stone-600">Avg Revenue</th>
                            <th class="px-4 py-2.5 text-right font-medium text-stone-600">Avg Orders</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($rfmSegmentation['segments'] as $segment => $data)
                            <tr class="transition hover:bg-stone-50">
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: {{ $data['color'] }}"></span>
                                        <span class="font-medium text-stone-700">{{ $segment }}</span>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700">{{ number_format($data['count']) }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700">€{{ number_format($data['avg_revenue'], 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700">{{ $data['avg_orders'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

{{-- Customer Lifetime Value --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="8">
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Customer Lifetime Value</h3>
    <p class="mb-3 text-[13px] text-stone-500">Average total spend per customer — how much is a customer worth over their lifetime?</p>
    @if ($clv['total_customers'] === 0)
        <p class="text-[15px] text-stone-500">No customer purchase data yet.</p>
    @else
        <div class="mb-5 grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg border border-stone-100 bg-stone-50 p-4 text-center">
                <div class="text-[13px] font-medium uppercase tracking-wide text-stone-500">Avg CLV</div>
                <div class="mt-1 text-2xl font-bold" style="color: #36a2eb">€{{ number_format($clv['overall_clv'], 2) }}</div>
            </div>
            <div class="rounded-lg border border-stone-100 bg-stone-50 p-4 text-center">
                <div class="text-[13px] font-medium uppercase tracking-wide text-stone-500">Total Customers</div>
                <div class="mt-1 text-2xl font-bold text-stone-700">{{ number_format($clv['total_customers']) }}</div>
            </div>
            <div class="rounded-lg border border-stone-100 bg-stone-50 p-4 text-center">
                <div class="text-[13px] font-medium uppercase tracking-wide text-stone-500">Total Revenue</div>
                <div class="mt-1 text-2xl font-bold" style="color: #4bc0c0">€{{ number_format($clv['total_revenue'], 2) }}</div>
            </div>
        </div>
        @if (!empty($clv['by_year']))
            <div class="h-[260px]">
                <canvas id="clvByYearChart"></canvas>
            </div>
        @endif
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const geoData = @json($customerGeo);
        if (geoData.length) {
            new Chart(document.getElementById('customerGeoChart'), {
                type: 'bar',
                data: {
                    labels: geoData.map(c => c.country || 'Unknown'),
                    datasets: [{
                        label: 'Customers',
                        data: geoData.map(c => c.count),
                        backgroundColor: '#9966ff',
                        borderRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { font: { size: 12 }, color: '#78716c' }, grid: { color: '#f5f5f4' } },
                        y: { ticks: { font: { size: 12 }, color: '#57534e' } }
                    }
                }
            });
        }

        {{-- Cohort Retention Chart --}}
        const cohortData = @json($cohortRetention);
        if (cohortData.length) {
            new Chart(document.getElementById('cohortRetentionChart'), {
                type: 'bar',
                data: {
                    labels: cohortData.map(c => c.year + (c.is_complete ? '' : '*')),
                    datasets: [
                        {
                            label: 'Retention %',
                            data: cohortData.map(c => c.retention_pct),
                            backgroundColor: '#4bc0c0',
                            borderRadius: 6,
                            yAxisID: 'y',
                        },
                        {
                            label: 'Cohort Size',
                            data: cohortData.map(c => c.total_customers),
                            backgroundColor: '#c9cbcf',
                            borderRadius: 6,
                            yAxisID: 'y1',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { size: 12 }, color: '#57534e', padding: 16 } },
                        tooltip: {
                            callbacks: {
                                afterBody: function(ctx) {
                                    const i = ctx[0].dataIndex;
                                    const d = cohortData[i];
                                    return d.retained + ' of ' + d.total_customers + ' returned within 12 mo';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: { font: { size: 12 }, color: '#78716c', callback: v => v + '%' },
                            grid: { color: '#f5f5f4' },
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            ticks: { font: { size: 12 }, color: '#a8a29e' },
                            grid: { display: false },
                        },
                        x: { ticks: { font: { size: 12 }, color: '#57534e' } }
                    }
                }
            });
        }

        {{-- AOV Breakdown Chart --}}
        const aovData = @json($aovBreakdown);
        if (aovData.length) {
            new Chart(document.getElementById('aovBreakdownChart'), {
                type: 'bar',
                data: {
                    labels: aovData.map(r => r.year),
                    datasets: [
                        {
                            label: 'AOV (€)',
                            data: aovData.map(r => r.aov),
                            backgroundColor: '#36a2eb',
                            borderRadius: 6,
                        },
                        {
                            label: 'Avg Price / Item (€)',
                            data: aovData.map(r => r.avg_price_per_item),
                            backgroundColor: '#ff6384',
                            borderRadius: 6,
                        },
                        {
                            label: 'Avg Items / Order',
                            data: aovData.map(r => r.avg_items_per_order),
                            backgroundColor: '#ff9f40',
                            borderRadius: 6,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { size: 12 }, color: '#57534e', padding: 16 } },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    const suffix = ctx.dataset.label.includes('€') ? '' : '';
                                    return ctx.dataset.label + ': ' + ctx.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { font: { size: 12 }, color: '#78716c' }, grid: { color: '#f5f5f4' } },
                        x: { ticks: { font: { size: 12 }, color: '#57534e' } }
                    }
                }
            });
        }

        {{-- RFM Segmentation Doughnut --}}
        const rfmData = @json($rfmSegmentation);
        if (rfmData.segments && Object.keys(rfmData.segments).length) {
            const labels = Object.keys(rfmData.segments);
            const counts = labels.map(s => rfmData.segments[s].count);
            const colors = labels.map(s => rfmData.segments[s].color);

            new Chart(document.getElementById('rfmChart'), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: counts,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#fff',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { size: 12 }, color: '#57534e', padding: 12 } },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                    const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                    return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }

        {{-- CLV by Year Chart --}}
        const clvData = @json($clv);
        if (clvData.by_year && clvData.by_year.length) {
            new Chart(document.getElementById('clvByYearChart'), {
                type: 'bar',
                data: {
                    labels: clvData.by_year.map(r => r.year),
                    datasets: [
                        {
                            label: 'CLV (€)',
                            data: clvData.by_year.map(r => r.clv),
                            backgroundColor: '#36a2eb',
                            borderRadius: 6,
                            yAxisID: 'y',
                        },
                        {
                            label: 'Avg Orders',
                            data: clvData.by_year.map(r => r.avg_orders),
                            backgroundColor: '#ff9f40',
                            borderRadius: 6,
                            yAxisID: 'y1',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { size: 12 }, color: '#57534e', padding: 16 } },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    const prefix = ctx.dataset.label.includes('€') ? '€' : '';
                                    return ctx.dataset.label + ': ' + prefix + ctx.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { font: { size: 12 }, color: '#78716c', callback: v => '€' + v },
                            grid: { color: '#f5f5f4' },
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            ticks: { font: { size: 12 }, color: '#a8a29e' },
                            grid: { display: false },
                        },
                        x: { ticks: { font: { size: 12 }, color: '#57534e' } }
                    }
                }
            });
        }
    });
</script>
@endpush
