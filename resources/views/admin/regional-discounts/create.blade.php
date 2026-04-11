@extends('admin.layout')

@section('content')
    <div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">New Regional Discount Rule</h2>
        <a href="{{ route('admin.regional-discounts.index') }}" class="text-sm text-blue-700 hover:underline">Back to list</a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <form method="POST" action="{{ route('admin.regional-discounts.store') }}" class="grid gap-4 max-w-xl">
        @csrf
        @include('admin.regional-discounts.form-fields')
        <div>
            <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Create Rule</button>
        </div>
    </form>
    </div>
    </div>
@endsection
