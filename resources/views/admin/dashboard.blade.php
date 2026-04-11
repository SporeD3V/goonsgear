@extends('admin.layout')

@section('content')
    <div class="space-y-6">
        <h1 class="text-2xl font-bold text-stone-800 animate-fade-in">Dashboard</h1>

        {{-- Tab Navigation --}}
        <div class="border-b border-stone-200">
            <nav class="-mb-px flex gap-1 overflow-x-auto text-[15px] font-medium">
                @foreach (['overview' => 'Overview', 'sales' => 'Sales', 'inventory' => 'Inventory', 'promotions' => 'Promotions', 'customers' => 'Customers'] as $key => $label)
                    <a href="{{ route('admin.dashboard', ['tab' => $key]) }}"
                       class="whitespace-nowrap rounded-t-lg border-b-2 px-4 py-3 transition {{ $tab === $key ? 'border-amber-600 bg-amber-50/50 text-amber-700' : 'border-transparent text-stone-500 hover:border-stone-300 hover:bg-stone-50 hover:text-stone-700' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>

        {{-- Tab Content --}}
        <div class="animate-fade-in">
            @include("admin.dashboard.{$tab}")
        </div>
    </div>
@endsection
