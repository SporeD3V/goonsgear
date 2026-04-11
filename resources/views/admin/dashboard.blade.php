@extends('admin.layout')

@section('content')
    <div class="space-y-6">
        <h1 class="text-2xl font-bold text-stone-800 animate-fade-in">Dashboard</h1>

        {{-- Tab Navigation --}}
        <div class="border-b border-stone-200">
            <nav class="-mb-px flex gap-1 overflow-x-auto text-[15px] font-medium">
                @php
                    $tabParams = ['period' => $period, 'compare' => $compare ? 1 : 0, 'compare_mode' => $compareMode];
                    if ($period === 'custom' && $customFrom && $customTo) {
                        $tabParams['custom_from'] = $customFrom;
                        $tabParams['custom_to'] = $customTo;
                    }
                @endphp
                @foreach (['overview' => 'Overview', 'sales' => 'Sales & Revenue', 'audience' => 'Audience & CRM', 'inventory' => 'Inventory & Ops', 'marketing' => 'Marketing & Funnel'] as $key => $label)
                    <a href="{{ route('admin.dashboard', array_merge($tabParams, ['tab' => $key])) }}"
                       class="whitespace-nowrap rounded-t-lg border-b-2 px-4 py-3 transition {{ $tab === $key ? 'border-[#36a2eb] bg-[#36a2eb]/10 text-[#36a2eb]' : 'border-transparent text-stone-500 hover:border-stone-300 hover:bg-stone-50 hover:text-stone-700' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>

        {{-- Period Picker, Custom Range & Compare Toggle --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                {{-- Preset Pills --}}
                <div class="flex items-center gap-1 rounded-lg bg-stone-100 p-1">
                    @php
                        $pillParams = ['tab' => $tab, 'compare' => $compare ? 1 : 0, 'compare_mode' => $compareMode];
                    @endphp
                    @foreach (['7d' => '7D', '14d' => '14D', '30d' => '30D', '90d' => '90D', 'year' => '1Y', 'all' => 'All'] as $key => $label)
                        <a href="{{ route('admin.dashboard', array_merge($pillParams, ['period' => $key])) }}"
                           class="rounded-md px-3 py-1.5 text-sm font-medium transition {{ $period === $key ? 'bg-white text-[#36a2eb] shadow-sm' : 'text-stone-500 hover:text-stone-700' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                {{-- Custom Date Range --}}
                <div x-data="{ open: {{ $period === 'custom' ? 'true' : 'false' }} }" class="flex items-center gap-2">
                    <button @click="open = !open"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition {{ $period === 'custom' ? 'bg-white text-[#36a2eb] shadow-sm ring-1 ring-stone-200' : 'text-stone-500 hover:text-stone-700' }}">
                        <svg class="inline-block h-4 w-4 -mt-0.5 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                        Custom
                    </button>

                    <form x-show="open" x-cloak method="GET" action="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <input type="hidden" name="compare" value="{{ $compare ? 1 : 0 }}">
                        <input type="hidden" name="compare_mode" value="{{ $compareMode }}">
                        <input type="date" name="custom_from" value="{{ $customFrom }}"
                            class="rounded-md border border-stone-300 px-2.5 py-1.5 text-sm text-stone-700 focus:border-[#36a2eb] focus:ring-1 focus:ring-[#36a2eb]"
                            required>
                        <span class="text-sm text-stone-400">to</span>
                        <input type="date" name="custom_to" value="{{ $customTo }}"
                            class="rounded-md border border-stone-300 px-2.5 py-1.5 text-sm text-stone-700 focus:border-[#36a2eb] focus:ring-1 focus:ring-[#36a2eb]"
                            required>
                        <button type="submit" class="rounded-md bg-[#36a2eb] px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-[#36a2eb]/90">
                            Apply
                        </button>
                    </form>
                </div>
            </div>

            {{-- Compare Toggle & Mode --}}
            @if ($tab !== 'inventory')
                <div class="flex items-center gap-2" x-data>
                    @php
                        $baseCompareParams = ['tab' => $tab, 'period' => $period];
                        if ($period === 'custom' && $customFrom && $customTo) {
                            $baseCompareParams['custom_from'] = $customFrom;
                            $baseCompareParams['custom_to'] = $customTo;
                        }
                    @endphp

                    {{-- Mode pills — always visible; clicking a mode auto-enables compare --}}
                    <div class="flex items-center gap-0.5 rounded-lg bg-stone-100 p-0.5">
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
                        @endif
                        @if ($compare)
                            <a href="{{ route('admin.dashboard', array_merge($baseCompareParams, ['compare' => 0, 'compare_mode' => $compareMode])) }}"
                               class="rounded-md px-2.5 py-1.5 text-xs font-medium text-stone-400 transition hover:text-[#ff6384]"
                               title="Turn off comparison">
                                ✕
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        {{-- Tab Content --}}
        <div class="animate-fade-in space-y-6">
            @include("admin.dashboard.{$tab}")
        </div>
    </div>
@endsection
