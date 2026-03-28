@extends('admin.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Products</h2>
        <a href="{{ route('admin.products.create') }}" class="rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">New Product</a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full border border-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="border border-slate-200 px-3 py-2 text-left">Name</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Slug</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Status</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Primary Category</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Variants</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Media</th>
                    <th class="border border-slate-200 px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td class="border border-slate-200 px-3 py-2">{{ $product->name }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ $product->slug }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ ucfirst($product->status) }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ $product->primaryCategory?->name ?? '-' }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ $product->variants_count }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ $product->media_count }}</td>
                        <td class="border border-slate-200 px-3 py-2 text-right">
                            <a href="{{ route('admin.products.variants.create', $product) }}" class="text-emerald-700 hover:underline">Add Variant</a>
                            <span class="mx-1 text-slate-300">|</span>
                            <a href="{{ route('admin.products.edit', $product) }}" class="text-blue-700 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="ml-2 inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-700 hover:underline" onclick="return confirm('Delete this product?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="border border-slate-200 px-3 py-6 text-center text-slate-500">No products yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
@endsection
