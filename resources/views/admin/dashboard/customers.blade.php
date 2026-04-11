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

@push('scripts')
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
    });
</script>
@endpush
