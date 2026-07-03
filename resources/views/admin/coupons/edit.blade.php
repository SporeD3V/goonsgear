@extends('admin.layout')

@section('content')
    <div class="space-y-6">
    <h2 class="text-lg font-semibold">Edit Coupon</h2>

    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
    <form method="POST" action="{{ route('admin.coupons.update', $coupon) }}" class="space-y-4" novalidate>
        @csrf
        @method('PATCH')

        @include('admin.coupons.form-fields')

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded bg-[#36a2eb] px-4 py-2 text-white hover:bg-[#2b8ac9]">Save</button>
            <a href="{{ route('admin.coupons.index') }}" class="text-sm text-stone-600 hover:underline">Cancel</a>
        </div>
    </form>
    </div>

    @include('admin.partials.edit-history', ['histories' => $editHistories])
    </div>
@endsection