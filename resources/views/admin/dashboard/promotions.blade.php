{{-- Discount Margin Impact --}}
<div class="admin-card admin-card-hover rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="1">
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Discount Margin Impact ({{ $periodLabel }})</h3>
    <div class="flex items-baseline gap-3">
        <p class="text-3xl font-bold text-stone-800">{{ $discountImpact['discount_pct'] }}%</p>
        @if (isset($deltas['discount_pct']) && $deltas['discount_pct'] !== null)
            <span class="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $deltas['discount_pct'] > 0 ? 'bg-[#ff6384]/15 text-[#ff6384]' : ($deltas['discount_pct'] < 0 ? 'bg-[#4bc0c0]/15 text-[#4bc0c0]' : 'bg-stone-100 text-stone-500') }}">
                {{ $deltas['discount_pct'] > 0 ? '+' : '' }}{{ $deltas['discount_pct'] }}%
            </span>
        @endif
        <p class="text-[15px] text-stone-500">of gross revenue goes to discounts</p>
    </div>
    <p class="mt-2 text-sm text-stone-400">
        &euro;{{ number_format($discountImpact['total_discounts'], 2) }} discounted out of &euro;{{ number_format($discountImpact['total_gross'], 2) }} gross
    </p>
    <p class="mt-1 text-[11px] text-stone-400">Formula: Total Discounts ÷ Gross Revenue × 100. Lower is better — a rising % means discounts are eating into your margins.</p>
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
        <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Cart Recovery</h3>
        <p class="mb-2 text-[12px] text-stone-400">Abandoned → Reminded → Recovered funnel. Recovery Rate = Recovered ÷ Abandoned × 100.</p>
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

{{-- Top Abandoned Products --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="4">
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Top Abandoned Products</h3>
    <p class="mb-3 text-[13px] text-stone-500">Which items are left behind? If a product shows up here often, investigate price or shipping barriers.</p>
    @if (empty($topAbandonedProducts))
        <p class="text-[15px] text-stone-500">No abandoned cart data for this period.</p>
    @else
        <div class="grid gap-5 lg:grid-cols-2">
            <div class="h-[260px]">
                <canvas id="abandonedProductsChart"></canvas>
            </div>
            <div class="overflow-x-auto" x-data="{ showAll: false, limit: 10 }">
                <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-medium text-stone-600">Product</th>
                            <th class="px-4 py-2.5 text-right font-medium text-stone-600">Abandoned</th>
                            <th class="px-4 py-2.5 text-right font-medium text-stone-600">Total Qty</th>
                            <th class="px-4 py-2.5 text-right font-medium text-stone-600">Avg Price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($topAbandonedProducts as $idx => $item)
                            <tr class="transition hover:bg-stone-50" x-show="showAll || {{ $idx }} < limit">
                                <td class="px-4 py-2.5 font-medium text-stone-700">{{ $item['product_name'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700">{{ $item['times_abandoned'] }}×</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700">{{ $item['total_qty'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-500">&euro;{{ number_format($item['avg_price'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if (count($topAbandonedProducts) > 10)
                    <button @click="showAll = !showAll" class="mt-2 text-sm font-medium text-[#36a2eb] hover:underline" x-text="showAll ? 'Show less' : 'Show all {{ count($topAbandonedProducts) }} products'"></button>
                @endif
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const abandonedData = @json($topAbandonedProducts);
        if (abandonedData.length && document.getElementById('abandonedProductsChart')) {
            new Chart(document.getElementById('abandonedProductsChart'), {
                type: 'bar',
                data: {
                    labels: abandonedData.map(p => p.product_name),
                    datasets: [{
                        label: 'Times Abandoned',
                        data: abandonedData.map(p => p.times_abandoned),
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
