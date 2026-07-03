@extends('admin.layout')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-stone-800">New Variant</h2>
                <p class="text-[13px] text-stone-500">
                    For <span class="font-medium text-stone-700">{{ $product->name }}</span>
                    — size, color, edition, or any other purchasable option.
                </p>
            </div>
            <a href="{{ route('admin.products.edit', $product) }}" class="rounded-lg px-3 py-2 text-sm font-medium text-stone-500 transition hover:bg-stone-100 hover:text-stone-700">
                &larr; Back to Product
            </a>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-semibold">Please fix the following before saving:</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="1">
            <form method="POST" action="{{ route('admin.products.variants.store', $product) }}" class="space-y-4" novalidate>
                @csrf

                @include('admin.products.variants.form-fields', ['variant' => null])

                <div class="flex items-center gap-3 border-t border-stone-100 pt-4">
                    <button type="submit" class="rounded-lg bg-[#36a2eb] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b8ac9]">Create Variant</button>
                    <a href="{{ route('admin.products.edit', $product) }}" class="rounded-lg px-3 py-2 text-sm font-medium text-stone-500 transition hover:bg-stone-100 hover:text-stone-700">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
