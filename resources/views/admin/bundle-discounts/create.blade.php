@extends('admin.layout')

@section('content')
    <div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">New Bundle Discount</h2>
        <a href="{{ route('admin.bundle-discounts.index') }}" class="text-sm text-blue-700 hover:underline">Back to list</a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <form method="POST" action="{{ route('admin.bundle-discounts.store') }}" class="grid max-w-4xl gap-4">
        @csrf
        @include('admin.bundle-discounts.form-fields')

        <div>
            <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Create Bundle Discount</button>
        </div>
    </form>
    </div>
    </div>
@endsection
