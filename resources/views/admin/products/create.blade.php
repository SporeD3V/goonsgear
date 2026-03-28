@extends('admin.layout')

@section('content')
    <h2 class="mb-4 text-lg font-semibold">New Product</h2>

    <form method="POST" action="{{ route('admin.products.store') }}" class="space-y-4">
        @csrf

        <div>
            <label class="mb-1 block text-sm font-medium">Name</label>
            <input type="text" name="name" value="{{ old('name') }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Slug</label>
            <input type="text" name="slug" value="{{ old('slug') }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Status</label>
                <select name="status" class="w-full rounded border border-slate-300 px-3 py-2" required>
                    <option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option>
                    <option value="active" @selected(old('status') === 'active')>Active</option>
                    <option value="archived" @selected(old('status') === 'archived')>Archived</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Primary Category</label>
                <select name="primary_category_id" class="w-full rounded border border-slate-300 px-3 py-2">
                    <option value="">None</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('primary_category_id') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Additional Categories</label>
            <select name="category_ids[]" multiple class="w-full rounded border border-slate-300 px-3 py-2">
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(in_array((string) $category->id, array_map('strval', old('category_ids', [])), true))>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Excerpt</label>
            <textarea name="excerpt" rows="2" class="w-full rounded border border-slate-300 px-3 py-2">{{ old('excerpt') }}</textarea>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Description</label>
            <textarea name="description" rows="6" class="w-full rounded border border-slate-300 px-3 py-2">{{ old('description') }}</textarea>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Meta Title</label>
                <input type="text" name="meta_title" value="{{ old('meta_title') }}" class="w-full rounded border border-slate-300 px-3 py-2">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Published At</label>
                <input type="datetime-local" name="published_at" value="{{ old('published_at') }}" class="w-full rounded border border-slate-300 px-3 py-2">
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Meta Description</label>
            <textarea name="meta_description" rows="3" class="w-full rounded border border-slate-300 px-3 py-2">{{ old('meta_description') }}</textarea>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured') === '1')>
                Featured
            </label>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_preorder" value="1" @checked(old('is_preorder') === '1')>
                Preorder Enabled
            </label>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Preorder Available From</label>
                <input type="datetime-local" name="preorder_available_from" value="{{ old('preorder_available_from') }}" class="w-full rounded border border-slate-300 px-3 py-2">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Expected Ship At</label>
                <input type="datetime-local" name="expected_ship_at" value="{{ old('expected_ship_at') }}" class="w-full rounded border border-slate-300 px-3 py-2">
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Create</button>
            <a href="{{ route('admin.products.index') }}" class="text-sm text-slate-600 hover:underline">Cancel</a>
        </div>
    </form>
@endsection
