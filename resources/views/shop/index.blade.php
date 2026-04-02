<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}">
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-slate-100 text-slate-900">
        @include('partials.header')

        <div class="mx-auto max-w-6xl p-6">

            @include('partials.breadcrumb', ['breadcrumbs' => $breadcrumbs])

            <div class="mb-5 grid gap-3 rounded border border-slate-200 bg-white p-3 md:grid-cols-8">
                {{-- Category navigation (not a form field) --}}
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Category</label>
                    <select onchange="if(this.value) window.location.href = this.value" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        <option value="{{ route('shop.index') }}" @selected(!$activeCategory)>All categories</option>
                        @foreach ($shopCategories as $shopCategory)
                            @if ($shopCategory->children->isNotEmpty())
                                <optgroup label="{{ $shopCategory->name }}">
                                    @foreach ($shopCategory->children as $child)
                                        <option value="{{ route('shop.category', $child) }}" @selected($activeCategory?->slug === $child->slug)>{{ $child->name }}</option>
                                    @endforeach
                                </optgroup>
                            @else
                                <option value="{{ route('shop.category', $shopCategory) }}" @selected($activeCategory?->slug === $shopCategory->slug)>{{ $shopCategory->name }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                {{-- Tag navigation (not a form field) --}}
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Artist / Brand</label>
                    <select onchange="if(this.value) window.location.href = this.value" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        <option value="{{ route('shop.index') }}" @selected(!$activeTag)>All artists & brands</option>
                        @foreach ($shopTags as $shopTag)
                            @php
                                $tagRoute = match($shopTag->type) {
                                    'artist' => route('shop.artist', $shopTag),
                                    'brand' => route('shop.brand', $shopTag),
                                    'custom' => route('shop.tag', $shopTag),
                                };
                            @endphp
                            <option value="{{ $tagRoute }}" @selected($activeTag?->slug === $shopTag->slug)>
                                {{ ucfirst($shopTag->type) }}: {{ $shopTag->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <form method="POST" action="{{ route('shop.filters.store') }}" class="contents" id="filter-form">
                    @csrf
                    <input type="hidden" name="_redirect" value="{{ url()->current() }}">

                    <div class="relative md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-slate-700">Search</label>
                        <input
                            type="text"
                            name="q"
                            id="search-input"
                            value="{{ $filters['q'] }}"
                            placeholder="Search name or excerpt"
                            class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            autocomplete="off"
                            data-search-endpoint="{{ route('api.shop.search') }}"
                        >
                        <div id="search-results" class="absolute left-0 right-0 top-full z-10 mt-1 hidden max-h-64 overflow-y-auto rounded border border-slate-300 bg-white shadow-lg"></div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Sort</label>
                        <select name="sort" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                            <option value="newest" @selected($filters['sort'] === 'newest')>Newest</option>
                            <option value="name_asc" @selected($filters['sort'] === 'name_asc')>Name A-Z</option>
                            <option value="name_desc" @selected($filters['sort'] === 'name_desc')>Name Z-A</option>
                            <option value="price_asc" @selected($filters['sort'] === 'price_asc')>Price low-high</option>
                            <option value="price_desc" @selected($filters['sort'] === 'price_desc')>Price high-low</option>
                        </select>
                    </div>

                    @if ($sizeProfiles->isNotEmpty())
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700">Shop for</label>
                            <select name="size_profile" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                <option value="">All sizes</option>
                                @foreach ($sizeProfiles as $profile)
                                    <option value="{{ $profile->id }}" @selected($filters['size_profile'] == $profile->id)>
                                        {{ $profile->is_self ? 'My sizes' : $profile->name }}
                                        ({{ implode(', ', $profile->allSizes()) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Min Price</label>
                        <input
                            type="number"
                            name="min_price"
                            min="0"
                            step="0.01"
                            value="{{ $filters['min_price'] ?? '' }}"
                            placeholder="0.00"
                            class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                        >
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Max Price</label>
                        <input
                            type="number"
                            name="max_price"
                            min="0"
                            step="0.01"
                            value="{{ $filters['max_price'] ?? '' }}"
                            placeholder="999.99"
                            class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                        >
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="rounded bg-slate-700 px-3 py-2 text-sm text-white hover:bg-slate-800">Filter</button>
                    </div>

                    @if ($activeCategory)
                        <div class="md:col-span-8">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    name="include_out_of_stock"
                                    value="1"
                                    @checked($filters['include_out_of_stock'])
                                    class="rounded border-slate-300"
                                >
                                Show out-of-stock items in this category
                            </label>
                        </div>
                    @endif
                </form>

                {{-- Reset button (separate form) --}}
                <div class="flex items-end">
                    <form method="POST" action="{{ route('shop.filters.reset') }}">
                        @csrf
                        <button type="submit" class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Reset</button>
                    </form>
                </div>
            </div>

            @if ($shopCategories->isNotEmpty())
                <div class="mb-5 flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-slate-500">Browse:</span>
                    @foreach ($shopCategories as $shopCategory)
                        <a
                            href="{{ route('shop.category', $shopCategory) }}"
                            class="rounded border px-2 py-1 transition hover:bg-slate-50 {{ $activeCategory?->slug === $shopCategory->slug ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700' }}"
                        >
                            {{ $shopCategory->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            <p class="mb-4 text-sm text-slate-600">Showing {{ $products->total() }} product(s).</p>

            @if ($products->isEmpty())
                <p class="rounded border border-slate-200 bg-white p-4 text-sm text-slate-600">No active products found.</p>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($products as $product)
                        @php
                            $primaryMedia = $product->media->first();
                            $secondaryMedia = $product->media->skip(1)->first();
                            $mediaUrl = $primaryMedia ? route('media.show', ['path' => $primaryMedia->catalog_path ?? $primaryMedia->path]) : null;
                            $secondaryMediaUrl = $secondaryMedia ? route('media.show', ['path' => $secondaryMedia->catalog_path ?? $secondaryMedia->path]) : null;
                            $startingPrice = $product->min_active_variant_price;

                            $selectorData = $product->catalog_selector_data;
                            $catalogVariants = $product->catalog_variants;
                            $maxPrice = $catalogVariants->max('price');
                            $hasPriceRange = $startingPrice !== null && $maxPrice !== null && (float) $startingPrice !== (float) $maxPrice;
                            $hasGroups = !empty($selectorData['groups']);
                            $hasMultipleVariants = $catalogVariants->count() > 1;

                            // Build a map of variant_id => media URL for image switching
                            $variantMediaMap = [];
                            if ($hasGroups && isset($selectorData['groups']['color'])) {
                                foreach ($product->media as $media) {
                                    if ($media->product_variant_id) {
                                        $variantMediaMap[$media->product_variant_id] = route('media.show', ['path' => $media->catalog_path ?? $media->path]);
                                    }
                                }
                            }
                        @endphp

                        <article
                            class="group/card relative flex flex-col rounded border border-slate-200 bg-white p-4 shadow-sm"
                            data-catalog-card
                            @if ($hasGroups)
                                data-catalog-attribute-order="{{ implode(',', $selectorData['attributeOrder']) }}"
                                data-catalog-variant-media='@json($variantMediaMap)'
                            @endif
                            @if ($activeSizeProfile)
                                data-catalog-preselect-sizes='@json($preselectSizes)'
                            @endif
                        >
                            <a href="{{ route('shop.show', $product) }}" class="group block flex-1">
                                @if ($mediaUrl)
                                    <div class="relative mb-3 h-52 w-full overflow-hidden rounded bg-slate-50">
                                        <img
                                            src="{{ $mediaUrl }}"
                                            alt="{{ $primaryMedia?->alt_text ?: $product->name }}"
                                            data-catalog-primary-image
                                            data-catalog-original-src="{{ $mediaUrl }}"
                                            class="h-52 w-full object-contain transition-opacity duration-200 {{ $secondaryMediaUrl ? 'group-hover:opacity-0' : '' }}"
                                        >
                                        @if ($secondaryMediaUrl)
                                            <img src="{{ $secondaryMediaUrl }}" alt="{{ $secondaryMedia?->alt_text ?: $product->name }}" class="pointer-events-none absolute inset-0 h-52 w-full object-contain opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                                        @endif
                                    </div>
                                @else
                                    <div class="mb-3 h-52 w-full overflow-hidden rounded bg-slate-50">
                                        <img src="{{ asset('images/placeholder-product.svg') }}" alt="No image available" class="h-52 w-full object-contain" data-catalog-primary-image>
                                    </div>
                                @endif
                                <h2 class="text-lg font-semibold">{{ $product->name }}</h2>
                                <p class="mt-1 text-sm text-slate-600">{{ $product->primaryCategory?->name ?? 'Uncategorized' }}</p>
                                @if ($product->tags->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach ($product->tags as $tag)
                                            <span class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-700">{{ ucfirst($tag->type) }}: {{ $tag->name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                @if ($startingPrice !== null)
                                    <p class="mt-1 text-sm font-medium text-slate-800" data-catalog-price>
                                        @if ($hasPriceRange)
                                            From &euro;{{ number_format((float) $startingPrice, 2) }}
                                        @else
                                            &euro;{{ number_format((float) $startingPrice, 2) }}
                                        @endif
                                    </p>
                                @endif
                                @if ($product->plainExcerpt() !== '')
                                    <p class="mt-2 text-sm text-slate-700">{{ $product->plainExcerpt() }}</p>
                                @endif
                            </a>

                            @if ($hasGroups && $hasMultipleVariants)
                                {{-- Variant options + add to cart --}}
                                <div class="mt-auto pt-3" data-catalog-hover-section>
                                    <div class="space-y-2" data-catalog-options>
                                        @foreach ($selectorData['groups'] as $attributeKey => $attributeGroup)
                                            <div>
                                                <p class="mb-1 text-xs font-medium text-slate-500">{{ $attributeGroup['label'] }}</p>
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach ($attributeGroup['values'] as $attributeValue)
                                                        @php
                                                            // Check if this value has at least one available variant
                                                            $isAvailable = $catalogVariants->contains(function ($v) use ($selectorData, $attributeKey, $attributeValue) {
                                                                $attrs = $selectorData['variantAttributesById'][$v->id] ?? [];
                                                                return isset($attrs[$attributeKey]) && $attrs[$attributeKey] === $attributeValue && !$v->is_out_of_stock;
                                                            });
                                                        @endphp
                                                        @if ($isAvailable)
                                                            <button
                                                                type="button"
                                                                data-catalog-attribute="{{ $attributeKey }}"
                                                                data-catalog-attribute-value="{{ $attributeValue }}"
                                                                class="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 transition hover:border-slate-500"
                                                            >
                                                                {{ $attributeValue }}
                                                            </button>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- Hidden variant data store --}}
                                    <select data-catalog-variant-select class="hidden" aria-hidden="true" tabindex="-1">
                                        <option value="" selected disabled>Select variant</option>
                                        @foreach ($catalogVariants as $variant)
                                            @if (!$variant->is_out_of_stock)
                                                <option
                                                    value="{{ $variant->id }}"
                                                    data-variant-price="{{ number_format((float) $variant->price, 2) }}"
                                                    data-variant-attributes='@json($selectorData['variantAttributesById'][$variant->id] ?? [])'
                                                >
                                                    {{ $variant->name }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>

                                    <form method="POST" action="{{ route('cart.items.store') }}" data-catalog-cart-form class="mt-2">
                                        @csrf
                                        <input type="hidden" name="variant_id" value="" data-catalog-cart-variant-input>
                                        <input type="hidden" name="quantity" value="1">
                                        <button
                                            type="submit"
                                            data-catalog-add-to-cart
                                            class="w-full rounded bg-slate-800 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-900"
                                        >
                                            Select options
                                        </button>
                                    </form>
                                </div>
                            @elseif (!$hasMultipleVariants && $catalogVariants->count() === 1)
                                @php $singleVariant = $catalogVariants->first(); @endphp
                                @if (!$singleVariant->is_out_of_stock)
                                    {{-- Single variant: direct add to cart --}}
                                    <div class="mt-auto pt-3" data-catalog-hover-section>
                                        <form method="POST" action="{{ route('cart.items.store') }}" data-catalog-cart-form>
                                            @csrf
                                            <input type="hidden" name="variant_id" value="{{ $singleVariant->id }}">
                                            <input type="hidden" name="quantity" value="1">
                                            <button
                                                type="submit"
                                                data-catalog-add-to-cart
                                                data-catalog-single-variant
                                                class="w-full rounded bg-slate-800 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-900"
                                            >
                                                Add to cart &mdash; &euro;{{ number_format((float) $singleVariant->price, 2) }}
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            @endif
                        </article>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $products->links() }}
                </div>
            @endif
        </div>

        @include('partials.footer')
    </body>
</html>
