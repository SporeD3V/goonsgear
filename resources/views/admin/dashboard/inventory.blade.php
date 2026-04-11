@php
    $health = (object) $stockHealth;
@endphp

{{-- Stock Health Bar --}}
<div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="1">
    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-stone-600">Inventory Health</h3>
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

<div class="grid gap-6 lg:grid-cols-2">
    {{-- Stock Alert Demand --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="2">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Stock Alert Demand</h3>
        @if (empty($stockAlertDemand))
            <p class="text-[15px] text-stone-500">No active stock alert subscriptions.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-medium text-stone-600">Product</th>
                            <th class="px-4 py-2.5 text-left font-medium text-stone-600">Variant</th>
                            <th class="px-4 py-2.5 text-left font-medium text-stone-600">SKU</th>
                            <th class="px-4 py-2.5 text-right font-medium text-stone-600">Waiting</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($stockAlertDemand as $row)
                            <tr class="transition hover:bg-stone-50">
                                <td class="px-4 py-2.5 text-stone-700">{{ $row['product'] }}</td>
                                <td class="px-4 py-2.5 text-stone-500">{{ $row['variant'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-stone-400">{{ $row['sku'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right font-medium text-stone-700">{{ $row['waiting'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Product Status Breakdown --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="3">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Product Status</h3>
        <div class="mx-auto max-w-xs">
            <canvas id="productStatusChart" height="200"></canvas>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const statusData = @json($productStatus);
        const colorMap = { active: '#059669', draft: '#d97706', archived: '#78716c' };
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
