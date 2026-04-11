{{-- KPI Row --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @include('admin.dashboard._kpi-card', [
        'label' => 'Active Customers (' . $periodLabel . ')',
        'value' => number_format($customerStats['active_in_period']),
        'delta' => $deltas['active_in_period'] ?? null,
        'delay' => 1,
    ])

    @include('admin.dashboard._kpi-card', [
        'label' => 'New (' . $periodLabel . ')',
        'value' => number_format($customerStats['new_in_period']),
        'delta' => $deltas['new_in_period'] ?? null,
        'delay' => 2,
    ])

    @include('admin.dashboard._kpi-card', [
        'label' => 'Repeat Customer Rate',
        'value' => $repeatRate['repeat_pct'] . '%',
        'delta' => $deltas['repeat_pct'] ?? null,
        'subtitle' => $repeatRate['total'] . ' unique customers · Buyers with 2+ orders ÷ All buyers × 100',
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

{{-- Tag Follow Popularity --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="3">
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Tag Follow Popularity</h3>
    @if (empty($tagFollows))
        <p class="text-[15px] text-stone-500">No tag follow data yet.</p>
    @else
        <div class="overflow-x-auto" x-data="{
            sortCol: 'followers',
            sortAsc: false,
            showAll: false,
            limit: 10,
            items: {{ Js::from($tagFollows) }},
            get sorted() {
                const col = this.sortCol;
                const dir = this.sortAsc ? 1 : -1;
                return [...this.items].sort((a, b) => {
                    if (typeof a[col] === 'string') return dir * a[col].localeCompare(b[col]);
                    return dir * (a[col] - b[col]);
                });
            },
            get visible() {
                return this.showAll ? this.sorted : this.sorted.slice(0, this.limit);
            },
            toggleSort(col) {
                if (this.sortCol === col) { this.sortAsc = !this.sortAsc; } else { this.sortCol = col; this.sortAsc = (col === 'name' || col === 'type'); }
            },
            sortIcon(col) {
                if (this.sortCol !== col) return '↕';
                return this.sortAsc ? '↑' : '↓';
            }
        }">
            @php
                $typeColors = ['artist' => 'bg-[#ff9f40]/15 text-[#ff9f40]', 'brand' => 'bg-[#4bc0c0]/15 text-[#4bc0c0]', 'custom' => 'bg-stone-100 text-stone-700'];
                $typeColorsJs = Js::from($typeColors);
            @endphp
            <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                <thead class="bg-stone-50">
                    <tr>
                        <th @click="toggleSort('name')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Tag <span class="text-xs" x-text="sortIcon('name')"></span></th>
                        <th @click="toggleSort('type')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Type <span class="text-xs" x-text="sortIcon('type')"></span></th>
                        <th @click="toggleSort('followers')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Followers <span class="text-xs" x-text="sortIcon('followers')"></span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <template x-for="tag in visible" :key="tag.name">
                        <tr class="transition hover:bg-stone-50">
                            <td class="px-4 py-2.5 font-medium text-stone-700" x-text="tag.name"></td>
                            <td class="whitespace-nowrap px-4 py-2.5">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="({{ $typeColorsJs }})[tag.type] || 'bg-stone-100 text-stone-700'"
                                    x-text="tag.type.charAt(0).toUpperCase() + tag.type.slice(1)"></span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right font-medium text-stone-700" x-text="tag.followers"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <template x-if="items.length > limit">
                <button @click="showAll = !showAll" class="mt-2 text-sm font-medium text-[#36a2eb] hover:underline" x-text="showAll ? 'Show less' : 'Show all ' + items.length + ' tags'"></button>
            </template>
        </div>
    @endif
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

{{-- RFM Segmentation --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="5">
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Customer Segments (RFM)</h3>
    <p class="mb-3 text-[13px] text-stone-500">Customers grouped by Recency, Frequency & Monetary value. Based on {{ number_format($rfmSegmentation['customers_analyzed']) }} customers.</p>
    @if (empty($rfmSegmentation['segments']))
        <p class="text-[15px] text-stone-500">Not enough order data for segmentation.</p>
    @else
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="h-[260px]">
                <canvas id="rfmChart"></canvas>
            </div>
            <div class="overflow-x-auto" x-data="{
                sortCol: 'count',
                sortAsc: false,
                items: {{ Js::from(collect($rfmSegmentation['segments'])->map(fn ($data, $segment) => array_merge($data, ['segment' => $segment]))->values()->all()) }},
                get sorted() {
                    const col = this.sortCol;
                    const dir = this.sortAsc ? 1 : -1;
                    return [...this.items].sort((a, b) => {
                        if (typeof a[col] === 'string') return dir * a[col].localeCompare(b[col]);
                        return dir * (a[col] - b[col]);
                    });
                },
                toggleSort(col) {
                    if (this.sortCol === col) { this.sortAsc = !this.sortAsc; } else { this.sortCol = col; this.sortAsc = col === 'segment'; }
                },
                sortIcon(col) {
                    if (this.sortCol !== col) return '↕';
                    return this.sortAsc ? '↑' : '↓';
                }
            }">
                <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                    <thead class="bg-stone-50">
                        <tr>
                            <th @click="toggleSort('segment')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Segment <span class="text-xs" x-text="sortIcon('segment')"></span></th>
                            <th @click="toggleSort('count')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Customers <span class="text-xs" x-text="sortIcon('count')"></span></th>
                            <th @click="toggleSort('avg_revenue')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Avg Revenue <span class="text-xs" x-text="sortIcon('avg_revenue')"></span></th>
                            <th @click="toggleSort('avg_orders')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Avg Orders <span class="text-xs" x-text="sortIcon('avg_orders')"></span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <template x-for="row in sorted" :key="row.segment">
                            <tr class="transition hover:bg-stone-50">
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="inline-block h-2.5 w-2.5 rounded-full" :style="'background:' + row.color"></span>
                                        <span class="font-medium text-stone-700" x-text="row.segment"></span>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700" x-text="Number(row.count).toLocaleString()"></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700" x-text="'€' + Number(row.avg_revenue).toFixed(2)"></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700" x-text="row.avg_orders"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

{{-- Customer Lifetime Value --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="6">
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

{{-- VIP Churn Warning --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="7">
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">VIP Churn Warning</h3>
    <p class="mb-3 text-[13px] text-stone-500">Top 5% spenders who haven't ordered within their personalised churn window (2.5× their average purchase interval, min 90d, max 365d).</p>
    @if ($vipChurn['vip_total'] === 0)
        <p class="text-[15px] text-stone-500">No customer data available yet.</p>
    @elseif (empty($vipChurn['churning']))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-center">
            <p class="text-[15px] font-medium text-emerald-700">All {{ $vipChurn['vip_total'] }} VIP customers are active</p>
            <p class="mt-1 text-[13px] text-emerald-600">VIP threshold: &euro;{{ number_format($vipChurn['vip_threshold'], 2) }}+ total spend</p>
        </div>
    @else
        <div class="mb-3 flex items-center gap-4">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-3 py-1 text-sm font-semibold text-red-700">
                {{ count($vipChurn['churning']) }} at risk
            </span>
            <span class="text-[13px] text-stone-500">of {{ $vipChurn['vip_total'] }} VIPs (threshold: &euro;{{ number_format($vipChurn['vip_threshold'], 2) }})</span>
        </div>
        <div class="overflow-x-auto" x-data="{
            sortCol: 'days_since_last',
            sortAsc: false,
            page: 1,
            perPage: 50,
            items: {{ Js::from($vipChurn['churning']) }},
            get sorted() {
                const col = this.sortCol;
                const dir = this.sortAsc ? 1 : -1;
                return [...this.items].sort((a, b) => {
                    if (typeof a[col] === 'string') return dir * a[col].localeCompare(b[col]);
                    return dir * (a[col] - b[col]);
                });
            },
            get visible() {
                return this.sorted.slice(0, this.page * this.perPage);
            },
            get hasMore() {
                return this.visible.length < this.sorted.length;
            },
            toggleSort(col) {
                if (this.sortCol === col) { this.sortAsc = !this.sortAsc; } else { this.sortCol = col; this.sortAsc = col === 'email'; }
                this.page = 1;
            },
            sortIcon(col) {
                if (this.sortCol !== col) return '↕';
                return this.sortAsc ? '↑' : '↓';
            }
        }">
            <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                <thead class="bg-stone-50">
                    <tr>
                        <th @click="toggleSort('email')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Customer <span class="text-xs" x-text="sortIcon('email')"></span></th>
                        <th @click="toggleSort('total_spent')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Total Spent <span class="text-xs" x-text="sortIcon('total_spent')"></span></th>
                        <th @click="toggleSort('order_count')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Orders <span class="text-xs" x-text="sortIcon('order_count')"></span></th>
                        <th @click="toggleSort('days_since_last')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Days Silent <span class="text-xs" x-text="sortIcon('days_since_last')"></span></th>
                        <th @click="toggleSort('churn_threshold')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Threshold <span class="text-xs" x-text="sortIcon('churn_threshold')"></span></th>
                        <th @click="toggleSort('last_order')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Last Order <span class="text-xs" x-text="sortIcon('last_order')"></span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <template x-for="vip in visible" :key="vip.email">
                        <tr class="transition hover:bg-stone-50">
                            <td class="px-4 py-2.5 font-medium text-stone-700" x-text="vip.email"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700" x-text="'€' + Number(vip.total_spent).toFixed(2)"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700" x-text="vip.order_count"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="vip.days_since_last >= 180 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'"
                                    x-text="vip.days_since_last + 'd'"></span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-500" x-text="vip.churn_threshold + 'd'"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-500" x-text="vip.last_order"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <template x-if="hasMore">
                <button @click="page++" class="mt-2 text-sm font-medium text-[#36a2eb] hover:underline" x-text="'Load more (' + (items.length - visible.length) + ' remaining)'"></button>
            </template>
        </div>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
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
