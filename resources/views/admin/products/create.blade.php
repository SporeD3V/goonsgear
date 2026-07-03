@extends('admin.layout')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-stone-800">New Product</h2>
                <p class="text-[13px] text-stone-500">Variants and media assignment become available after saving.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.products.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-stone-500 transition hover:bg-stone-100 hover:text-stone-700">
                    &larr; Back to Products
                </a>
                <button type="submit" form="product-form" class="rounded-lg bg-[#36a2eb] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b8ac9]">
                    Create product
                </button>
            </div>
        </div>

        @include('admin.products._form', ['product' => null])
    </div>
@endsection
