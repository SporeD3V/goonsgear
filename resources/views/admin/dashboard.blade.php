@extends('admin.layout')

@section('content')
    <div class="space-y-6">
        <h1 class="text-2xl font-bold text-stone-800 animate-fade-in">Dashboard</h1>

        {{-- Tab Navigation --}}
        <div class="border-b border-stone-200">
            <nav class="-mb-px flex gap-1 overflow-x-auto text-[15px] font-medium">
                @foreach (['overview' => 'Overview', 'sales' => 'Sales', 'inventory' => 'Inventory', 'promotions' => 'Promotions', 'customers' => 'Customers'] as $key => $label)
                    <a href="{{ route('admin.dashboard', ['tab' => $key, 'period' => $period, 'compare' => $compare ? 1 : 0]) }}"
                       class="whitespace-nowrap rounded-t-lg border-b-2 px-4 py-3 transition {{ $tab === $key ? 'border-[#36a2eb] bg-[#36a2eb]/10 text-[#36a2eb]' : 'border-transparent text-stone-500 hover:border-stone-300 hover:bg-stone-50 hover:text-stone-700' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>

        {{-- Period Picker & Compare Toggle --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-1 rounded-lg bg-stone-100 p-1">
                @foreach (['7d' => '7D', '14d' => '14D', '30d' => '30D', '90d' => '90D', 'year' => '1Y', 'all' => 'All'] as $key => $label)
                    <a href="{{ route('admin.dashboard', ['tab' => $tab, 'period' => $key, 'compare' => $compare ? 1 : 0]) }}"
                       class="rounded-md px-3 py-1.5 text-sm font-medium transition {{ $period === $key ? 'bg-white text-[#36a2eb] shadow-sm' : 'text-stone-500 hover:text-stone-700' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            @if ($tab !== 'inventory')
                <a href="{{ route('admin.dashboard', ['tab' => $tab, 'period' => $period, 'compare' => $compare ? 0 : 1]) }}"
                   class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium transition {{ $compare ? 'border-[#9966ff]/30 bg-[#9966ff]/10 text-[#9966ff]' : 'border-stone-200 bg-white text-stone-500 hover:border-stone-300 hover:text-stone-700' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                    Compare
                </a>
            @endif
        </div>

        {{-- Tab Content --}}
        <div class="animate-fade-in space-y-6">
            @include("admin.dashboard.{$tab}")
        </div>
    </div>
@endsection
