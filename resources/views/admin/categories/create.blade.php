@extends('admin.layout')

@section('content')
    <h2 class="mb-4 text-lg font-semibold">New Category</h2>

    <form method="POST" action="{{ route('admin.categories.store') }}" class="space-y-4" novalidate>
        @csrf

        <div>
            <label class="mb-1 block text-sm font-medium">Name</label>
            <input type="text" name="name" value="{{ old('name') }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Slug</label>
            <input type="text" name="slug" value="{{ old('slug') }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Parent Category</label>
            <select name="parent_id" class="w-full rounded border border-slate-300 px-3 py-2">
                <option value="">None</option>
                @foreach ($parentCategories as $parentCategory)
                    <option value="{{ $parentCategory->id }}" @selected((string) old('parent_id') === (string) $parentCategory->id)>{{ $parentCategory->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Description</label>
            <textarea name="description" rows="4" class="w-full rounded border border-slate-300 px-3 py-2">{{ old('description') }}</textarea>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Sort Order</label>
            <input type="number" min="0" name="sort_order" value="{{ old('sort_order', 0) }}" class="w-full rounded border border-slate-300 px-3 py-2">
        </div>

        <div class="space-y-4 rounded border border-slate-200 p-4">
            <h3 class="text-sm font-semibold">SEO</h3>

            @include('admin.partials.seo-field', [
                'name'  => 'meta_title',
                'label' => 'Meta Title',
                'value' => old('meta_title', ''),
                'min'   => 50,
                'max'   => 60,
                'hint'  => 'Recommended 50–60 characters. This appears as the clickable headline in search results.',
            ])

            @include('admin.partials.seo-field', [
                'name'  => 'meta_description',
                'label' => 'Meta Description',
                'value' => old('meta_description', ''),
                'type'  => 'textarea',
                'rows'  => 3,
                'min'   => 120,
                'max'   => 160,
                'hint'  => 'Recommended 120–160 characters. This appears below the title in search results.',
            ])
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Size Type</label>
            <select name="size_type" class="w-full rounded border border-slate-300 px-3 py-2">
                <option value="">None (not sized)</option>
                <option value="top" @selected(old('size_type') === 'top')>Top (shirts, hoodies)</option>
                <option value="bottom" @selected(old('size_type') === 'bottom')>Bottom (pants, shorts)</option>
                <option value="shoe" @selected(old('size_type') === 'shoe')>Shoe (socks, footwear)</option>
            </select>
        </div>

        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1')>
            Active
        </label>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Create</button>
            <a href="{{ route('admin.categories.index') }}" class="text-sm text-slate-600 hover:underline">Cancel</a>
        </div>
    </form>
@endsection
