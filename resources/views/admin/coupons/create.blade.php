@extends('admin.layout')

@section('content')
    <div class="space-y-6">
    <h2 class="text-lg font-semibold">New Coupon</h2>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <form method="POST" action="{{ route('admin.coupons.store') }}" class="space-y-4" novalidate>
        @csrf

        @include('admin.coupons.form-fields')

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Create</button>
            <a href="{{ route('admin.coupons.index') }}" class="text-sm text-slate-600 hover:underline">Cancel</a>
        </div>
    </form>
    </div>
    </div>
@endsection