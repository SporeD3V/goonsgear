@extends('admin.layout')

@section('content')
    <h2 class="mb-2 text-lg font-semibold">Edit Variant for {{ $product->name }}</h2>
    <p class="mb-4 text-sm text-slate-600">SKU: {{ $variant->sku }}</p>

    <form method="POST" action="{{ route('admin.products.variants.update', [$product, $variant]) }}" class="space-y-4" novalidate>
        @csrf
        @method('PUT')

        @include('admin.products.variants.form-fields', ['variant' => $variant])

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Update Variant</button>
            <a href="{{ route('admin.products.edit', $product) }}" class="text-sm text-slate-600 hover:underline">Back to Product</a>
        </div>
    </form>
@endsection
