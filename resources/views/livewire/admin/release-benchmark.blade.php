<?php

use App\Support\DashboardStatsService;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $searchA = '';
    public string $searchB = '';
    public ?int $selectedA = null;
    public ?int $selectedB = null;
    public ?string $selectedNameA = null;
    public ?string $selectedNameB = null;
    public ?string $startA = null;
    public ?string $startB = null;
    public int $days = 30;
    public bool $showDropdownA = false;
    public bool $showDropdownB = false;

    #[Computed]
    public function products(): array
    {
        return app(DashboardStatsService::class)->benchmarkableProducts(500);
    }

    #[Computed]
    public function filteredA(): array
    {
        if (mb_strlen($this->searchA) < 2) {
            return array_slice($this->products, 0, 30);
        }

        $terms = array_filter(explode(' ', mb_strtolower($this->searchA)));

        return array_values(array_filter($this->products, function (array $p) use ($terms) {
            $name = mb_strtolower($p['name']);
            foreach ($terms as $term) {
                if (! str_contains($name, $term)) {
                    return false;
                }
            }

            return true;
        }));
    }

    #[Computed]
    public function filteredB(): array
    {
        if (mb_strlen($this->searchB) < 2) {
            return array_slice($this->products, 0, 30);
        }

        $terms = array_filter(explode(' ', mb_strtolower($this->searchB)));

        return array_values(array_filter($this->products, function (array $p) use ($terms) {
            $name = mb_strtolower($p['name']);
            foreach ($terms as $term) {
                if (! str_contains($name, $term)) {
                    return false;
                }
            }

            return true;
        }));
    }

    #[Computed]
    public function benchmark(): ?array
    {
        if (! $this->selectedA || ! $this->selectedB) {
            return null;
        }

        return app(DashboardStatsService::class)->releaseBenchmark(
            $this->selectedA,
            $this->selectedB,
            $this->days,
            $this->startA,
            $this->startB,
        );
    }

    public function selectProduct(string $side, int $id, string $name): void
    {
        if ($side === 'a') {
            $this->selectedA = $id;
            $this->selectedNameA = $name;
            $this->searchA = '';
            $this->showDropdownA = false;
        } else {
            $this->selectedB = $id;
            $this->selectedNameB = $name;
            $this->searchB = '';
            $this->showDropdownB = false;
        }

        unset($this->benchmark);
    }

    public function clearProduct(string $side): void
    {
        if ($side === 'a') {
            $this->selectedA = null;
            $this->selectedNameA = null;
            $this->searchA = '';
            $this->startA = null;
        } else {
            $this->selectedB = null;
            $this->selectedNameB = null;
            $this->searchB = '';
            $this->startB = null;
        }

        unset($this->benchmark);
    }

    public function updatedDays(): void
    {
        $this->days = max(7, min(90, $this->days));
        unset($this->benchmark);
    }

    public function updatedStartA(): void
    {
        unset($this->benchmark);
    }

    public function updatedStartB(): void
    {
        unset($this->benchmark);
    }
}; ?>

<div>
    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-stone-600">Release-to-Release Benchmarking</h3>
    <p class="mb-3 text-[12px] text-stone-400">Compare how two products performed in their first days after launch. Start typing to search, pick two products, and the chart updates automatically.</p>

    {{-- Product Selectors --}}
    <div class="mb-4 flex flex-wrap items-end gap-3">
        {{-- Product A --}}
        <div class="flex-1 min-w-[180px]" x-data x-on:click.outside="$wire.showDropdownA = false">
            <label class="mb-1 block text-xs font-medium text-stone-500">Product A</label>
            @if ($selectedA)
                <div class="flex items-center gap-2 rounded-md border border-[#36a2eb] bg-[#36a2eb]/5 px-3 py-1.5">
                    <span class="flex-1 truncate text-sm font-medium text-stone-700">{{ $selectedNameA }}</span>
                    <button wire:click="clearProduct('a')" class="text-stone-400 hover:text-[#ff6384]" title="Clear">✕</button>
                </div>
            @else
                <div class="relative">
                    <input
                        type="text"
                        wire:model.live.debounce.200ms="searchA"
                        wire:focus="$set('showDropdownA', true)"
                        placeholder="Search products…"
                        class="w-full rounded-md border border-stone-300 px-3 py-1.5 text-sm text-stone-700 focus:border-[#36a2eb] focus:ring-1 focus:ring-[#36a2eb]"
                    >
                    @if ($showDropdownA)
                        <div class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-stone-200 bg-white shadow-lg">
                            @forelse ($this->filteredA as $product)
                                <button
                                    wire:click="selectProduct('a', {{ $product['id'] }}, '{{ addslashes($product['name']) }}')"
                                    class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-stone-50 {{ $product['id'] === $selectedB ? 'opacity-40' : '' }}"
                                >
                                    <span class="truncate text-stone-700">{{ $product['name'] }}</span>
                                    <span class="ml-2 shrink-0 text-xs text-stone-400">{{ $product['published_at'] }}</span>
                                </button>
                            @empty
                                <div class="px-3 py-2 text-sm text-stone-400">No products found</div>
                            @endforelse
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Start Date A --}}
        <div class="w-36">
            <label class="mb-1 block text-xs font-medium text-stone-500">Start Date A <span class="text-stone-400">(opt.)</span></label>
            <input
                type="date"
                wire:model.live="startA"
                class="w-full rounded-md border border-stone-300 px-3 py-1.5 text-sm text-stone-700 focus:border-[#36a2eb] focus:ring-1 focus:ring-[#36a2eb]"
                {{ ! $selectedA ? 'disabled' : '' }}
            >
        </div>

        {{-- Product B --}}
        <div class="flex-1 min-w-[180px]" x-data x-on:click.outside="$wire.showDropdownB = false">
            <label class="mb-1 block text-xs font-medium text-stone-500">Product B</label>
            @if ($selectedB)
                <div class="flex items-center gap-2 rounded-md border border-[#ff6384] bg-[#ff6384]/5 px-3 py-1.5">
                    <span class="flex-1 truncate text-sm font-medium text-stone-700">{{ $selectedNameB }}</span>
                    <button wire:click="clearProduct('b')" class="text-stone-400 hover:text-[#ff6384]" title="Clear">✕</button>
                </div>
            @else
                <div class="relative">
                    <input
                        type="text"
                        wire:model.live.debounce.200ms="searchB"
                        wire:focus="$set('showDropdownB', true)"
                        placeholder="Search products…"
                        class="w-full rounded-md border border-stone-300 px-3 py-1.5 text-sm text-stone-700 focus:border-[#36a2eb] focus:ring-1 focus:ring-[#36a2eb]"
                    >
                    @if ($showDropdownB)
                        <div class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-stone-200 bg-white shadow-lg">
                            @forelse ($this->filteredB as $product)
                                <button
                                    wire:click="selectProduct('b', {{ $product['id'] }}, '{{ addslashes($product['name']) }}')"
                                    class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-stone-50 {{ $product['id'] === $selectedA ? 'opacity-40' : '' }}"
                                >
                                    <span class="truncate text-stone-700">{{ $product['name'] }}</span>
                                    <span class="ml-2 shrink-0 text-xs text-stone-400">{{ $product['published_at'] }}</span>
                                </button>
                            @empty
                                <div class="px-3 py-2 text-sm text-stone-400">No products found</div>
                            @endforelse
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Start Date B --}}
        <div class="w-36">
            <label class="mb-1 block text-xs font-medium text-stone-500">Start Date B <span class="text-stone-400">(opt.)</span></label>
            <input
                type="date"
                wire:model.live="startB"
                class="w-full rounded-md border border-stone-300 px-3 py-1.5 text-sm text-stone-700 focus:border-[#36a2eb] focus:ring-1 focus:ring-[#36a2eb]"
                {{ ! $selectedB ? 'disabled' : '' }}
            >
        </div>

        {{-- Days --}}
        <div class="w-24">
            <label class="mb-1 block text-xs font-medium text-stone-500">Days</label>
            <select wire:model.live="days" class="w-full rounded-md border border-stone-300 px-3 py-1.5 text-sm text-stone-700 focus:border-[#36a2eb] focus:ring-1 focus:ring-[#36a2eb]">
                @foreach ([7, 14, 30, 60, 90] as $d)
                    <option value="{{ $d }}">{{ $d }}d</option>
                @endforeach
            </select>
        </div>
    </div>
    <p class="mb-3 text-[11px] text-stone-400">Leave "Start Date" empty to use the product's publish date. Set a custom date to compare specific drops.</p>

    {{-- Chart --}}
    @if ($this->benchmark && count($this->benchmark['products']) === 2)
        @php
            $bm = $this->benchmark;
            $pA = $bm['products'][0];
            $pB = $bm['products'][1];
            $compA = $bm['comparison'][$pA['id']] ?? [];
            $compB = $bm['comparison'][$pB['id']] ?? [];
            $totalUnitsA = end($compA)['cumulative_units'] ?? 0;
            $totalUnitsB = end($compB)['cumulative_units'] ?? 0;
            $totalRevenueA = end($compA)['cumulative_revenue'] ?? 0;
            $totalRevenueB = end($compB)['cumulative_revenue'] ?? 0;
        @endphp

        {{-- Summary stats --}}
        <div class="mb-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg border border-[#36a2eb]/20 bg-[#36a2eb]/5 p-3">
                <div class="text-xs font-medium text-[#36a2eb]">{{ $pA['name'] }}</div>
                <div class="mt-1 flex items-baseline gap-3">
                    <span class="text-lg font-bold text-stone-800">{{ number_format($totalUnitsA) }} units</span>
                    <span class="text-sm text-stone-500">&euro;{{ number_format($totalRevenueA, 2) }}</span>
                </div>
                <div class="text-[11px] text-stone-400">First {{ $days }} days from {{ $pA['custom_start'] ?? $pA['published_at'] }}</div>
            </div>
            <div class="rounded-lg border border-[#ff6384]/20 bg-[#ff6384]/5 p-3">
                <div class="text-xs font-medium text-[#ff6384]">{{ $pB['name'] }}</div>
                <div class="mt-1 flex items-baseline gap-3">
                    <span class="text-lg font-bold text-stone-800">{{ number_format($totalUnitsB) }} units</span>
                    <span class="text-sm text-stone-500">&euro;{{ number_format($totalRevenueB, 2) }}</span>
                </div>
                <div class="text-[11px] text-stone-400">First {{ $days }} days from {{ $pB['custom_start'] ?? $pB['published_at'] }}</div>
            </div>
        </div>

        {{-- Chart canvas --}}
        <div
            class="h-[280px]"
            wire:key="benchmark-{{ $selectedA }}-{{ $selectedB }}-{{ $days }}-{{ $startA }}-{{ $startB }}"
            x-data="{ chart: null }"
            x-init="
                $nextTick(() => {
                    const canvas = $refs.canvas;
                    if (!canvas || typeof Chart === 'undefined') return;
                    const bm = {{ Js::from($bm) }};
                    const products = bm.products;
                    const dataA = Object.values(bm.comparison)[0] || [];
                    const dataB = Object.values(bm.comparison)[1] || [];
                    chart = new Chart(canvas, {
                        type: 'line',
                        data: {
                            labels: dataA.map(d => 'Day ' + d.day),
                            datasets: [
                                {
                                    label: products[0].name,
                                    data: dataA.map(d => d.cumulative_revenue),
                                    borderColor: '#36a2eb',
                                    backgroundColor: 'rgba(54, 162, 235, 0.06)',
                                    fill: true, tension: 0.35, pointRadius: 0, borderWidth: 2.5,
                                },
                                {
                                    label: products[1].name,
                                    data: dataB.map(d => d.cumulative_revenue),
                                    borderColor: '#ff6384',
                                    backgroundColor: 'rgba(255, 99, 132, 0.06)',
                                    fill: true, tension: 0.35, pointRadius: 0, borderWidth: 2.5,
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 }, color: '#57534e' } },
                                tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': \u20ac' + ctx.parsed.y.toLocaleString() } }
                            },
                            scales: {
                                x: { grid: { display: false }, ticks: { maxTicksLimit: 10, font: { size: 11 }, color: '#78716c' } },
                                y: { beginAtZero: true, ticks: { callback: v => '\u20ac' + v.toLocaleString(), font: { size: 12 }, color: '#78716c' }, grid: { color: '#f5f5f4' } }
                            }
                        }
                    });
                });
            "
            x-on:destroy.window="chart && chart.destroy()"
        >
            <canvas x-ref="canvas"></canvas>
        </div>
    @elseif ($selectedA && $selectedB)
        <div class="rounded-lg border border-stone-100 bg-stone-50 p-4 text-center">
            <p class="text-[15px] text-stone-500">No sales data found for this comparison.</p>
            <p class="mt-1 text-[12px] text-stone-400">Both products need a publish date and at least some orders within the selected time window.</p>
        </div>
    @else
        <p class="text-[15px] text-stone-500">Select two products above to compare their launch performance.</p>
    @endif
</div>
