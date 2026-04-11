{{-- KPI Row --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @include('admin.dashboard._kpi-card', [
        'label' => 'AOV — Avg Order Value',
        'value' => '&euro;' . number_format($aov, 2),
        'delta' => $deltas['aov'] ?? null,
        'subtitle' => 'Total Revenue ÷ Number of Paid Orders',
        'delay' => 1,
    ])

    @include('admin.dashboard._kpi-card', [
        'label' => 'Repeat Customer Rate',
        'value' => $repeatRate['repeat_pct'] . '%',
        'delta' => $deltas['repeat_pct'] ?? null,
        'subtitle' => $repeatRate['total'] . ' unique customers · Buyers with 2+ orders ÷ All buyers × 100',
        'delay' => 2,
    ])

    @include('admin.dashboard._kpi-card', [
        'label' => 'Items per Order',
        'value' => number_format($itemsPerOrder, 1),
        'delta' => $deltas['items_per_order'] ?? null,
        'subtitle' => 'Avg number of products in each order',
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

{{-- AOV by Country --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="5">
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">AOV by Country — Average Order Value per Country ({{ $periodLabel }})</h3>
    <p class="mb-3 text-[13px] text-stone-500">Do customers in some countries spend more per order? <span class="font-medium">AOV = Total Revenue ÷ Number of Orders</span> for each country.</p>
    @if (empty($aovByCountry))
        <p class="text-[15px] text-stone-500">No order data yet.</p>
    @else
        <div class="grid gap-5 lg:grid-cols-2">
            <div class="h-[260px]">
                <canvas id="aovByCountryChart"></canvas>
            </div>
            <div class="overflow-x-auto" x-data="{
                sortCol: 'aov',
                sortAsc: false,
                items: {{ Js::from($aovByCountry) }},
                get sorted() {
                    const col = this.sortCol;
                    const dir = this.sortAsc ? 1 : -1;
                    return [...this.items].sort((a, b) => {
                        if (typeof a[col] === 'string') return dir * a[col].localeCompare(b[col]);
                        return dir * (a[col] - b[col]);
                    });
                },
                toggleSort(col) {
                    if (this.sortCol === col) { this.sortAsc = !this.sortAsc; } else { this.sortCol = col; this.sortAsc = col === 'country'; }
                },
                sortIcon(col) {
                    if (this.sortCol !== col) return '↕';
                    return this.sortAsc ? '↑' : '↓';
                }
            }">
                <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                    <thead class="bg-stone-50">
                        <tr>
                            <th @click="toggleSort('country')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Country <span class="text-xs" x-text="sortIcon('country')"></span></th>
                            <th @click="toggleSort('aov')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">AOV <span class="text-xs" x-text="sortIcon('aov')"></span></th>
                            <th @click="toggleSort('orders')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Orders <span class="text-xs" x-text="sortIcon('orders')"></span></th>
                            <th @click="toggleSort('revenue')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Revenue <span class="text-xs" x-text="sortIcon('revenue')"></span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <template x-for="row in sorted" :key="row.country">
                            <tr class="transition hover:bg-stone-50">
                                <td class="whitespace-nowrap px-4 py-2.5 font-medium text-stone-700" x-text="row.country || 'Unknown'"></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold" style="color: #36a2eb" x-text="'€' + Number(row.aov).toFixed(2)"></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700" x-text="Number(row.orders).toLocaleString()"></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-500" x-text="'€' + Number(row.revenue).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

{{-- Top Products --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="5">
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Top Selling Products ({{ $periodLabel }})</h3>
    <p class="mb-2 text-[12px] text-stone-400">Ranked by total revenue. Click any column header to sort. Shows top 10 by default.</p>
    @if (empty($topProducts))
        <p class="text-[15px] text-stone-500">No sales data yet.</p>
    @else
        <div x-data="{
            sortCol: 'revenue',
            sortAsc: false,
            showAll: false,
            limit: 10,
            items: {{ Js::from($topProducts) }},
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
                if (this.sortCol === col) { this.sortAsc = !this.sortAsc; } else { this.sortCol = col; this.sortAsc = col === 'name'; }
            },
            sortIcon(col) {
                if (this.sortCol !== col) return '↕';
                return this.sortAsc ? '↑' : '↓';
            }
        }">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-medium text-stone-600">#</th>
                            <th @click="toggleSort('name')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Product <span class="text-xs" x-text="sortIcon('name')"></span></th>
                            <th @click="toggleSort('units')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Units <span class="text-xs" x-text="sortIcon('units')"></span></th>
                            <th @click="toggleSort('revenue')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Revenue <span class="text-xs" x-text="sortIcon('revenue')"></span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <template x-for="(product, i) in visible" :key="i">
                            <tr class="transition hover:bg-stone-50">
                                <td class="whitespace-nowrap px-4 py-2.5 text-stone-400" x-text="i + 1"></td>
                                <td class="px-4 py-2.5 text-stone-700" x-text="product.name"></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right font-medium text-stone-700" x-text="product.units"></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700" x-text="'€' + Number(product.revenue).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <template x-if="items.length > limit">
                <button @click="showAll = !showAll" class="mt-2 text-sm font-medium text-[#36a2eb] hover:underline" x-text="showAll ? 'Show less' : 'Show all ' + items.length + ' products'"></button>
            </template>
        </div>
    @endif
</div>

{{-- Multi-Year Revenue Overlay & Best Month Benchmark --}}
<div class="grid gap-6 lg:grid-cols-3">
    {{-- Multi-Year Line Overlay --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm lg:col-span-2" data-delay="6">
        <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Monthly Revenue — Multi-Year Overlay</h3>
        <p class="mb-2 text-[12px] text-stone-400">Each line is one year's monthly revenue. Hover over any point to see the exact amount. The dashed line shows the historical average across all years.</p>
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

{{-- Release-to-Release Benchmarking --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="8">
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Release-to-Release Benchmarking</h3>
    <p class="mb-3 text-[12px] text-stone-400">Compare how two products performed in their first days after launch. Pick two products and a time window to see cumulative revenue side by side.</p>

    {{-- Product Selector --}}
    <form method="GET" action="{{ route('admin.dashboard') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="tab" value="sales">
        <input type="hidden" name="period" value="{{ $period }}">
        <input type="hidden" name="compare" value="{{ $compare ? 1 : 0 }}">
        <input type="hidden" name="compare_mode" value="{{ $compareMode }}">
        @if ($period === 'custom' && $customFrom && $customTo)
            <input type="hidden" name="custom_from" value="{{ $customFrom }}">
            <input type="hidden" name="custom_to" value="{{ $customTo }}">
        @endif

        <div class="flex-1 min-w-[180px]">
            <label class="mb-1 block text-xs font-medium text-stone-500">Product A</label>
            <select name="benchmark_a" class="w-full rounded-md border border-stone-300 px-3 py-1.5 text-sm text-stone-700 focus:border-[#36a2eb] focus:ring-1 focus:ring-[#36a2eb]">
                <option value="">Select product…</option>
                @foreach ($benchmarkableProducts as $bp)
                    <option value="{{ $bp['id'] }}" {{ $benchmarkA == $bp['id'] ? 'selected' : '' }}>
                        {{ $bp['name'] }} ({{ $bp['published_at'] }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="flex-1 min-w-[180px]">
            <label class="mb-1 block text-xs font-medium text-stone-500">Product B</label>
            <select name="benchmark_b" class="w-full rounded-md border border-stone-300 px-3 py-1.5 text-sm text-stone-700 focus:border-[#36a2eb] focus:ring-1 focus:ring-[#36a2eb]">
                <option value="">Select product…</option>
                @foreach ($benchmarkableProducts as $bp)
                    <option value="{{ $bp['id'] }}" {{ $benchmarkB == $bp['id'] ? 'selected' : '' }}>
                        {{ $bp['name'] }} ({{ $bp['published_at'] }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="w-24">
            <label class="mb-1 block text-xs font-medium text-stone-500">Days</label>
            <select name="benchmark_days" class="w-full rounded-md border border-stone-300 px-3 py-1.5 text-sm text-stone-700 focus:border-[#36a2eb] focus:ring-1 focus:ring-[#36a2eb]">
                @foreach ([7, 14, 30, 60, 90] as $d)
                    <option value="{{ $d }}" {{ $benchmarkDays == $d ? 'selected' : '' }}>{{ $d }}d</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="rounded-md bg-[#36a2eb] px-4 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-[#36a2eb]/90">
            Compare
        </button>
    </form>

    {{-- Benchmark Chart --}}
    @if ($releaseBenchmark && count($releaseBenchmark['products']) === 2)
        <div class="h-[280px]">
            <canvas id="releaseBenchmarkChart"></canvas>
        </div>
    @elseif ($benchmarkA && $benchmarkB)
        <p class="text-[15px] text-stone-500">One or both products don't have a publish date or sales data.</p>
    @else
        <p class="text-[15px] text-stone-500">Select two products above to compare their launch performance.</p>
    @endif
</div>

{{-- Regional Growth Trend --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="9">
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Regional Growth Trend (Quarterly)</h3>
    <p class="mb-2 text-[12px] text-stone-400">Revenue per quarter for your top countries. Upward lines mean growing markets; flat or dropping lines need attention.</p>
    @if (empty($regionalGrowth['countries']))
        <p class="text-[15px] text-stone-500">Not enough order data to show regional trends.</p>
    @else
        <div class="h-[300px]">
            <canvas id="regionalGrowthChart"></canvas>
        </div>
    @endif
</div>

{{-- Product Decay Tracking --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="10">
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Product Sales Velocity (from Launch)</h3>
    <p class="mb-2 text-[12px] text-stone-400">Units sold per month after launch. Percentages show each month's sales relative to Month 1 — a drop means the product is losing momentum.</p>
    @if (empty($productDecay))
        <p class="text-[15px] text-stone-500">No products with a published date more than 2 months old.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-medium text-stone-600">Product</th>
                        <th class="px-4 py-2.5 text-center font-medium text-stone-600">Launch</th>
                        @for ($m = 1; $m <= 6; $m++)
                            <th class="px-4 py-2.5 text-right font-medium text-stone-600">Mo {{ $m }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($productDecay as $pd)
                        <tr class="transition hover:bg-stone-50">
                            <td class="px-4 py-2.5 text-stone-700 max-w-[200px] truncate" title="{{ $pd['name'] }}">{{ $pd['name'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-center text-stone-500 text-sm">{{ $pd['published_at'] }}</td>
                            @for ($m = 0; $m < 6; $m++)
                                @if (isset($pd['months'][$m]))
                                    @php
                                        $vel = $pd['months'][$m]['velocity_pct'];
                                        $velColor = $vel === null ? 'text-stone-400'
                                            : ($vel >= 80 ? 'text-[#4bc0c0]'
                                            : ($vel >= 40 ? 'text-[#ff9f40]'
                                            : 'text-[#ff6384]'));
                                    @endphp
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                        <span class="font-medium text-stone-700">{{ $pd['months'][$m]['units'] }}</span>
                                        @if ($vel !== null && $m > 0)
                                            <span class="ml-1 text-xs {{ $velColor }}">{{ number_format($vel, 0) }}%</span>
                                        @endif
                                    </td>
                                @else
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-300">—</td>
                                @endif
                            @endfor
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-stone-400">Percentages are relative to Month 1 units. <span class="text-[#4bc0c0]">≥80%</span> strong, <span class="text-[#ff9f40]">40–79%</span> fading, <span class="text-[#ff6384]">&lt;40%</span> declining.</p>
    @endif
</div>

{{-- First-Purchase Heroes --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="10">
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">First-Purchase Heroes</h3>
    <p class="mb-3 text-[13px] text-stone-500">Which products do new customers buy first? Identifies the "entry point" products that bring people in.</p>
    @if (empty($firstPurchaseHeroes))
        <p class="text-[15px] text-stone-500">No first-purchase data available yet.</p>
    @else
        <div class="grid gap-5 lg:grid-cols-2">
            <div class="h-[260px]">
                <canvas id="firstPurchaseChart"></canvas>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-medium text-stone-600">Product</th>
                            <th class="px-4 py-2.5 text-right font-medium text-stone-600">First Purchases</th>
                            <th class="px-4 py-2.5 text-right font-medium text-stone-600">Share</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($firstPurchaseHeroes as $hero)
                            <tr class="transition hover:bg-stone-50">
                                <td class="px-4 py-2.5 font-medium text-stone-700">{{ $hero['product_name'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700">{{ number_format($hero['first_purchases']) }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-500">{{ $hero['pct'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

{{-- Product Affinity (Market Basket) --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="11">
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Product Affinity (Market Basket)</h3>
    <p class="mb-3 text-[13px] text-stone-500">"Customers who bought X also bought Y" — use this for "Complete the Look" upsells. <span class="font-medium">Affinity % = Co-purchases ÷ Total purchases of Product A × 100</span>.</p>
    @if (empty($productAffinity))
        <p class="text-[15px] text-stone-500">No multi-product order data yet. Affinity appears once customers start buying 2+ products together.</p>
    @else
        <div class="overflow-x-auto" x-data="{
            sortCol: 'affinity_pct',
            sortAsc: false,
            showAll: false,
            limit: 10,
            items: {{ Js::from($productAffinity) }},
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
                if (this.sortCol === col) { this.sortAsc = !this.sortAsc; } else { this.sortCol = col; this.sortAsc = (col === 'product_a' || col === 'product_b'); }
            },
            sortIcon(col) {
                if (this.sortCol !== col) return '↕';
                return this.sortAsc ? '↑' : '↓';
            }
        }">
            <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                <thead class="bg-stone-50">
                    <tr>
                        <th @click="toggleSort('product_a')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Product A <span class="text-xs" x-text="sortIcon('product_a')"></span></th>
                        <th @click="toggleSort('product_b')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Product B <span class="text-xs" x-text="sortIcon('product_b')"></span></th>
                        <th @click="toggleSort('co_purchases')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Bought Together <span class="text-xs" x-text="sortIcon('co_purchases')"></span></th>
                        <th @click="toggleSort('affinity_pct')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Affinity <span class="text-xs" x-text="sortIcon('affinity_pct')"></span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <template x-for="pair in visible" :key="pair.product_a + pair.product_b">
                        <tr class="transition hover:bg-stone-50">
                            <td class="px-4 py-2.5 font-medium text-stone-700" x-text="pair.product_a"></td>
                            <td class="px-4 py-2.5 font-medium text-stone-700" x-text="pair.product_b"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700" x-text="pair.co_purchases + '×'"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="pair.affinity_pct >= 50 ? 'bg-[#4bc0c0]/15 text-[#4bc0c0]' : (pair.affinity_pct >= 25 ? 'bg-[#ff9f40]/15 text-[#ff9f40]' : 'bg-stone-100 text-stone-500')"
                                    x-text="pair.affinity_pct + '%'"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <template x-if="items.length > limit">
                <button @click="showAll = !showAll" class="mt-2 text-sm font-medium text-[#36a2eb] hover:underline" x-text="showAll ? 'Show less' : 'Show all ' + items.length + ' pairs'"></button>
            </template>
        </div>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const revenueData = @json($revenueOverTime);
        const countryData = @json($revenueByCountry);
        const prevRevenueData = @json($prevRevenueOverTime ?? null);
        const yearlyRevenue = @json($yearlyRevenue);
        const releaseBenchmark = @json($releaseBenchmark ?? null);
        const regionalGrowth = @json($regionalGrowth);

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
                    pointRadius: isCurrent ? 4 : 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: yearColors[idx % yearColors.length],
                    pointBorderColor: '#fff',
                    pointBorderWidth: isCurrent ? 2 : 1,
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
                    plugins: {
                        legend: { position: 'bottom', labels: { padding: 16, font: { size: 11 }, color: '#57534e' } },
                        tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': €' + (ctx.parsed.y ?? 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) } }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 12 }, color: '#78716c' } },
                        y: { beginAtZero: true, ticks: { callback: v => '€' + v.toLocaleString(), font: { size: 12 }, color: '#78716c' }, grid: { color: '#f5f5f4' } }
                    }
                }
            });
        }

        // Release-to-Release Benchmark Chart
        if (releaseBenchmark && releaseBenchmark.products.length === 2 && document.getElementById('releaseBenchmarkChart')) {
            const productA = releaseBenchmark.products[0];
            const productB = releaseBenchmark.products[1];
            const dataA = releaseBenchmark.comparison[productA.id] || [];
            const dataB = releaseBenchmark.comparison[productB.id] || [];

            new Chart(document.getElementById('releaseBenchmarkChart'), {
                type: 'line',
                data: {
                    labels: dataA.map(d => 'Day ' + d.day),
                    datasets: [
                        {
                            label: productA.name,
                            data: dataA.map(d => d.cumulative_revenue),
                            borderColor: '#36a2eb',
                            backgroundColor: 'rgba(54, 162, 235, 0.06)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 0,
                            borderWidth: 2.5,
                        },
                        {
                            label: productB.name,
                            data: dataB.map(d => d.cumulative_revenue),
                            borderColor: '#ff6384',
                            backgroundColor: 'rgba(255, 99, 132, 0.06)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 0,
                            borderWidth: 2.5,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 }, color: '#57534e' } },
                        tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': €' + ctx.parsed.y.toLocaleString() } }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxTicksLimit: 10, font: { size: 11 }, color: '#78716c' } },
                        y: { beginAtZero: true, ticks: { callback: v => '€' + v.toLocaleString(), font: { size: 12 }, color: '#78716c' }, grid: { color: '#f5f5f4' } }
                    }
                }
            });
        }

        // Regional Growth Trend Chart
        if (regionalGrowth && regionalGrowth.countries.length > 0 && document.getElementById('regionalGrowthChart')) {
            const regionColors = ['#36a2eb', '#ff6384', '#4bc0c0', '#ff9f40', '#9966ff', '#c9cbcf'];
            const regionDatasets = regionalGrowth.countries.map((country, idx) => ({
                label: country,
                data: regionalGrowth.series[country],
                borderColor: regionColors[idx % regionColors.length],
                backgroundColor: 'transparent',
                borderWidth: 2,
                tension: 0.35,
                pointRadius: 3,
                pointBackgroundColor: regionColors[idx % regionColors.length],
                pointBorderColor: '#fff',
                pointBorderWidth: 1,
            }));

            new Chart(document.getElementById('regionalGrowthChart'), {
                type: 'line',
                data: {
                    labels: regionalGrowth.quarters,
                    datasets: regionDatasets,
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

        // AOV by Country Chart
        const aovCountryData = @json($aovByCountry);
        if (aovCountryData.length && document.getElementById('aovByCountryChart')) {
            new Chart(document.getElementById('aovByCountryChart'), {
                type: 'bar',
                data: {
                    labels: aovCountryData.map(r => r.country || 'Unknown'),
                    datasets: [{
                        label: 'AOV (€)',
                        data: aovCountryData.map(r => r.aov),
                        backgroundColor: '#36a2eb',
                        borderRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    return 'AOV: €' + ctx.parsed.y.toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: v => '€' + v, font: { size: 12 }, color: '#78716c' }, grid: { color: '#f5f5f4' } },
                        x: { ticks: { font: { size: 12 }, color: '#57534e' } }
                    }
                }
            });
        }

        // First-Purchase Heroes Chart
        const heroData = @json($firstPurchaseHeroes);
        if (heroData.length && document.getElementById('firstPurchaseChart')) {
            const heroColors = ['#36a2eb', '#ff6384', '#4bc0c0', '#ff9f40', '#9966ff', '#c9cbcf', '#e7e5e4', '#57534e', '#78716c', '#a8a29e'];
            new Chart(document.getElementById('firstPurchaseChart'), {
                type: 'doughnut',
                data: {
                    labels: heroData.map(h => h.product_name),
                    datasets: [{
                        data: heroData.map(h => h.first_purchases),
                        backgroundColor: heroData.map((_, i) => heroColors[i % heroColors.length]),
                        borderWidth: 2,
                        borderColor: '#fff',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { size: 11 }, color: '#57534e', padding: 10 } },
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
    });
</script>
@endpush
