@extends('admin.layout')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Edit Product: {{ $product->name }}</h2>
            <a href="{{ route('admin.products.index') }}" class="text-sm text-slate-600 hover:underline">&larr; Back to Products</a>
        </div>

        <form method="POST" action="{{ route('admin.products.update', $product) }}" class="space-y-6" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PUT')

            {{-- Identity --}}
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-700">Identity</h3>
                <div class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Name</label>
                            <input type="text" name="name" value="{{ old('name', $product->name) }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Slug</label>
                            <input type="text" name="slug" value="{{ old('slug', $product->slug) }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
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
                        <div>
                            <label class="mb-1 block text-sm font-medium">Published At</label>
                            <input type="datetime-local" name="published_at" value="{{ old('published_at', optional($product->published_at)->format('Y-m-d\TH:i')) }}" class="w-full rounded border border-slate-300 px-3 py-2">
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
                        <textarea name="excerpt" rows="2" class="w-full rounded border border-slate-300 px-3 py-2">{{ old('excerpt', $product->excerpt) }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Description</label>
                        <textarea name="description" rows="6" class="w-full rounded border border-slate-300 px-3 py-2">{{ old('description', $product->description) }}</textarea>
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
                            @php
                                $selectedCategories = array_map('strval', old('category_ids', $product->categories->pluck('id')->all()));
                            @endphp
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(in_array((string) $category->id, $selectedCategories, true))>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Artists / Brands</label>
                        <select name="tag_ids[]" multiple class="w-full rounded border border-slate-300 px-3 py-2">
                            @php
                                $selectedTags = array_map('strval', old('tag_ids', $product->tags->pluck('id')->all()));
                            @endphp
                            @foreach ($tags as $tag)
                                <option value="{{ $tag->id }}" @selected(in_array((string) $tag->id, $selectedTags, true))>
                                    {{ ucfirst($tag->type) }}: {{ $tag->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Followers of these artists/brands can receive drop and discount notifications.</p>
                    </div>
                </div>
            </div>

            {{-- Settings & Flags --}}
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-700">Settings</h3>
                <div class="space-y-4">
                    <div class="flex flex-wrap gap-6">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_featured" value="1" @checked((string) old('is_featured', $product->is_featured ? '1' : '0') === '1')>
                            Featured
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_preorder" value="1" @checked((string) old('is_preorder', $product->is_preorder ? '1' : '0') === '1')>
                            Preorder Enabled
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_bundle_exclusive" value="1" @checked((string) old('is_bundle_exclusive', $product->is_bundle_exclusive ? '1' : '0') === '1')>
                            Bundle Exclusive
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
                </div>
            </div>

            {{-- SEO --}}
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-700">SEO</h3>
                <div class="space-y-4">
                    @include('admin.partials.seo-field', [
                        'name'  => 'meta_title',
                        'label' => 'Meta Title',
                        'value' => old('meta_title', $product->meta_title ?? ''),
                        'min'   => 50,
                        'max'   => 60,
                        'hint'  => 'Recommended 50–60 characters. This appears as the clickable headline in search results.',
                    ])

                    @include('admin.partials.seo-field', [
                        'name'  => 'meta_description',
                        'label' => 'Meta Description',
                        'value' => old('meta_description', $product->meta_description ?? ''),
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
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Upload Images / Videos</label>
                            <input type="file" name="media_files[]" accept="image/*,video/*" multiple class="w-full rounded border border-slate-300 px-3 py-2">
                            <p class="mt-1 text-xs text-slate-500">Allowed: JPG, JPEG, PNG, WEBP, AVIF, MP4, WEBM, MOV. Max 50MB per file.</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Assign Uploaded Media To Variant</label>
                            <select name="media_variant_id" class="w-full rounded border border-slate-300 px-3 py-2">
                                <option value="">All Variants (Product Gallery)</option>
                                @foreach ($product->variants as $variant)
                                    <option value="{{ $variant->id }}">{{ $variant->name }} ({{ $variant->sku }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Alt Text (optional)</label>
                        <input type="text" name="media_alt_text" value="" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="e.g. Black tee front view">
                    </div>
                    <p class="text-xs text-slate-500">Media management (preview, set primary, delete) is available below after saving.</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Update</button>
                <a href="{{ route('admin.products.index') }}" class="text-sm text-slate-600 hover:underline">Cancel</a>
            </div>
        </form>

        {{-- Media Gallery --}}
        @if ($product->media->isNotEmpty())
            @php
                $primaryMedia = $product->media->first();
            @endphp

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" data-media-gallery>
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-700">Current Media</h3>

            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium">Preview Variant</label>
                    <select data-media-variant-filter class="w-full rounded border border-slate-300 px-3 py-2">
                        <option value="all">All Variants</option>
                        @foreach ($product->variants as $variant)
                            <option value="{{ $variant->id }}">{{ $variant->name }} ({{ $variant->sku }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="rounded border border-slate-200 p-3">
                <div class="mb-3 overflow-hidden rounded border border-slate-200 bg-slate-50">
                    @if ($primaryMedia !== null)
                        @php
                            $primaryMediaUrl = route('media.show', ['path' => $primaryMedia->path]);
                            $isPrimaryVideo = str_starts_with((string) $primaryMedia->mime_type, 'video/');
                        @endphp

                        <img
                            data-media-main-image
                            src="{{ $isPrimaryVideo ? '' : $primaryMediaUrl }}"
                            alt="{{ $primaryMedia->alt_text ?: $product->name }}"
                            class="{{ $isPrimaryVideo ? 'hidden' : '' }} h-72 w-full object-contain"
                        >

                        <video
                            data-media-main-video
                            controls
                            class="{{ $isPrimaryVideo ? '' : 'hidden' }} h-72 w-full bg-black object-contain"
                            src="{{ $isPrimaryVideo ? $primaryMediaUrl : '' }}"
                        ></video>
                    @else
                        <img src="{{ asset('images/placeholder-product.svg') }}" alt="No image available" class="h-72 w-full object-contain">
                    @endif
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-media-thumbnails>
                    @foreach ($product->media as $media)
                        @php
                            $mediaUrl = route('media.show', ['path' => $media->path]);
                            $isVideo = str_starts_with((string) $media->mime_type, 'video/');
                        @endphp

                        <div
                            class="rounded border border-slate-200 p-2 text-left"
                            tabindex="0"
                            data-media-thumb
                            data-media-url="{{ $mediaUrl }}"
                            data-media-type="{{ $isVideo ? 'video' : 'image' }}"
                            data-media-variant-id="{{ $media->product_variant_id ?? '' }}"
                            data-media-alt="{{ $media->alt_text ?: $product->name }}"
                        >
                            @if ($isVideo)
                                <div class="mb-2 flex h-20 items-center justify-center rounded bg-slate-900 text-xs font-medium text-white">VIDEO</div>
                            @else
                                <img src="{{ $mediaUrl }}" alt="{{ $media->alt_text ?: $product->name }}" class="mb-2 h-20 w-full rounded object-cover">
                            @endif
                            <p class="text-xs text-slate-600">{{ $media->alt_text ?: 'No alt text' }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $media->variant?->name ?? 'All Variants' }} · {{ $media->is_primary ? 'Primary' : 'Gallery' }}
                            </p>
                            <p class="mt-1 text-xs {{ $media->is_converted ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ $media->is_converted ? 'Converted: '.strtoupper((string) $media->converted_to) : 'Not converted (original/fallback)' }}
                            </p>

                            <div class="mt-2 flex items-center gap-2">
                                @if (! $media->is_primary)
                                    <form method="POST" action="{{ route('admin.products.media.primary', [$product, $media]) }}">
                                        @csrf
                                        <button type="submit" class="text-xs text-blue-700 hover:underline">Set Primary</button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('admin.products.media.destroy', [$product, $media]) }}">
                                    @csrf
                                    <button type="submit" class="text-xs text-red-700 hover:underline" onclick="return confirm('Delete this media item?')">Delete</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-700">Current Media</h3>
                <p class="text-xs text-slate-500">No media uploaded yet.</p>
            </div>
        @endif

        {{-- Variants --}}
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Variants</h3>
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

        {{-- Edit History --}}
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            @include('admin.partials.edit-history', ['histories' => $editHistories])
        </div>
    </div>
@endsection
