{{-- KPI Row --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Customers</p>
        <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($customerStats['total']) }}</p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">New This Month</p>
        <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($customerStats['new_this_month']) }}</p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Newsletter Subscribers</p>
        <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($customerStats['total_newsletter']) }}</p>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    {{-- Customer Geography --}}
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-700">Customers by Country</h3>
        @if (empty($customerGeo))
            <p class="text-sm text-slate-500">No customer data yet.</p>
        @else
            <canvas id="customerGeoChart" height="220"></canvas>
        @endif
    </div>

    {{-- Tag Follow Popularity --}}
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-700">Tag Follow Popularity</h3>
        @if (empty($tagFollows))
            <p class="text-sm text-slate-500">No tag follow data yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-slate-600">Tag</th>
                            <th class="px-4 py-2 text-left font-medium text-slate-600">Type</th>
                            <th class="px-4 py-2 text-right font-medium text-slate-600">Followers</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($tagFollows as $tag)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2 font-medium text-slate-700">{{ $tag['name'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2">
                                    @php
                                        $typeColors = ['artist' => 'bg-blue-100 text-blue-700', 'brand' => 'bg-emerald-100 text-emerald-700', 'custom' => 'bg-slate-100 text-slate-700'];
                                    @endphp
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $typeColors[$tag['type']] ?? 'bg-slate-100 text-slate-700' }}">
                                        {{ ucfirst($tag['type']) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-medium text-slate-700">{{ $tag['followers'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const geoData = @json($customerGeo);
        if (!geoData.length) return;

        new Chart(document.getElementById('customerGeoChart'), {
            type: 'bar',
            data: {
                labels: geoData.map(c => c.country || 'Unknown'),
                datasets: [{
                    label: 'Customers',
                    data: geoData.map(c => c.count),
                    backgroundColor: '#6366f1',
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { font: { size: 11 } } },
                    y: { ticks: { font: { size: 11 } } }
                }
            }
        });
    });
</script>
@endpush
