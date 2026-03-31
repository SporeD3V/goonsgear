@extends('admin.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Products</h2>
        <a href="{{ route('admin.products.create') }}" class="rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">New Product</a>
    </div>

    <form method="GET" action="{{ route('admin.products.index') }}" class="mb-4 grid gap-3 rounded border border-slate-200 bg-slate-50 p-3 md:grid-cols-6">
        <div>
            <label for="q" class="mb-1 block text-xs font-medium text-slate-700">Search</label>
            <input id="q" name="q" type="text" value="{{ $filters['q'] }}" placeholder="Name / slug / excerpt" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="status" class="mb-1 block text-xs font-medium text-slate-700">Status</label>
            <select id="status" name="status" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach (['draft', 'active', 'archived'] as $statusOption)
                    <option value="{{ $statusOption }}" @selected($filters['status'] === $statusOption)>{{ ucfirst($statusOption) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="category" class="mb-1 block text-xs font-medium text-slate-700">Category</label>
            <select id="category" name="category" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($filters['category'] === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="sales" class="mb-1 block text-xs font-medium text-slate-700">Sales</label>
            <select id="sales" name="sales" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                <option value="never_sold" @selected($filters['sales'] === 'never_sold')>Never sold</option>
                <option value="sold" @selected($filters['sales'] === 'sold')>Has sales</option>
            </select>
        </div>
        <div>
            <label for="stock" class="mb-1 block text-xs font-medium text-slate-700">Stock</label>
            <select id="stock" name="stock" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                <option value="zero_stock" @selected($filters['stock'] === 'zero_stock')>Zero stock</option>
                <option value="in_stock" @selected($filters['stock'] === 'in_stock')>Has stock</option>
            </select>
        </div>
        <div>
            <label for="preorder" class="mb-1 block text-xs font-medium text-slate-700">Preorder</label>
            <select id="preorder" name="preorder" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                <option value="only_preorder" @selected($filters['preorder'] === 'only_preorder')>Only preorder</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="rounded bg-slate-700 px-3 py-2 text-sm text-white hover:bg-slate-800">Filter</button>
            <a href="{{ route('admin.products.index') }}" class="rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">Reset</a>
        </div>
    </form>

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
                    <th class="border border-slate-200 px-3 py-2 text-center">Waiting</th>
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
                        <td class="border border-slate-200 px-3 py-2 text-center">
                            @if ($product->active_stock_alert_subscriptions_count > 0)
                                <a href="{{ route('admin.products.stock-alerts', $product) }}" class="inline-block rounded bg-amber-100 px-2 py-1 text-amber-700 hover:bg-amber-200">
                                    {{ $product->active_stock_alert_subscriptions_count }}
                                </a>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
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
                        <td colspan="8" class="border border-slate-200 px-3 py-6 text-center text-slate-500">No products yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
@endsection
