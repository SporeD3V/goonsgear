@extends('admin.layout')

@section('content')
    <h2 class="mb-4 text-lg font-semibold">New Coupon</h2>

    <form method="POST" action="{{ route('admin.coupons.store') }}" class="space-y-4" novalidate>
        @csrf

        @include('admin.coupons.form-fields')

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Create</button>
            <a href="{{ route('admin.coupons.index') }}" class="text-sm text-slate-600 hover:underline">Cancel</a>
        </div>
    </form>
@endsection