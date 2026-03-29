@extends('admin.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold">New Regional Discount Rule</h2>
        <a href="{{ route('admin.regional-discounts.index') }}" class="text-sm text-blue-700 hover:underline">Back to list</a>
    </div>

    <form method="POST" action="{{ route('admin.regional-discounts.store') }}" class="grid gap-4 max-w-xl">
        @csrf
        @include('admin.regional-discounts.form-fields')
        <div>
            <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Create Rule</button>
        </div>
    </form>
@endsection
