@extends('admin.layout')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">New Product</h2>
            <a href="{{ route('admin.products.index') }}" class="text-sm text-slate-600 hover:underline">&larr; Back to Products</a>
        </div>

        <form method="POST" action="{{ route('admin.products.store') }}" class="space-y-6" enctype="multipart/form-data" novalidate>
            @csrf

            {{-- Identity --}}
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-700">Identity</h3>
                <div class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Name</label>
                            <input type="text" name="name" value="{{ old('name') }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Slug</label>
                            <input type="text" name="slug" value="{{ old('slug') }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
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
                        <div>
                            <label class="mb-1 block text-sm font-medium">Published At</label>
                            <input type="datetime-local" name="published_at" value="{{ old('published_at') }}" class="w-full rounded border border-slate-300 px-3 py-2">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Content --}}
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-700">Content</h3>
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Excerpt</label>
                        <textarea name="excerpt" rows="2" class="w-full rounded border border-slate-300 px-3 py-2">{{ old('excerpt') }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Description</label>
                        <textarea name="description" rows="6" class="w-full rounded border border-slate-300 px-3 py-2">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Categorization --}}
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-700">Categorization</h3>
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Additional Categories</label>
                        <select name="category_ids[]" multiple class="w-full rounded border border-slate-300 px-3 py-2">
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(in_array((string) $category->id, array_map('strval', old('category_ids', [])), true))>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Artists / Brands</label>
                        <select name="tag_ids[]" multiple class="w-full rounded border border-slate-300 px-3 py-2">
                            @foreach ($tags as $tag)
                                <option value="{{ $tag->id }}" @selected(in_array((string) $tag->id, array_map('strval', old('tag_ids', [])), true))>
                                    {{ ucfirst($tag->type) }}: {{ $tag->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Assign one or multiple artists/brands to power storefront filters and follower notifications.</p>
                    </div>
                </div>
            </div>

            {{-- Settings & Flags --}}
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-700">Settings</h3>
                <div class="space-y-4">
                    <div class="flex flex-wrap gap-6">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured') === '1')>
                            Featured
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_preorder" value="1" @checked(old('is_preorder') === '1')>
                            Preorder Enabled
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_bundle_exclusive" value="1" @checked(old('is_bundle_exclusive') === '1')>
                            Bundle Exclusive
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
                </div>
            </div>

            {{-- SEO --}}
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-700">SEO</h3>
                <div class="space-y-4">
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
            </div>

            {{-- Media Upload --}}
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-700">Upload Media</h3>
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Upload Images / Videos</label>
                        <input type="file" name="media_files[]" accept="image/*,video/*" multiple class="w-full rounded border border-slate-300 px-3 py-2">
                        <p class="mt-1 text-xs text-slate-500">Allowed: JPG, JPEG, PNG, WEBP, AVIF, MP4, WEBM, MOV. Max 50MB per file.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Alt Text (optional)</label>
                        <input type="text" name="media_alt_text" value="" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="e.g. Black tee front view">
                    </div>
                    <p class="text-xs text-slate-500">You can assign media to specific variants after creating the product.</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Create</button>
                <a href="{{ route('admin.products.index') }}" class="text-sm text-slate-600 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
