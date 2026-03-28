@extends('admin.layout')

@section('content')
    <h2 class="mb-4 text-lg font-semibold">Edit Product: {{ $product->name }}</h2>

    <form method="POST" action="{{ route('admin.products.update', $product) }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="mb-1 block text-sm font-medium">Name</label>
            <input type="text" name="name" value="{{ old('name', $product->name) }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Slug</label>
            <input type="text" name="slug" value="{{ old('slug', $product->slug) }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Status</label>
                <select name="status" class="w-full rounded border border-slate-300 px-3 py-2" required>
                    <option value="draft" @selected(old('status', $product->status) === 'draft')>Draft</option>
                    <option value="active" @selected(old('status', $product->status) === 'active')>Active</option>
                    <option value="archived" @selected(old('status', $product->status) === 'archived')>Archived</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Primary Category</label>
                <select name="primary_category_id" class="w-full rounded border border-slate-300 px-3 py-2">
                    <option value="">None</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('primary_category_id', $product->primary_category_id) === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Additional Categories</label>
            <select name="category_ids[]" multiple class="w-full rounded border border-slate-300 px-3 py-2">
                @php
                    $selectedCategories = array_map('strval', old('category_ids', $product->categories->pluck('id')->all()));
                @endphp
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(in_array((string) $category->id, $selectedCategories, true))>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Excerpt</label>
            <textarea name="excerpt" rows="2" class="w-full rounded border border-slate-300 px-3 py-2">{{ old('excerpt', $product->excerpt) }}</textarea>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Description</label>
            <textarea name="description" rows="6" class="w-full rounded border border-slate-300 px-3 py-2">{{ old('description', $product->description) }}</textarea>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Meta Title</label>
                <input type="text" name="meta_title" value="{{ old('meta_title', $product->meta_title) }}" class="w-full rounded border border-slate-300 px-3 py-2">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Published At</label>
                <input type="datetime-local" name="published_at" value="{{ old('published_at', optional($product->published_at)->format('Y-m-d\TH:i')) }}" class="w-full rounded border border-slate-300 px-3 py-2">
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Meta Description</label>
            <textarea name="meta_description" rows="3" class="w-full rounded border border-slate-300 px-3 py-2">{{ old('meta_description', $product->meta_description) }}</textarea>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_featured" value="1" @checked((string) old('is_featured', $product->is_featured ? '1' : '0') === '1')>
                Featured
            </label>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_preorder" value="1" @checked((string) old('is_preorder', $product->is_preorder ? '1' : '0') === '1')>
                Preorder Enabled
            </label>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Preorder Available From</label>
                <input type="datetime-local" name="preorder_available_from" value="{{ old('preorder_available_from', optional($product->preorder_available_from)->format('Y-m-d\TH:i')) }}" class="w-full rounded border border-slate-300 px-3 py-2">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Expected Ship At</label>
                <input type="datetime-local" name="expected_ship_at" value="{{ old('expected_ship_at', optional($product->expected_ship_at)->format('Y-m-d\TH:i')) }}" class="w-full rounded border border-slate-300 px-3 py-2">
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Update</button>
            <a href="{{ route('admin.products.index') }}" class="text-sm text-slate-600 hover:underline">Cancel</a>
        </div>
    </form>

    <hr class="my-8 border-slate-200">

    <div class="mb-4 flex items-center justify-between">
        <h3 class="text-base font-semibold">Variants</h3>
        <a href="{{ route('admin.products.variants.create', $product) }}" class="rounded bg-emerald-600 px-3 py-2 text-sm text-white hover:bg-emerald-700">Add Variant</a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full border border-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="border border-slate-200 px-3 py-2 text-left">Name</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">SKU</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Price</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Stock</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Preorder</th>
                    <th class="border border-slate-200 px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($product->variants as $variant)
                    <tr>
                        <td class="border border-slate-200 px-3 py-2">{{ $variant->name }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ $variant->sku }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ number_format((float) $variant->price, 2) }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ $variant->stock_quantity }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ $variant->is_preorder ? 'Yes' : 'No' }}</td>
                        <td class="border border-slate-200 px-3 py-2 text-right">
                            <a href="{{ route('admin.products.variants.edit', [$product, $variant]) }}" class="text-blue-700 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.products.variants.destroy', [$product, $variant]) }}" class="ml-2 inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-700 hover:underline" onclick="return confirm('Delete this variant?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="border border-slate-200 px-3 py-6 text-center text-slate-500">No variants yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
