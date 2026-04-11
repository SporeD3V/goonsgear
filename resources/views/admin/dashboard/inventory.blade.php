@php
    $health = (object) $stockHealth;
@endphp

{{-- Stock Health Bar --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="1">
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Inventory Health</h3>
    <p class="mb-3 text-[12px] text-stone-400">Active product variants grouped by current stock level. "Critical" means 1–5 units left and could sell out very soon.</p>
    <div class="grid gap-4 sm:grid-cols-5">
        <div class="admin-card-hover rounded-xl border border-red-200 bg-red-50 p-4 text-center" data-delay="1">
            <p class="text-3xl font-bold text-red-700">{{ (int) $health->out_of_stock }}</p>
            <p class="text-sm font-medium text-red-600">Out of Stock</p>
        </div>
        <div class="admin-card-hover rounded-xl border border-amber-200 bg-amber-50 p-4 text-center" data-delay="2">
            <p class="text-3xl font-bold text-amber-700">{{ (int) $health->critical }}</p>
            <p class="text-sm font-medium text-amber-600">Critical (1–5)</p>
        </div>
        <div class="admin-card-hover rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-center" data-delay="3">
            <p class="text-3xl font-bold text-yellow-700">{{ (int) $health->low }}</p>
            <p class="text-sm font-medium text-yellow-600">Low (6–20)</p>
        </div>
        <div class="admin-card-hover rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center" data-delay="4">
            <p class="text-3xl font-bold text-emerald-700">{{ (int) $health->healthy }}</p>
            <p class="text-sm font-medium text-emerald-600">Healthy (21–100)</p>
        </div>
        <div class="admin-card-hover rounded-xl border border-blue-200 bg-blue-50 p-4 text-center" data-delay="5">
            <p class="text-3xl font-bold text-blue-700">{{ (int) $health->overstocked }}</p>
            <p class="text-sm font-medium text-blue-600">Over 100</p>
        </div>
    </div>
</div>

{{-- Days of Stock Remaining --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="2">
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Days of Stock Remaining</h3>
    <p class="mb-3 text-[12px] text-stone-400">Estimated days until a variant sells out, based on the last 30 days of sales. <span class="font-medium">Formula: Current Stock ÷ (Units Sold in 30 Days ÷ 30)</span>. "No sales" means the item hasn't sold recently so no prediction is possible.</p>
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
