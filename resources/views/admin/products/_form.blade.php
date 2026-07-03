{{--
    Shared product form for create + edit.

    Expects:
        $product    – Product|null (null on create)
        $categories – Collection<Category>
        $tags       – Collection<Tag>
--}}

@php
    $editing = $product !== null;
    $inputClasses = 'w-full rounded-lg border border-stone-200 px-3 py-2 text-sm text-stone-700 focus:border-[#36a2eb] focus:outline-none focus:ring-1 focus:ring-[#36a2eb]';
    $selectedCategories = array_map('strval', old('category_ids', $editing ? $product->categories->pluck('id')->all() : []));
    $selectedTags = array_map('strval', old('tag_ids', $editing ? $product->tags->pluck('id')->all() : []));
    $preorderEnabled = (string) old('is_preorder', $editing && $product->is_preorder ? '1' : '0') === '1';
@endphp

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

<form
    id="product-form"
    method="POST"
    action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}"
    enctype="multipart/form-data"
    novalidate
>
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">

        {{-- ── Main column ─────────────────────────────────────── --}}
        <div class="min-w-0 space-y-6">

            {{-- Identity --}}
            <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="1"
                 x-data="{
                    name: @js(old('name', $product->name ?? '')),
                    slug: @js(old('slug', $product->slug ?? '')),
                    slugTouched: {{ $editing || old('slug') ? 'true' : 'false' }},
                    slugify(value) {
                        return value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
                    },
                    syncSlug() {
                        if (! this.slugTouched) {
                            this.slug = this.slugify(this.name);
                        }
                    },
                 }">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-stone-600">Identity</h3>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="product-name" class="mb-1 block text-sm font-medium text-stone-700">Name</label>
                        <input id="product-name" type="text" name="name" x-model="name" x-on:input="syncSlug()" class="{{ $inputClasses }}" required>
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="product-slug" class="mb-1 block text-sm font-medium text-stone-700">Slug</label>
                        <input id="product-slug" type="text" name="slug" x-model="slug" x-on:input="slugTouched = true" class="{{ $inputClasses }} font-mono" required>
                        @if (! $editing)
                            <p class="mt-1 text-xs text-stone-400">Fills in automatically from the name — edit to override.</p>
                        @endif
                        @error('slug')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- Content --}}
            <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="2">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-stone-600">Content</h3>
                <div class="space-y-4">
                    <div>
                        <label for="product-excerpt" class="mb-1 block text-sm font-medium text-stone-700">Excerpt</label>
                        <textarea id="product-excerpt" name="excerpt" rows="2" class="{{ $inputClasses }}" placeholder="Short teaser shown on listing cards">{{ old('excerpt', $product->excerpt ?? '') }}</textarea>
                    </div>
                    <div>
                        <label for="product-description" class="mb-1 block text-sm font-medium text-stone-700">Description</label>
                        <textarea id="product-description" name="description" rows="7" class="{{ $inputClasses }}">{{ old('description', $product->description ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- SEO --}}
            <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="3">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-stone-600">SEO</h3>
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

            {{-- Media upload --}}
            <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="4">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-stone-600">Upload Media</h3>
                <div class="space-y-4" x-data="{ files: 0 }">
                    <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-stone-200 px-6 py-8 text-center transition hover:border-[#36a2eb] hover:bg-[#36a2eb]/5">
                        <input type="file" name="media_files[]" accept="image/*,video/*" multiple class="sr-only" x-on:change="files = $event.target.files.length">
                        <svg class="h-8 w-8 text-stone-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                        <span class="text-sm font-medium text-stone-600" x-show="files === 0">Click to choose images or videos</span>
                        <span class="text-sm font-medium text-[#36a2eb]" x-show="files > 0" x-cloak x-text="files + (files === 1 ? ' file selected' : ' files selected')"></span>
                        <span class="text-xs text-stone-400">JPG, PNG, WEBP, AVIF, MP4, WEBM, MOV — max 50MB each</span>
                    </label>
                    @error('media_files.*')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="media-alt-text" class="mb-1 block text-sm font-medium text-stone-700">Alt text (optional)</label>
                            <input id="media-alt-text" type="text" name="media_alt_text" value="" class="{{ $inputClasses }}" placeholder="e.g. Black tee front view">
                        </div>
                        @if ($editing)
                            <div>
                                <label for="media-variant" class="mb-1 block text-sm font-medium text-stone-700">Assign uploads to variant</label>
                                <select id="media-variant" name="media_variant_id" class="{{ $inputClasses }}">
                                    <option value="">All variants (product gallery)</option>
                                    @foreach ($product->variants as $variant)
                                        <option value="{{ $variant->id }}">{{ $variant->name }} ({{ $variant->sku }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    <p class="text-xs text-stone-400">
                        {{ $editing ? 'Manage existing media (preview, set primary, delete) in the gallery below.' : 'You can assign media to specific variants after creating the product.' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- ── Side rail ───────────────────────────────────────── --}}
        <div class="space-y-6">

            {{-- Publishing --}}
            <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="1">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-stone-600">Publishing</h3>
                <div class="space-y-4">
                    <div>
                        <label for="product-status" class="mb-1 block text-sm font-medium text-stone-700">Status</label>
                        <select id="product-status" name="status" class="{{ $inputClasses }}" required>
                            <option value="draft" @selected(old('status', $product->status ?? 'draft') === 'draft')>Draft</option>
                            <option value="active" @selected(old('status', $product->status ?? '') === 'active')>Active</option>
                            <option value="archived" @selected(old('status', $product->status ?? '') === 'archived')>Archived</option>
                        </select>
                        @error('status')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="primary-category" class="mb-1 block text-sm font-medium text-stone-700">Primary category</label>
                        <select id="primary-category" name="primary_category_id" class="{{ $inputClasses }}">
                            <option value="">None</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('primary_category_id', $product->primary_category_id ?? '') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="published-at" class="mb-1 block text-sm font-medium text-stone-700">Published at</label>
                        <input id="published-at" type="datetime-local" name="published_at" value="{{ old('published_at', $editing ? optional($product->published_at)->format('Y-m-d\TH:i') : '') }}" class="{{ $inputClasses }}">
                    </div>
                </div>
            </div>

            {{-- Flags --}}
            <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="2"
                 x-data="{ preorder: {{ $preorderEnabled ? 'true' : 'false' }} }">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-stone-600">Flags</h3>
                <div class="divide-y divide-stone-100">
                    <label class="flex cursor-pointer items-center justify-between gap-3 py-3 first:pt-0">
                        <span class="text-sm">
                            <span class="font-medium text-stone-700">Featured</span><br>
                            <span class="text-xs text-stone-400">Highlighted on the homepage.</span>
                        </span>
                        <input type="checkbox" name="is_featured" value="1" class="peer sr-only" @checked((string) old('is_featured', $editing && $product->is_featured ? '1' : '0') === '1')>
                        <span aria-hidden="true" class="relative h-6 w-11 shrink-0 rounded-full bg-stone-200 transition after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:bg-[#36a2eb] peer-checked:after:translate-x-5"></span>
                    </label>

                    <label class="flex cursor-pointer items-center justify-between gap-3 py-3">
                        <span class="text-sm">
                            <span class="font-medium text-stone-700">Pre-order</span><br>
                            <span class="text-xs text-stone-400">Sellable before release.</span>
                        </span>
                        <input type="checkbox" name="is_preorder" value="1" class="peer sr-only" x-on:change="preorder = $event.target.checked" @checked($preorderEnabled)>
                        <span aria-hidden="true" class="relative h-6 w-11 shrink-0 rounded-full bg-stone-200 transition after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:bg-[#36a2eb] peer-checked:after:translate-x-5"></span>
                    </label>

                    <div x-show="preorder" x-transition.origin.top x-cloak class="space-y-3 py-3">
                        <div>
                            <label for="preorder-from" class="mb-1 block text-sm font-medium text-stone-700">Available from</label>
                            <input id="preorder-from" type="datetime-local" name="preorder_available_from" value="{{ old('preorder_available_from', $editing ? optional($product->preorder_available_from)->format('Y-m-d\TH:i') : '') }}" class="{{ $inputClasses }}">
                            <p class="mt-1 text-xs text-stone-400">Pre-orders auto-release once this date passes. Leave empty to keep it a pre-order until you switch it off.</p>
                        </div>
                        <div>
                            <label for="expected-ship" class="mb-1 block text-sm font-medium text-stone-700">Expected to ship</label>
                            <input id="expected-ship" type="datetime-local" name="expected_ship_at" value="{{ old('expected_ship_at', $editing ? optional($product->expected_ship_at)->format('Y-m-d\TH:i') : '') }}" class="{{ $inputClasses }}">
                        </div>
                    </div>

                    <label class="flex cursor-pointer items-center justify-between gap-3 py-3 last:pb-0">
                        <span class="text-sm">
                            <span class="font-medium text-stone-700">Bundle exclusive</span><br>
                            <span class="text-xs text-stone-400">Only sold as part of a bundle.</span>
                        </span>
                        <input type="checkbox" name="is_bundle_exclusive" value="1" class="peer sr-only" @checked((string) old('is_bundle_exclusive', $editing && $product->is_bundle_exclusive ? '1' : '0') === '1')>
                        <span aria-hidden="true" class="relative h-6 w-11 shrink-0 rounded-full bg-stone-200 transition after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:bg-[#36a2eb] peer-checked:after:translate-x-5"></span>
                    </label>
                </div>
            </div>

            {{-- Organization --}}
            <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="3">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-stone-600">Organization</h3>

                <p class="mb-1 text-sm font-medium text-stone-700">Additional categories</p>
                <div class="max-h-44 overflow-y-auto rounded-lg border border-stone-100 bg-stone-50/50 p-2">
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($categories as $category)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" class="peer sr-only" @checked(in_array((string) $category->id, $selectedCategories, true))>
                                <span class="inline-block rounded-full border border-stone-200 bg-white px-2.5 py-1 text-[12px] font-medium text-stone-600 transition hover:border-stone-300 peer-checked:border-[#36a2eb] peer-checked:bg-[#36a2eb]/10 peer-checked:text-[#36a2eb]">{{ $category->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <p class="mb-1 mt-4 text-sm font-medium text-stone-700">Artists &amp; brands</p>
                <div class="max-h-56 overflow-y-auto rounded-lg border border-stone-100 bg-stone-50/50 p-2">
                    @foreach (['artist' => 'Artists', 'brand' => 'Brands', 'custom' => 'Tags'] as $type => $typeLabel)
                        @php $typeTags = $tags->where('type', $type); @endphp
                        @if ($typeTags->isNotEmpty())
                            <p class="mb-1 mt-2 px-1 text-[10px] font-bold uppercase tracking-wider text-stone-400 first:mt-0">{{ $typeLabel }}</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($typeTags as $tag)
                                    <label class="cursor-pointer">
                                        <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" class="peer sr-only" @checked(in_array((string) $tag->id, $selectedTags, true))>
                                        <span class="inline-block rounded-full border border-stone-200 bg-white px-2.5 py-1 text-[12px] font-medium text-stone-600 transition hover:border-stone-300 peer-checked:border-[#36a2eb] peer-checked:bg-[#36a2eb]/10 peer-checked:text-[#36a2eb]">{{ $tag->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-stone-400">Followers of selected artists/brands get drop and discount emails.</p>
            </div>

            {{-- Save --}}
            <div class="flex items-center gap-3">
                <button type="submit" class="flex-1 rounded-lg bg-[#36a2eb] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b8ac9]">
                    {{ $editing ? 'Save changes' : 'Create product' }}
                </button>
                <a href="{{ route('admin.products.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-stone-500 transition hover:bg-stone-100 hover:text-stone-700">Cancel</a>
            </div>
        </div>
    </div>
</form>
