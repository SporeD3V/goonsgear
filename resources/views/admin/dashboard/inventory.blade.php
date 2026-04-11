@php
    $health = (object) $stockHealth;
@endphp

{{-- Stock Health Bar --}}
<div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-700">Inventory Health</h3>
    <div class="grid gap-4 sm:grid-cols-5">
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-center">
            <p class="text-2xl font-bold text-red-700">{{ (int) $health->out_of_stock }}</p>
            <p class="text-xs font-medium text-red-600">Out of Stock</p>
        </div>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-center">
            <p class="text-2xl font-bold text-amber-700">{{ (int) $health->critical }}</p>
            <p class="text-xs font-medium text-amber-600">Critical (1–5)</p>
        </div>
        <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-center">
            <p class="text-2xl font-bold text-yellow-700">{{ (int) $health->low }}</p>
            <p class="text-xs font-medium text-yellow-600">Low (6–20)</p>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-center">
            <p class="text-2xl font-bold text-emerald-700">{{ (int) $health->healthy }}</p>
            <p class="text-xs font-medium text-emerald-600">Healthy (21–100)</p>
        </div>
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-center">
            <p class="text-2xl font-bold text-blue-700">{{ (int) $health->overstocked }}</p>
            <p class="text-xs font-medium text-blue-600">Over 100</p>
        </div>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    {{-- Stock Alert Demand --}}
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-700">Stock Alert Demand</h3>
        @if (empty($stockAlertDemand))
            <p class="text-sm text-slate-500">No active stock alert subscriptions.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-slate-600">Product</th>
                            <th class="px-4 py-2 text-left font-medium text-slate-600">Variant</th>
                            <th class="px-4 py-2 text-left font-medium text-slate-600">SKU</th>
                            <th class="px-4 py-2 text-right font-medium text-slate-600">Waiting</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($stockAlertDemand as $row)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2 text-slate-700">{{ $row['product'] }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ $row['variant'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2 font-mono text-xs text-slate-400">{{ $row['sku'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-medium text-slate-700">{{ $row['waiting'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Product Status Breakdown --}}
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-700">Product Status</h3>
        <div class="mx-auto max-w-xs">
            <canvas id="productStatusChart" height="200"></canvas>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const statusData = @json($productStatus);
        const colorMap = { active: '#10b981', draft: '#f59e0b', archived: '#64748b' };
        const labels = Object.keys(statusData);

        new Chart(document.getElementById('productStatusChart'), {
            type: 'doughnut',
            data: {
                labels: labels.map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                datasets: [{
                    data: labels.map(s => statusData[s]),
                    backgroundColor: labels.map(s => colorMap[s] || '#94a3b8'),
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 16, font: { size: 11 } } }
                }
            }
        });
    });
</script>
@endpush
