@extends('admin.layout')

@section('content')
    <div class="space-y-6">
        <h1 class="text-2xl font-bold text-stone-800 animate-fade-in">Dashboard</h1>

        {{-- Tab Navigation --}}
        @php
            $dashboardTabs = ['overview' => 'Overview', 'sales' => 'Sales & Revenue', 'audience' => 'Audience & CRM', 'inventory' => 'Inventory & Ops', 'marketing' => 'Marketing & Funnel'];
        @endphp

        <div class="rounded-lg border border-stone-200 bg-white p-3 sm:hidden">
            <label for="dashboard-tab-select" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-stone-500">Dashboard Section</label>
            <select id="dashboard-tab-select"
                    class="w-full rounded-md border border-stone-300 px-3 py-2 text-sm text-stone-700"
                    onchange="if (this.value) window.location.href = this.value;">
                @php
                    $mobileTabParams = [
                        'period' => $period,
                        'compare' => $compare ? 1 : 0,
                        'compare_mode' => $compareMode,
                        'compare_interval_unit' => $compareIntervalUnit,
                        'compare_interval_value' => $compareIntervalValue,
                        'compare_custom_from' => $compareCustomFrom,
                        'compare_custom_to' => $compareCustomTo,
                    ];
                    if ($period === 'custom' && $customFrom && $customTo) {
                        $mobileTabParams['custom_from'] = $customFrom;
                        $mobileTabParams['custom_to'] = $customTo;
                    }
                @endphp
                @foreach ($dashboardTabs as $key => $label)
                    <option value="{{ route('admin.dashboard', array_merge($mobileTabParams, ['tab' => $key])) }}" {{ $tab === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="hidden border-b border-stone-200 sm:block">
            <nav class="-mb-px flex gap-1 overflow-x-auto text-[15px] font-medium">
                @php
                    $tabParams = [
                        'period' => $period,
                        'compare' => $compare ? 1 : 0,
                        'compare_mode' => $compareMode,
                        'compare_interval_unit' => $compareIntervalUnit,
                        'compare_interval_value' => $compareIntervalValue,
                        'compare_custom_from' => $compareCustomFrom,
                        'compare_custom_to' => $compareCustomTo,
                    ];
                    if ($period === 'custom' && $customFrom && $customTo) {
                        $tabParams['custom_from'] = $customFrom;
                        $tabParams['custom_to'] = $customTo;
                    }
                @endphp
                @foreach ($dashboardTabs as $key => $label)
                    <a href="{{ route('admin.dashboard', array_merge($tabParams, ['tab' => $key])) }}"
                       class="whitespace-nowrap rounded-t-lg border-b-2 px-4 py-3 transition {{ $tab === $key ? 'border-[#36a2eb] bg-[#36a2eb]/10 text-[#36a2eb]' : 'border-transparent text-stone-500 hover:border-stone-300 hover:bg-stone-50 hover:text-stone-700' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>

        {{-- Period Picker, Custom Range & Compare Toggle --}}
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                {{-- Preset Pills --}}
                <div class="flex items-center gap-1 rounded-lg bg-stone-100 p-1">
                    @php
                        $pillParams = [
                            'tab' => $tab,
                            'compare' => $compare ? 1 : 0,
                            'compare_mode' => $compareMode,
                            'compare_interval_unit' => $compareIntervalUnit,
                            'compare_interval_value' => $compareIntervalValue,
                            'compare_custom_from' => $compareCustomFrom,
                            'compare_custom_to' => $compareCustomTo,
                        ];
                    @endphp
                    @foreach (['1d' => '1D', '7d' => '7D', '14d' => '14D', '30d' => '30D', '90d' => '90D', 'year' => '1Y', 'all' => 'All'] as $key => $label)
                        <a href="{{ route('admin.dashboard', array_merge($pillParams, ['period' => $key])) }}"
                           class="rounded-md px-3 py-1.5 text-sm font-medium transition {{ $period === $key ? 'bg-white text-[#36a2eb] shadow-sm' : 'text-stone-500 hover:text-stone-700' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                {{-- Custom Date Range --}}
                <div x-data="{ open: {{ $period === 'custom' ? 'true' : 'false' }} }" class="flex flex-wrap items-center gap-2">
                    <button @click="open = !open"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition {{ $period === 'custom' ? 'bg-white text-[#36a2eb] shadow-sm ring-1 ring-stone-200' : 'text-stone-500 hover:text-stone-700' }}">
                        <svg class="inline-block h-4 w-4 -mt-0.5 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                        Custom
                    </button>

                    <form x-show="open" x-cloak method="GET" action="{{ route('admin.dashboard') }}" class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <input type="hidden" name="compare" value="{{ $compare ? 1 : 0 }}">
                        <input type="hidden" name="compare_mode" value="{{ $compareMode }}">
                        <input type="hidden" name="compare_interval_unit" value="{{ $compareIntervalUnit }}">
                        <input type="hidden" name="compare_interval_value" value="{{ $compareIntervalValue }}">
                        <input type="hidden" name="compare_custom_from" value="{{ $compareCustomFrom }}">
                        <input type="hidden" name="compare_custom_to" value="{{ $compareCustomTo }}">
                        <input type="date" name="custom_from" value="{{ $customFrom }}"
                            class="w-full rounded-md border border-stone-300 px-2.5 py-1.5 text-sm text-stone-700 focus:border-[#36a2eb] focus:ring-1 focus:ring-[#36a2eb] sm:w-auto"
                            required>
                        <span class="hidden text-sm text-stone-400 sm:inline">to</span>
                        <input type="date" name="custom_to" value="{{ $customTo }}"
                            class="w-full rounded-md border border-stone-300 px-2.5 py-1.5 text-sm text-stone-700 focus:border-[#36a2eb] focus:ring-1 focus:ring-[#36a2eb] sm:w-auto"
                            required>
                        <button type="submit" class="w-full rounded-md bg-[#36a2eb] px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-[#36a2eb]/90 sm:w-auto">
                            Apply
                        </button>
                    </form>
                </div>
            </div>

            {{-- Compare Toggle & Mode --}}
            @if ($tab !== 'inventory')
                <div class="w-full sm:w-auto" x-data>
                    @php
                        $baseCompareParams = [
                            'tab' => $tab,
                            'period' => $period,
                            'compare_interval_unit' => $compareIntervalUnit,
                            'compare_interval_value' => $compareIntervalValue,
                            'compare_custom_from' => $compareCustomFrom,
                            'compare_custom_to' => $compareCustomTo,
                        ];
                        if ($period === 'custom' && $customFrom && $customTo) {
                            $baseCompareParams['custom_from'] = $customFrom;
                            $baseCompareParams['custom_to'] = $customTo;
                        }
                    @endphp

                    {{-- Mode pills — always visible; clicking a mode auto-enables compare --}}
                    <div class="inline-flex flex-wrap items-center gap-0.5 rounded-lg bg-stone-100 p-0.5">
                        @if ($period !== 'all')
                            <a href="{{ route('admin.dashboard', array_merge($baseCompareParams, ['compare' => 1, 'compare_mode' => 'previous_period'])) }}"
                               class="rounded-md px-2.5 py-1.5 text-xs font-medium transition {{ $compare && $compareMode === 'previous_period' ? 'bg-white text-[#9966ff] shadow-sm' : 'text-stone-400 hover:text-stone-600' }}"
                               title="Compare with the prior period of equal length">
                                <svg class="mr-1 inline-block h-3.5 w-3.5 -mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                                vs Prev. Period
                            </a>
                            <a href="{{ route('admin.dashboard', array_merge($baseCompareParams, ['compare' => 1, 'compare_mode' => 'yoy'])) }}"
                               class="rounded-md px-2.5 py-1.5 text-xs font-medium transition {{ $compare && $compareMode === 'yoy' ? 'bg-white text-[#9966ff] shadow-sm' : 'text-stone-400 hover:text-stone-600' }}"
                               title="Compare with the same period last year">
                                <svg class="mr-1 inline-block h-3.5 w-3.5 -mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                                vs Year Ago
                            </a>
                            <a href="{{ route('admin.dashboard', array_merge($baseCompareParams, ['compare' => 1, 'compare_mode' => 'custom_interval'])) }}"
                               class="rounded-md px-2.5 py-1.5 text-xs font-medium transition {{ $compare && $compareMode === 'custom_interval' ? 'bg-white text-[#9966ff] shadow-sm' : 'text-stone-400 hover:text-stone-600' }}"
                               title="Compare by a custom shift interval">
                                <svg class="mr-1 inline-block h-3.5 w-3.5 -mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15m0 0 4.5-4.5M4.5 12l4.5 4.5M12 4.5v15"/></svg>
                                vs Custom Interval
                            </a>
                            <a href="{{ route('admin.dashboard', array_merge($baseCompareParams, ['compare' => 1, 'compare_mode' => 'custom_range'])) }}"
                               class="rounded-md px-2.5 py-1.5 text-xs font-medium transition {{ $compare && $compareMode === 'custom_range' ? 'bg-white text-[#9966ff] shadow-sm' : 'text-stone-400 hover:text-stone-600' }}"
                               title="Compare against an explicit date range">
                                <svg class="mr-1 inline-block h-3.5 w-3.5 -mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M5 21h14a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Z"/></svg>
                                vs Date Range
                            </a>
                        @endif
                        @if ($compare)
                            <a href="{{ route('admin.dashboard', array_merge($baseCompareParams, ['compare' => 0, 'compare_mode' => $compareMode])) }}"
                               class="rounded-md px-2.5 py-1.5 text-xs font-medium text-stone-400 transition hover:text-[#ff6384]"
                               title="Turn off comparison">
                                ✕
                            </a>
                        @endif
                    </div>

                    @if ($period !== 'all' && $compare && $compareMode === 'custom_interval')
                        <form method="GET" action="{{ route('admin.dashboard') }}" class="mt-2 flex flex-wrap items-center gap-2 rounded-lg border border-stone-200 bg-white p-2">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <input type="hidden" name="period" value="{{ $period }}">
                            <input type="hidden" name="compare" value="1">
                            <input type="hidden" name="compare_mode" value="custom_interval">
                            <input type="hidden" name="compare_custom_from" value="{{ $compareCustomFrom }}">
                            <input type="hidden" name="compare_custom_to" value="{{ $compareCustomTo }}">
                            @if ($period === 'custom' && $customFrom && $customTo)
                                <input type="hidden" name="custom_from" value="{{ $customFrom }}">
                                <input type="hidden" name="custom_to" value="{{ $customTo }}">
                            @endif
                            <label class="text-xs font-medium text-stone-500" for="compare-interval-value">Shift by</label>
                            <input id="compare-interval-value"
                                   type="number"
                                   name="compare_interval_value"
                                   min="1"
                                   max="365"
                                   value="{{ $compareIntervalValue }}"
                                   class="w-20 rounded-md border border-stone-300 px-2 py-1 text-sm text-stone-700 focus:border-[#36a2eb] focus:ring-1 focus:ring-[#36a2eb]">
                            <select name="compare_interval_unit"
                                    class="rounded-md border border-stone-300 px-2 py-1 text-sm text-stone-700 focus:border-[#36a2eb] focus:ring-1 focus:ring-[#36a2eb]">
                                @foreach (['day' => 'Day(s)', 'week' => 'Week(s)', 'month' => 'Month(s)', 'quarter' => 'Quarter(s)', 'year' => 'Year(s)'] as $unitKey => $unitLabel)
                                    <option value="{{ $unitKey }}" {{ $compareIntervalUnit === $unitKey ? 'selected' : '' }}>{{ $unitLabel }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="rounded-md bg-[#9966ff] px-2.5 py-1 text-xs font-medium text-white transition hover:bg-[#9966ff]/90">Apply</button>
                        </form>
                    @endif

                    @if ($period !== 'all' && $compare && $compareMode === 'custom_range')
                        <form method="GET" action="{{ route('admin.dashboard') }}" class="mt-2 flex flex-wrap items-center gap-2 rounded-lg border border-stone-200 bg-white p-2">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <input type="hidden" name="period" value="{{ $period }}">
                            <input type="hidden" name="compare" value="1">
                            <input type="hidden" name="compare_mode" value="custom_range">
                            <input type="hidden" name="compare_interval_unit" value="{{ $compareIntervalUnit }}">
                            <input type="hidden" name="compare_interval_value" value="{{ $compareIntervalValue }}">
                            @if ($period === 'custom' && $customFrom && $customTo)
                                <input type="hidden" name="custom_from" value="{{ $customFrom }}">
                                <input type="hidden" name="custom_to" value="{{ $customTo }}">
                            @endif

                            <label class="text-xs font-medium text-stone-500" for="compare-custom-from">Compare From</label>
                            <input id="compare-custom-from"
                                   type="date"
                                   name="compare_custom_from"
                                   value="{{ $compareCustomFrom }}"
                                   class="rounded-md border border-stone-300 px-2 py-1 text-sm text-stone-700 focus:border-[#36a2eb] focus:ring-1 focus:ring-[#36a2eb]"
                                   required>
                            <span class="text-xs text-stone-400">to</span>
                            <input id="compare-custom-to"
                                   type="date"
                                   name="compare_custom_to"
                                   value="{{ $compareCustomTo }}"
                                   class="rounded-md border border-stone-300 px-2 py-1 text-sm text-stone-700 focus:border-[#36a2eb] focus:ring-1 focus:ring-[#36a2eb]"
                                   required>
                            <button type="submit" class="rounded-md bg-[#9966ff] px-2.5 py-1 text-xs font-medium text-white transition hover:bg-[#9966ff]/90">Apply</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        {{-- Tab Content --}}
        <div id="admin-dashboard-content" class="animate-fade-in space-y-6">
            @include("admin.dashboard.{$tab}")
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const root = document.getElementById('admin-dashboard-content');
        if (!root) {
            return;
        }

        const tables = root.querySelectorAll('table');
        tables.forEach((table) => {
            table.classList.add('dashboard-mobile-table');

            const headerCells = Array.from(table.querySelectorAll('thead th')).map((th) => {
                return (th.textContent || '').trim();
            });

            if (headerCells.length === 0) {
                return;
            }

            table.querySelectorAll('tbody tr').forEach((row) => {
                const bodyCells = Array.from(row.querySelectorAll('td'));
                bodyCells.forEach((cell, index) => {
                    if (cell.hasAttribute('colspan')) {
                        return;
                    }

                    const fallbackLabel = 'Column ' + (index + 1);
                    const label = headerCells[index] || fallbackLabel;
                    cell.setAttribute('data-label', label);
                });
            });
        });

        const isMobile = window.matchMedia('(max-width: 767px)').matches;

        document.querySelectorAll('.dashboard-card-toggle').forEach((toggle) => toggle.remove());
        root.querySelectorAll('.dashboard-card-collapsed, .dashboard-card-expanded').forEach((card) => {
            card.classList.remove('dashboard-card-collapsed', 'dashboard-card-expanded');
            card.style.removeProperty('--dashboard-collapsed-height');
        });

        if (!isMobile) {
            return;
        }

        const collapseHeight = 520;
        const cards = Array.from(root.querySelectorAll('.admin-card'));

        cards.forEach((card) => {
            if (card.scrollHeight <= collapseHeight + 40) {
                return;
            }

            card.classList.add('dashboard-card-collapsed');
            card.style.setProperty('--dashboard-collapsed-height', collapseHeight + 'px');

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'dashboard-card-toggle mt-2 w-full rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700';
            button.textContent = 'Show More';

            button.addEventListener('click', function () {
                const isCollapsed = card.classList.contains('dashboard-card-collapsed');
                if (isCollapsed) {
                    card.classList.remove('dashboard-card-collapsed');
                    card.classList.add('dashboard-card-expanded');
                    button.textContent = 'Show Less';
                } else {
                    card.classList.remove('dashboard-card-expanded');
                    card.classList.add('dashboard-card-collapsed');
                    button.textContent = 'Show More';
                }
            });

            card.after(button);
        });
    });
</script>
@endpush
