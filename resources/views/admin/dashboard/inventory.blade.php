@php
    $health = (object) $stockHealth;
@endphp

{{-- Operational Metrics: Pre-order Liability + Fulfillment Speed --}}
<div class="grid gap-6 lg:grid-cols-2">
    {{-- Pre-order Liability --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="1">
        <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Pre-order Liability</h3>
        <p class="mb-3 text-[12px] text-stone-400">Cash collected for pre-orders not yet shipped. Split by product value vs. shipping & tax so you can see your real obligation.</p>
        @if ($preorderLiability['order_count'] === 0)
            <p class="text-[15px] text-stone-500">No active pre-orders with payment confirmed.</p>
        @else
            <div class="flex items-baseline gap-3">
                <p class="text-3xl font-bold text-[#ff9f40]">&euro;{{ number_format($preorderLiability['total_liability'], 2) }}</p>
                <span class="text-[15px] text-stone-500">total collected</span>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-lg border border-stone-100 bg-stone-50 p-3 text-center">
                    <div class="text-[13px] text-stone-500">Product Value</div>
                    <div class="text-lg font-bold text-stone-700">&euro;{{ number_format($preorderLiability['product_liability'], 2) }}</div>
                </div>
                <div class="rounded-lg border border-stone-100 bg-stone-50 p-3 text-center">
                    <div class="text-[13px] text-stone-500">Shipping + Tax</div>
                    <div class="text-lg font-bold text-stone-700">&euro;{{ number_format($preorderLiability['shipping_liability'] + $preorderLiability['tax_liability'], 2) }}</div>
                </div>
                <div class="rounded-lg border border-stone-100 bg-stone-50 p-3 text-center">
                    <div class="text-[13px] text-stone-500">Orders</div>
                    <div class="text-lg font-bold text-stone-700">{{ $preorderLiability['order_count'] }}</div>
                </div>
                <div class="rounded-lg border border-stone-100 bg-stone-50 p-3 text-center">
                    <div class="text-[13px] text-stone-500">Items</div>
                    <div class="text-lg font-bold text-stone-700">{{ $preorderLiability['item_count'] }}</div>
                </div>
            </div>
            <p class="mt-2 text-[11px] text-stone-400">Only paid pre-orders. Product = subtotal, Shipping + Tax = shipping_total + tax_total.</p>
        @endif
    </div>

    {{-- Fulfillment Speed --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="1">
        <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Fulfillment Speed ({{ $periodLabel }})</h3>
        <p class="mb-3 text-[12px] text-stone-400">How long from when an order is placed until it's shipped? Faster = happier customers.</p>
        @if ($fulfillmentSpeed['shipped_count'] === 0)
            <p class="text-[15px] text-stone-500">No shipped orders with both placed and shipped dates in this period.</p>
        @else
            <div class="flex items-baseline gap-3">
                <p class="text-3xl font-bold" style="color: #36a2eb">{{ $fulfillmentSpeed['median_days'] }}</p>
                <span class="text-[15px] text-stone-500">median days to ship</span>
            </div>
            <div class="mt-3 grid grid-cols-3 gap-3">
                <div class="rounded-lg border border-stone-100 bg-stone-50 p-3 text-center">
                    <div class="text-[13px] text-stone-500">Average</div>
                    <div class="text-lg font-bold text-stone-700">{{ $fulfillmentSpeed['avg_days'] }}d</div>
                </div>
                <div class="rounded-lg border border-stone-100 bg-stone-50 p-3 text-center">
                    <div class="text-[13px] text-stone-500">Fastest</div>
                    <div class="text-lg font-bold text-[#4bc0c0]">{{ $fulfillmentSpeed['fastest_days'] }}d</div>
                </div>
                <div class="rounded-lg border border-stone-100 bg-stone-50 p-3 text-center">
                    <div class="text-[13px] text-stone-500">Slowest</div>
                    <div class="text-lg font-bold text-[#ff6384]">{{ $fulfillmentSpeed['slowest_days'] }}d</div>
                </div>
            </div>
            <p class="mt-2 text-[11px] text-stone-400">Based on {{ $fulfillmentSpeed['shipped_count'] }} shipped orders. Formula: shipped_at − placed_at in days.</p>
        @endif
    </div>
</div>

{{-- Stock Health Bar --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="1">
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Inventory Health</h3>
    <p class="mb-3 text-[12px] text-stone-400">Active product variants grouped by current stock level. "Almost Sold Out" means 1–5 units left — could sell out very soon.</p>
    <div class="grid gap-4 sm:grid-cols-5">
        <div class="admin-card-hover rounded-xl border border-red-200 bg-red-50 p-4 text-center" data-delay="1">
            <p class="text-3xl font-bold text-red-700">{{ (int) $health->out_of_stock }}</p>
            <p class="text-sm font-medium text-red-600">Sold Out</p>
        </div>
        <div class="admin-card-hover rounded-xl border border-amber-200 bg-amber-50 p-4 text-center" data-delay="2">
            <p class="text-3xl font-bold text-amber-700">{{ (int) $health->critical }}</p>
            <p class="text-sm font-medium text-amber-600">Almost Sold Out (1–5)</p>
        </div>
        <div class="admin-card-hover rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-center" data-delay="3">
            <p class="text-3xl font-bold text-yellow-700">{{ (int) $health->low }}</p>
            <p class="text-sm font-medium text-yellow-600">Low Stock (6–20)</p>
        </div>
        <div class="admin-card-hover rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center" data-delay="4">
            <p class="text-3xl font-bold text-emerald-700">{{ (int) $health->healthy }}</p>
            <p class="text-sm font-medium text-emerald-600">Healthy Stock (21–100)</p>
        </div>
        <div class="admin-card-hover rounded-xl border border-blue-200 bg-blue-50 p-4 text-center" data-delay="5">
            <p class="text-3xl font-bold text-blue-700">{{ (int) $health->overstocked }}</p>
            <p class="text-sm font-medium text-blue-600">Well Stocked (100+)</p>
        </div>
    </div>

    @include('admin.dashboard._contextual-notes', ['context' => 'inventory-stock-health', 'label' => 'Inventory Health'])
</div>

{{-- Revenue at Risk --}}
@php $risk = (object) $revenueAtRisk; @endphp
<div class="admin-card rounded-xl border border-red-200 bg-red-50/30 p-5 shadow-sm" data-delay="2">
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-red-700">Revenue at Risk — Sold Out Items</h3>
    <p class="mb-3 text-[12px] text-stone-500">Estimated monthly revenue you're losing because these variants are sold out. Velocity is calculated using only the days the item was actually in stock — for recently-published items, the window starts from the publish date instead of 90 days ago. <span class="font-medium">Formula: (Units Sold ÷ Days In Stock) × 30 × Avg Price</span>.</p>

    <div class="mb-4 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-red-200 bg-white p-4 text-center">
            <p class="text-2xl font-bold text-red-700">&euro;{{ number_format($risk->total_monthly_revenue, 2) }}</p>
            <p class="text-sm text-stone-500">Potential Monthly Loss</p>
        </div>
        <div class="rounded-xl border border-red-200 bg-white p-4 text-center">
            <p class="text-2xl font-bold text-stone-700">{{ number_format($risk->variant_count) }}</p>
            <p class="text-sm text-stone-500">Sold-Out Variants</p>
        </div>
        <div class="rounded-xl border border-red-200 bg-white p-4 text-center">
            <p class="text-2xl font-bold text-stone-700">{{ number_format($risk->product_count) }}</p>
            <p class="text-sm text-stone-500">Affected Products</p>
        </div>
    </div>

    @if (! empty($risk->top_items))
        <div class="overflow-x-auto" x-data="{
            sortCol: 'monthly_revenue',
            sortAsc: false,
            showAll: false,
            limit: 10,
            items: {{ Js::from($risk->top_items) }},
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
                if (this.sortCol === col) { this.sortAsc = !this.sortAsc; } else { this.sortCol = col; this.sortAsc = (col === 'product' || col === 'variant' || col === 'sku'); }
            },
            sortIcon(col) {
                if (this.sortCol !== col) return '↕';
                return this.sortAsc ? '↑' : '↓';
            }
        }">
            <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                <thead class="bg-white/60">
                    <tr>
                        <th @click="toggleSort('product')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Product <span class="text-xs" x-text="sortIcon('product')"></span></th>
                        <th @click="toggleSort('variant')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Variant <span class="text-xs" x-text="sortIcon('variant')"></span></th>
                        <th @click="toggleSort('avg_daily_units')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Avg Daily Units <span class="text-xs" x-text="sortIcon('avg_daily_units')"></span></th>
                        <th @click="toggleSort('days_in_stock')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Days In Stock <span class="text-xs" x-text="sortIcon('days_in_stock')"></span></th>
                        <th @click="toggleSort('avg_price')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Avg Price <span class="text-xs" x-text="sortIcon('avg_price')"></span></th>
                        <th @click="toggleSort('monthly_revenue')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Monthly Loss <span class="text-xs" x-text="sortIcon('monthly_revenue')"></span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <template x-for="row in visible" :key="row.sku">
                        <tr class="transition hover:bg-white/80">
                            <td class="px-4 py-2.5 text-stone-700" x-text="row.product"></td>
                            <td class="px-4 py-2.5 text-stone-500" x-text="row.variant"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-500" x-text="row.avg_daily_units + '/day'"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-500" x-text="row.days_in_stock + 'd'"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-500" x-text="'€' + row.avg_price.toFixed(2)"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold text-red-700" x-text="'€' + row.monthly_revenue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <template x-if="items.length > limit">
                <button @click="showAll = !showAll" class="mt-2 text-sm font-medium text-[#36a2eb] hover:underline" x-text="showAll ? 'Show less' : 'Show all ' + items.length + ' items'"></button>
            </template>
        </div>
    @else
        <p class="text-[15px] text-stone-500">No historical sales data for currently sold-out variants — they may be new listings or discontinued items.</p>
    @endif

    @include('admin.dashboard._contextual-notes', ['context' => 'inventory-revenue-at-risk', 'label' => 'Revenue at Risk'])
</div>

{{-- Days of Stock Remaining --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="2">
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Days of Stock Remaining</h3>
    <p class="mb-3 text-[12px] text-stone-400">Estimated days until a variant sells out, using smart velocity fallback: <span class="font-medium">30d → 90d → 365d → all-time sales</span>. Falls back to wider windows when recent sales are absent.</p>
    @if (empty($daysOfStockRemaining))
        <p class="text-[15px] text-stone-500">No low-stock variants (1–20 units) right now. All items are either well-stocked or out of stock.</p>
    @else
        <div class="overflow-x-auto" x-data="{
            sortCol: 'days_remaining',
            sortAsc: true,
            showAll: false,
            limit: 10,
            items: {{ Js::from($daysOfStockRemaining) }},
            get sorted() {
                const col = this.sortCol;
                const dir = this.sortAsc ? 1 : -1;
                return [...this.items].sort((a, b) => {
                    if (a[col] === null && b[col] === null) return 0;
                    if (a[col] === null) return 1;
                    if (b[col] === null) return -1;
                    if (typeof a[col] === 'string') return dir * a[col].localeCompare(b[col]);
                    return dir * (a[col] - b[col]);
                });
            },
            get visible() {
                return this.showAll ? this.sorted : this.sorted.slice(0, this.limit);
            },
            toggleSort(col) {
                if (this.sortCol === col) { this.sortAsc = !this.sortAsc; } else { this.sortCol = col; this.sortAsc = true; }
            },
            sortIcon(col) {
                if (this.sortCol !== col) return '↕';
                return this.sortAsc ? '↑' : '↓';
            }
        }">
            <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                <thead class="bg-stone-50">
                    <tr>
                        <th @click="toggleSort('product')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Product <span class="text-xs" x-text="sortIcon('product')"></span></th>
                        <th @click="toggleSort('variant')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Variant <span class="text-xs" x-text="sortIcon('variant')"></span></th>
                        <th @click="toggleSort('stock')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Stock <span class="text-xs" x-text="sortIcon('stock')"></span></th>
                        <th @click="toggleSort('daily_velocity')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Daily Sales <span class="text-xs" x-text="sortIcon('daily_velocity')"></span></th>
                        <th @click="toggleSort('days_remaining')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Days Left <span class="text-xs" x-text="sortIcon('days_remaining')"></span></th>
                        <th class="px-4 py-2.5 text-right font-medium text-stone-400 text-[11px]">Window</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <template x-for="row in visible" :key="row.sku">
                        <tr class="transition hover:bg-stone-50">
                            <td class="px-4 py-2.5 text-stone-700" x-text="row.product"></td>
                            <td class="px-4 py-2.5 text-stone-500" x-text="row.variant"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right font-medium text-stone-700" x-text="row.stock"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-500" x-text="row.daily_velocity > 0 ? row.daily_velocity + '/day' : '—'"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold"
                                :class="row.days_remaining === null ? 'text-stone-400' : (row.days_remaining <= 3 ? 'text-[#ff6384]' : (row.days_remaining <= 7 ? 'text-[#ff9f40]' : 'text-stone-700'))"
                                x-text="row.days_remaining !== null ? '~' + row.days_remaining + ' days' : 'No sales'"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-[11px] text-stone-400" x-text="row.velocity_window ? '(' + row.velocity_window + ')' : ''"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <template x-if="items.length > limit">
                <button @click="showAll = !showAll" class="mt-2 text-sm font-medium text-[#36a2eb] hover:underline" x-text="showAll ? 'Show less' : 'Show all ' + items.length + ' items'"></button>
            </template>
        </div>
        <p class="mt-2 text-[11px] text-stone-400">
            <span class="font-semibold text-[#ff6384]">≤3 days</span> = critical,
            <span class="font-semibold text-[#ff9f40]">4–7 days</span> = order soon,
            <span class="font-semibold text-stone-600">8+ days</span> = manageable.
        </p>
    @endif
</div>

<div class="grid gap-6 lg:grid-cols-2">
    {{-- Stock Alert Demand --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="2">
        <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Stock Alert Demand</h3>
        <p class="mb-2 text-[12px] text-stone-400">Customers who signed up to be notified when an out-of-stock item comes back. More "Waiting" = higher restock priority.</p>
        @if (empty($stockAlertDemand))
            <p class="text-[15px] text-stone-500">No active stock alert subscriptions.</p>
        @else
            <div class="overflow-x-auto" x-data="{
                sortCol: 'waiting',
                sortAsc: false,
                showAll: false,
                limit: 10,
                items: {{ Js::from($stockAlertDemand) }},
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
                    if (this.sortCol === col) { this.sortAsc = !this.sortAsc; } else { this.sortCol = col; this.sortAsc = (col === 'product' || col === 'variant' || col === 'sku'); }
                },
                sortIcon(col) {
                    if (this.sortCol !== col) return '↕';
                    return this.sortAsc ? '↑' : '↓';
                }
            }">
                <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                    <thead class="bg-stone-50">
                        <tr>
                            <th @click="toggleSort('product')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Product <span class="text-xs" x-text="sortIcon('product')"></span></th>
                            <th @click="toggleSort('variant')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Variant <span class="text-xs" x-text="sortIcon('variant')"></span></th>
                            <th @click="toggleSort('sku')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">SKU <span class="text-xs" x-text="sortIcon('sku')"></span></th>
                            <th @click="toggleSort('waiting')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Waiting <span class="text-xs" x-text="sortIcon('waiting')"></span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <template x-for="row in visible" :key="row.sku">
                            <tr class="transition hover:bg-stone-50">
                                <td class="px-4 py-2.5 text-stone-700" x-text="row.product"></td>
                                <td class="px-4 py-2.5 text-stone-500" x-text="row.variant"></td>
                                <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-stone-400" x-text="row.sku"></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right font-medium text-stone-700" x-text="row.waiting"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <template x-if="items.length > limit">
                    <button @click="showAll = !showAll" class="mt-2 text-sm font-medium text-[#36a2eb] hover:underline" x-text="showAll ? 'Show less' : 'Show all ' + items.length + ' items'"></button>
                </template>
            </div>
        @endif
    </div>

    {{-- Product Status Breakdown --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="3">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Product Status</h3>
        <div class="mx-auto h-[250px] max-w-xs">
            <canvas id="productStatusChart"></canvas>
        </div>
    </div>
</div>

{{-- Dead Stock: Clearance & Bundle Candidates --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="4">
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Dead Stock</h3>
    <p class="mb-3 text-[13px] text-stone-500">Active variants with 10+ units in stock and no sales in the last 180 days. These are tying up cash — consider clearance pricing or including them in bundles.</p>
    @php $dead = (object) $deadStock; @endphp
    @if (empty($dead->items))
        <p class="text-[15px] text-stone-500">No dead stock right now — all stocked items have sold within the last 6 months.</p>
    @else
        <div class="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-center">
                <div class="text-[13px] font-medium uppercase tracking-wide text-amber-600">Stuck Units</div>
                <div class="mt-1 text-2xl font-bold text-amber-700">{{ number_format($dead->total_units) }}</div>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-center">
                <div class="text-[13px] font-medium uppercase tracking-wide text-amber-600">Stock Value</div>
                <div class="mt-1 text-2xl font-bold text-amber-700">&euro;{{ number_format($dead->total_value, 2) }}</div>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-center sm:col-span-1 col-span-2">
                <div class="text-[13px] font-medium uppercase tracking-wide text-amber-600">Items</div>
                <div class="mt-1 text-2xl font-bold text-amber-700">{{ count($dead->items) }}</div>
            </div>
        </div>
        <div class="overflow-x-auto" x-data="{
            sortCol: 'stock_value',
            sortAsc: false,
            showAll: false,
            limit: 15,
            items: {{ Js::from($dead->items) }},
            get sorted() {
                const col = this.sortCol;
                const dir = this.sortAsc ? 1 : -1;
                return [...this.items].sort((a, b) => {
                    if (a[col] === null && b[col] === null) return 0;
                    if (a[col] === null) return 1;
                    if (b[col] === null) return -1;
                    if (typeof a[col] === 'string') return dir * a[col].localeCompare(b[col]);
                    return dir * (a[col] - b[col]);
                });
            },
            get visible() {
                return this.showAll ? this.sorted : this.sorted.slice(0, this.limit);
            },
            toggleSort(col) {
                if (this.sortCol === col) { this.sortAsc = !this.sortAsc; } else { this.sortCol = col; this.sortAsc = (col === 'product' || col === 'variant' || col === 'sku'); }
            },
            sortIcon(col) {
                if (this.sortCol !== col) return '↕';
                return this.sortAsc ? '↑' : '↓';
            }
        }">
            <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                <thead class="bg-stone-50">
                    <tr>
                        <th @click="toggleSort('product')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Product <span class="text-xs" x-text="sortIcon('product')"></span></th>
                        <th @click="toggleSort('variant')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Variant <span class="text-xs" x-text="sortIcon('variant')"></span></th>
                        <th @click="toggleSort('stock')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Stock <span class="text-xs" x-text="sortIcon('stock')"></span></th>
                        <th @click="toggleSort('unit_price')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Unit Price <span class="text-xs" x-text="sortIcon('unit_price')"></span></th>
                        <th @click="toggleSort('stock_value')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Stock Value <span class="text-xs" x-text="sortIcon('stock_value')"></span></th>
                        <th @click="toggleSort('days_since_last_sale')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Last Sale <span class="text-xs" x-text="sortIcon('days_since_last_sale')"></span></th>
                        <th @click="toggleSort('total_ever_sold')" class="cursor-pointer select-none px-4 py-2.5 text-right font-medium text-stone-600 hover:text-[#36a2eb]">Total Sold <span class="text-xs" x-text="sortIcon('total_ever_sold')"></span></th>
                        <th @click="toggleSort('suggestion')" class="cursor-pointer select-none px-4 py-2.5 text-left font-medium text-stone-600 hover:text-[#36a2eb]">Suggestion <span class="text-xs" x-text="sortIcon('suggestion')"></span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <template x-for="row in visible" :key="row.sku">
                        <tr class="transition hover:bg-stone-50">
                            <td class="px-4 py-2.5 font-medium text-stone-700" x-text="row.product"></td>
                            <td class="px-4 py-2.5 text-stone-500" x-text="row.variant"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right font-medium text-stone-700" x-text="row.stock"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-500" x-text="'€' + Number(row.unit_price).toFixed(2)"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold text-amber-700" x-text="'€' + Number(row.stock_value).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="row.days_since_last_sale === null ? 'bg-red-100 text-red-700' : (row.days_since_last_sale >= 365 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700')"
                                    x-text="row.days_since_last_sale !== null ? row.days_since_last_sale + 'd ago' : 'Never'"></span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-500" x-text="row.total_ever_sold"></td>
                            <td class="whitespace-nowrap px-4 py-2.5">
                                <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold"
                                    :class="row.suggestion === 'Bundle Inclusion' ? 'bg-[#36a2eb]/10 text-[#36a2eb]' : 'bg-[#ff6384]/10 text-[#ff6384]'"
                                    x-text="row.suggestion"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <template x-if="items.length > limit">
                <button @click="showAll = !showAll" class="mt-2 text-sm font-medium text-[#36a2eb] hover:underline" x-text="showAll ? 'Show less' : 'Show all ' + items.length + ' items'"></button>
            </template>
        </div>
        <p class="mt-3 text-[12px] text-stone-400">
            <span class="font-semibold text-[#ff6384]">Clearance Sale</span> = few co-purchases historically, discount to move units.
            <span class="font-semibold text-[#36a2eb]">Bundle Inclusion</span> = has been bought with other products before, add to a bundle to boost value.
        </p>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const statusData = @json($productStatus);
        const colorMap = { active: '#4bc0c0', draft: '#ff9f40', archived: '#c9cbcf' };
        const labels = Object.keys(statusData);

        new Chart(document.getElementById('productStatusChart'), {
            type: 'doughnut',
            data: {
                labels: labels.map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                datasets: [{
                    data: labels.map(s => statusData[s]),
                    backgroundColor: labels.map(s => colorMap[s] || '#a8a29e'),
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 }, color: '#57534e' } }
                }
            }
        });
    });
</script>
@endpush
