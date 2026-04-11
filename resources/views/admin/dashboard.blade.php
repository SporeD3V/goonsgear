@extends('admin.layout')

@section('content')
    <div class="space-y-6">
        <h1 class="text-2xl font-bold">Dashboard</h1>

        {{-- Tab Navigation --}}
        <div class="border-b border-slate-200">
            <nav class="-mb-px flex gap-6 text-sm font-medium">
                @foreach (['overview' => 'Overview', 'sales' => 'Sales', 'inventory' => 'Inventory', 'promotions' => 'Promotions', 'customers' => 'Customers'] as $key => $label)
                    <a href="{{ route('admin.dashboard', ['tab' => $key]) }}"
                       class="border-b-2 px-1 pb-3 transition {{ $tab === $key ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>

        {{-- Tab Content --}}
        @include("admin.dashboard.{$tab}")
    </div>
@endsection
