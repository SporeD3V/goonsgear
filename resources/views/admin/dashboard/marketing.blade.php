{{-- KPI Row --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-2">
    @include('admin.dashboard._kpi-card', [
        'label' => 'Newsletter Subscribers',
        'value' => number_format($newsletterCount),
        'subtitle' => 'Total opted-in newsletter subscribers',
        'delay' => 1,
    ])

    @include('admin.dashboard._kpi-card', [
        'label' => 'Cart Recovery Rate',
        'value' => $cartRecovery['recovery_pct'] . '%',
        'delta' => $deltas['recovery_pct'] ?? null,
        'subtitle' => $cartRecovery['recovered'] . ' recovered of ' . $cartRecovery['reminded'] . ' reminded',
        'delay' => 2,
    ])
</div>

<div class="grid gap-6 lg:grid-cols-2">
    {{-- Coupon Leaderboard --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="2">
        <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Coupon Leaderboard</h3>
        <p class="mb-2 text-[12px] text-stone-400">Which discount codes are used most? Click column headers to sort.</p>
        @if (empty($couponLeaderboard))
            <p class="text-[15px] text-stone-500">No coupon usage data.</p>
        @else
            <div class="overflow-x-auto" x-data="{
                sortCol: 'total_discounted',
                sortAsc: false,
                showAll: false,
                limit: 10,
                items: {{ Js::from($couponLeaderboard) }},
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
                    if (this.sortCol === col) { this.sortAsc = !this.sortAsc; } else { this.sortCol = col; this.sortAsc = col === 'code'; }
                },
                sortIcon(col) {
                    if (this.sortCol !== col) return '↕';
                    return this.sortAsc ? '↑' : '↓';
                }
            }">
                <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                    <thead class="bg-stone-50">
                        <tr>
                            <th @click="toggleSort('code')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Code <span class="text-xs" x-text="sortIcon('code')"></span></th>
                            <th @click="toggleSort('times_used')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Uses <span class="text-xs" x-text="sortIcon('times_used')"></span></th>
                            <th @click="toggleSort('total_discounted')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Total Discounted <span class="text-xs" x-text="sortIcon('total_discounted')"></span></th>
                            <th @click="toggleSort('avg_discount')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Avg Discount <span class="text-xs" x-text="sortIcon('avg_discount')"></span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <template x-for="coupon in visible" :key="coupon.code">
                            <tr class="transition hover:bg-stone-50">
                                <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs font-medium text-stone-700" x-text="coupon.code"></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700" x-text="coupon.times_used"></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700" x-text="'€' + Number(coupon.total_discounted).toFixed(2)"></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-500" x-text="'€' + Number(coupon.avg_discount).toFixed(2)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <template x-if="items.length > limit">
                    <button @click="showAll = !showAll" class="mt-2 text-sm font-medium text-[#36a2eb] hover:underline" x-text="showAll ? 'Show less' : 'Show all ' + items.length + ' coupons'"></button>
                </template>
            </div>
        @endif
    </div>

    {{-- Cart Recovery Funnel --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="3">
        <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Cart Recovery Funnel</h3>
        <p class="mb-2 text-[12px] text-stone-400">Abandoned → Reminded → Recovered funnel. Recovery Rate = Recovered ÷ Reminded × 100.</p>
        <div class="space-y-3">
            @php
                $funnel = [
                    ['label' => 'Abandoned', 'value' => $cartRecovery['abandoned'], 'color' => 'bg-red-50 text-red-700 border-red-200'],
                    ['label' => 'Reminded', 'value' => $cartRecovery['reminded'], 'color' => 'bg-amber-50 text-amber-700 border-amber-200'],
                    ['label' => 'Recovered', 'value' => $cartRecovery['recovered'], 'color' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                ];
            @endphp
            @foreach ($funnel as $step)
                <div class="flex items-center justify-between rounded-xl border {{ $step['color'] }} p-4 transition hover:shadow-sm">
                    <span class="text-[15px] font-medium">{{ $step['label'] }}</span>
                    <span class="text-xl font-bold">{{ $step['value'] }}</span>
                </div>
            @endforeach
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-center">
                <p class="text-sm font-medium text-blue-600">Recovery Rate</p>
                <div class="flex items-center justify-center gap-2">
                    <p class="text-3xl font-bold text-blue-700">{{ $cartRecovery['recovery_pct'] }}%</p>
                    @if (isset($deltas['recovery_pct']) && $deltas['recovery_pct'] !== null)
                        <span class="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $deltas['recovery_pct'] > 0 ? 'bg-[#4bc0c0]/15 text-[#4bc0c0]' : ($deltas['recovery_pct'] < 0 ? 'bg-[#ff6384]/15 text-[#ff6384]' : 'bg-stone-100 text-stone-500') }}">
                            {{ $deltas['recovery_pct'] > 0 ? '+' : '' }}{{ $deltas['recovery_pct'] }}%
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Waitlist Conversion Benchmarking --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="4">
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

{{-- Top Abandoned Products --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="5">
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Top Abandoned Products</h3>
    <p class="mb-3 text-[13px] text-stone-500">Which items are left behind? If a product shows up here often, investigate price or shipping barriers.</p>
    @if (empty($topAbandonedProducts))
        <p class="text-[15px] text-stone-500">No abandoned cart data for this period.</p>
    @else
        <div class="grid gap-5 lg:grid-cols-2">
            <div class="h-[260px]">
                <canvas id="abandonedProductsChart"></canvas>
            </div>
            <div class="overflow-x-auto" x-data="{
                sortCol: 'times_abandoned',
                sortAsc: false,
                showAll: false,
                limit: 10,
                items: {{ Js::from($topAbandonedProducts) }},
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
                    if (this.sortCol === col) { this.sortAsc = !this.sortAsc; } else { this.sortCol = col; this.sortAsc = col === 'product_name'; }
                },
                sortIcon(col) {
                    if (this.sortCol !== col) return '↕';
                    return this.sortAsc ? '↑' : '↓';
                }
            }">
                <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                    <thead class="bg-stone-50">
                        <tr>
                            <th @click="toggleSort('product_name')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Product <span class="text-xs" x-text="sortIcon('product_name')"></span></th>
                            <th @click="toggleSort('times_abandoned')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Abandoned <span class="text-xs" x-text="sortIcon('times_abandoned')"></span></th>
                            <th @click="toggleSort('total_qty')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Total Qty <span class="text-xs" x-text="sortIcon('total_qty')"></span></th>
                            <th @click="toggleSort('avg_price')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Avg Price <span class="text-xs" x-text="sortIcon('avg_price')"></span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <template x-for="item in visible" :key="item.product_name">
                            <tr class="transition hover:bg-stone-50">
                                <td class="px-4 py-2.5 font-medium text-stone-700" x-text="item.product_name"></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700" x-text="item.times_abandoned + '×'"></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700" x-text="item.total_qty"></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-500" x-text="'€' + Number(item.avg_price).toFixed(2)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <template x-if="items.length > limit">
                    <button @click="showAll = !showAll" class="mt-2 text-sm font-medium text-[#36a2eb] hover:underline" x-text="showAll ? 'Show less' : 'Show all ' + items.length + ' products'"></button>
                </template>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const abandonedData = @json($topAbandonedProducts);
        const abandonedChartData = abandonedData.slice(0, 10);
        if (abandonedChartData.length && document.getElementById('abandonedProductsChart')) {
            new Chart(document.getElementById('abandonedProductsChart'), {
                type: 'bar',
                data: {
                    labels: abandonedChartData.map(p => p.product_name),
                    datasets: [{
                        label: 'Times Abandoned',
                        data: abandonedChartData.map(p => p.times_abandoned),
                        backgroundColor: '#ff6384',
                        borderRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    return 'Abandoned ' + ctx.parsed.x + ' times';
                                }
                            }
                        }
                    },
                    scales: {
                        x: { beginAtZero: true, ticks: { font: { size: 12 }, color: '#78716c', precision: 0 }, grid: { color: '#f5f5f4' } },
                        y: { ticks: { font: { size: 11 }, color: '#57534e' } }
                    }
                }
            });
        }
    });
</script>
@endpush
