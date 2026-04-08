<div>
    {{-- Loading overlay --}}
    <div wire:loading.delay class="pointer-events-none fixed inset-x-0 top-0 z-50 h-1 animate-pulse bg-slate-700"></div>

    @if ($activeCategory || $activeTag)
        {{-- Two-column layout --}}
        <div class="flex flex-col lg:flex-row lg:items-start lg:gap-8">
            {{-- Sidebar --}}
            <aside class="mb-4 lg:mb-0 lg:w-64 lg:shrink-0" x-data="{ filtersOpen: false }">
                {{-- Mobile filter toggle --}}
                <button
                    @click="filtersOpen = !filtersOpen"
                    type="button"
                    class="flex w-full items-center justify-between rounded border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 lg:hidden"
                >
                    <span class="flex items-center gap-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"/></svg>
                        Filters
                    </span>
                    <svg class="h-4 w-4 transition" :class="filtersOpen && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                </button>

                {{-- Filter panel --}}
                <div
                    class="mt-3 space-y-5 rounded border border-slate-200 bg-white p-4 max-lg:hidden lg:mt-0 lg:sticky lg:top-20"
                    :class="filtersOpen && '!block'"
                >
                    {{-- Search --}}
                    <div class="relative" x-data="searchAutocomplete()" x-on:click.outside="open = false">
                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Search</p>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            x-on:input.debounce.300ms="performSearch($event.target.value)"
                            x-on:focus="showResults()"
                            placeholder="Search products…"
                            class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            autocomplete="off"
                            data-search-endpoint="{{ route('api.shop.search') }}"
                        >
                        <div wire:ignore x-show="open" x-cloak class="absolute left-0 right-0 top-full z-10 mt-1 max-h-64 overflow-y-auto rounded border border-slate-300 bg-white shadow-lg">
                            <template x-for="result in results" :key="result.id">
                                <a :href="result.url" class="group flex gap-3 border-b border-slate-100 p-2 text-sm hover:bg-slate-50">
                                    <template x-if="result.image">
                                        <div class="relative h-10 w-10 overflow-hidden rounded">
                                            <img :src="result.image" :alt="result.name" class="h-10 w-10 object-cover">
                                        </div>
                                    </template>
                                    <template x-if="!result.image">
                                        <div class="h-10 w-10 rounded bg-slate-100"></div>
                                    </template>
                                    <div class="flex-1">
                                        <div class="font-medium text-slate-900" x-text="result.name"></div>
                                        <div class="text-xs text-slate-600" x-text="result.category || 'Uncategorized'"></div>
                                        <template x-if="result.price !== null">
                                            <div class="text-xs font-medium text-slate-800" x-text="'€' + Number(result.price).toFixed(2)"></div>
                                        </template>
                                    </div>
                                </a>
                            </template>
                            <template x-if="results.length === 0 && noResults">
                                <div class="p-3 text-center text-xs text-slate-600">No products found</div>
                            </template>
                        </div>
                    </div>

                    {{-- Artist / Brand --}}
                    @if ($shopTags->isNotEmpty())
                        <div
                            class="relative"
                            x-data="tagCombobox()"
                            x-on:click.outside="open = false"
                            wire:ignore
                        >
                            <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $shopTags->contains('type', 'brand') ? 'Artist / Brand' : 'Artist' }}</p>
                            <input
                                type="text"
                                x-model="query"
                                x-on:input="filter()"
                                x-on:focus="showAll()"
                                placeholder="Search {{ $shopTags->contains('type', 'brand') ? 'artists & brands' : 'artists' }}…"
                                class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                                autocomplete="off"
                            >
                            <div x-show="open" x-cloak class="absolute left-0 right-0 top-full z-10 mt-1 max-h-64 overflow-y-auto rounded border border-slate-300 bg-white shadow-lg">
                                <template x-for="group in filtered" :key="group.type">
                                    <div>
                                        <div class="sticky top-0 border-b border-slate-100 bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-500" x-text="group.type"></div>
                                        <template x-for="tag in group.tags" :key="tag.slug">
                                            <a
                                                :href="tag.url"
                                                class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                                                x-text="tag.name"
                                            ></a>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="filtered.length === 0">
                                    <div class="p-3 text-center text-xs text-slate-600">No matches found</div>
                                </template>
                            </div>
                        </div>
                    @endif

                    {{-- Shop for --}}
                    @if ($sizeProfiles->isNotEmpty())
                        <div>
                            <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Shop for</p>
                            <select wire:model.live="sizeProfileId" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                <option value="0">All sizes</option>
                                @foreach ($sizeProfiles as $profile)
                                    <option value="{{ $profile->id }}">
                                        {{ $profile->is_self ? 'My sizes' : $profile->name }}
                                        ({{ implode(', ', $profile->allSizes()) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Price --}}
                    @if ($priceCeiling > 0 && $priceFloor < $priceCeiling)
                        <div
                            x-data="priceRangeSlider({
                                floor: {{ (int) $priceFloor }},
                                ceiling: {{ (int) $priceCeiling }},
                                initialMin: {{ $minPrice !== null && $minPrice !== '' ? (int) $minPrice : (int) $priceFloor }},
                                initialMax: {{ $maxPrice !== null && $maxPrice !== '' ? (int) $maxPrice : (int) $priceCeiling }},
                            })"
                        >
                            <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Price</p>
                            <div class="flex items-center gap-2">
                                <span class="text-xs tabular-nums text-slate-600" x-text="'€' + rangeMin"></span>
                                <div class="relative h-6 flex-1">
                                    <div class="absolute inset-x-0 top-1/2 h-1 -translate-y-1/2 rounded bg-slate-200"></div>
                                    <div class="absolute top-1/2 h-1 -translate-y-1/2 rounded bg-slate-700" :style="'left:' + minPercent + '%;right:' + (100 - maxPercent) + '%'"></div>
                                    <input type="range" :min="floor" :max="ceiling" x-model.number="rangeMin" x-on:change="commitMin()" class="pointer-events-none absolute inset-0 z-[2] h-full w-full cursor-pointer appearance-none bg-transparent [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:cursor-pointer [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-slate-700 [&::-moz-range-thumb]:bg-white [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-slate-700 [&::-webkit-slider-thumb]:bg-white">
                                    <input type="range" :min="floor" :max="ceiling" x-model.number="rangeMax" x-on:change="commitMax()" class="pointer-events-none absolute inset-0 z-[3] h-full w-full cursor-pointer appearance-none bg-transparent [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:cursor-pointer [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-slate-700 [&::-moz-range-thumb]:bg-white [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-slate-700 [&::-webkit-slider-thumb]:bg-white">
                                </div>
                                <span class="text-xs tabular-nums text-slate-600" x-text="'€' + rangeMax"></span>
                            </div>
                        </div>
                    @endif

                    {{-- Shoe size --}}
                    @if ($availableShoeSizes !== [])
                        <div>
                            <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Shoe size</p>
                            @php
                                $euToUs = [
                                    '36' => '4', '37' => '5', '38' => '5.5', '39' => '6.5',
                                    '40' => '7.5', '41' => '8', '42' => '9',
                                    '43' => '9.5', '44' => '10', '45' => '11.5', '46' => '12.5',
                                ];
                            @endphp
                            <select wire:model.live="selectedShoeSize" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                <option value="">All sizes</option>
                                @foreach ($availableShoeSizes as $eu)
                                    <option value="{{ $eu }}">EU {{ $eu }} / US {{ $euToUs[$eu] ?? $eu }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Clothing size --}}
                    @if ($availableSizes !== [])
                        <div>
                            <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Size</p>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($availableSizes as $size)
                                    <button
                                        type="button"
                                        wire:click="toggleSize('{{ $size }}')"
                                        wire:key="size-{{ $size }}"
                                        class="rounded border px-2 py-1 text-xs transition {{ in_array($size, $selectedSizes, true) ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-slate-500' }}"
                                    >
                                        {{ $size }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Out-of-stock --}}
                    @if ($activeCategory || $activeTag)
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" wire:model.live="includeOutOfStock" class="rounded border-slate-300">
                            Show out-of-stock
                        </label>
                    @endif

                    {{-- Reset --}}
                    <button wire:click="resetFilters" type="button" class="w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        Reset filters
                    </button>
                </div>
            </aside>

            {{-- Products column --}}
            <div class="min-w-0 flex-1">
                {{-- Sort bar --}}
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button
                            wire:click="$set('sort', 'newest')"
                            type="button"
                            @class([
                                'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition',
                                'border-slate-800 bg-slate-800 text-white' => $sort === 'newest',
                                'border-slate-300 bg-white text-slate-600 hover:border-slate-400 hover:text-slate-800' => $sort !== 'newest',
                            ])
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                            Newest
                        </button>

                        <button
                            wire:click="$set('sort', 'name_asc')"
                            type="button"
                            @class([
                                'inline-flex items-center gap-1 rounded-full border px-3 py-1.5 text-sm font-medium transition',
                                'border-slate-800 bg-slate-800 text-white' => $sort === 'name_asc',
                                'border-slate-300 bg-white text-slate-600 hover:border-slate-400 hover:text-slate-800' => $sort !== 'name_asc',
                            ])
                        >
                            A – Z
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75 12 3m0 0 3.75 3.75M12 3v18"/></svg>
                        </button>

                        <button
                            wire:click="$set('sort', 'name_desc')"
                            type="button"
                            @class([
                                'inline-flex items-center gap-1 rounded-full border px-3 py-1.5 text-sm font-medium transition',
                                'border-slate-800 bg-slate-800 text-white' => $sort === 'name_desc',
                                'border-slate-300 bg-white text-slate-600 hover:border-slate-400 hover:text-slate-800' => $sort !== 'name_desc',
                            ])
                        >
                            Z – A
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25 12 21m0 0-3.75-3.75M12 21V3"/></svg>
                        </button>

                        <button
                            wire:click="$set('sort', 'price_asc')"
                            type="button"
                            @class([
                                'inline-flex items-center gap-1 rounded-full border px-3 py-1.5 text-sm font-medium transition',
                                'border-slate-800 bg-slate-800 text-white' => $sort === 'price_asc',
                                'border-slate-300 bg-white text-slate-600 hover:border-slate-400 hover:text-slate-800' => $sort !== 'price_asc',
                            ])
                        >
                            Price
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75 12 3m0 0 3.75 3.75M12 3v18"/></svg>
                        </button>

                        <button
                            wire:click="$set('sort', 'price_desc')"
                            type="button"
                            @class([
                                'inline-flex items-center gap-1 rounded-full border px-3 py-1.5 text-sm font-medium transition',
                                'border-slate-800 bg-slate-800 text-white' => $sort === 'price_desc',
                                'border-slate-300 bg-white text-slate-600 hover:border-slate-400 hover:text-slate-800' => $sort !== 'price_desc',
                            ])
                        >
                            Price
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25 12 21m0 0-3.75-3.75M12 21V3"/></svg>
                        </button>
                    </div>

                    <div>
                        <span class="text-sm text-slate-500" wire:loading.remove>{{ $products->total() }} product(s)</span>
                        <span class="text-sm text-slate-400" wire:loading>Updating…</span>
                    </div>
                </div>
    @endif

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
                    $maxPrice2 = $catalogVariants->max('price');
                    $hasPriceRange = $startingPrice !== null && $maxPrice2 !== null && (float) $startingPrice !== (float) $maxPrice2;
                    $hasGroups = !empty($selectorData['groups']);
                    $hasMultipleVariants = $catalogVariants->count() > 1;

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
                    wire:key="product-{{ $product->id }}"
                    class="group/card relative flex flex-col rounded border border-slate-200 bg-white p-4 shadow-sm"
                    data-catalog-card
                    @if ($hasGroups)
                        data-catalog-attribute-order="{{ implode(',', $selectorData['attributeOrder']) }}"
                        data-catalog-variant-media='@json($variantMediaMap)'
                    @endif
                    @php
                        $cardPreselectSizes = $preselectSizes;

                        // Skip preselection when user has selected multiple sizes in the filter
                        if (count($selectedSizes) > 1) {
                            $cardPreselectSizes = [];
                        } else {
                            // Add shoe size filter → Biggie/Smalls label
                            if ($selectedShoeSize !== '') {
                                $shoeNum = (int) $selectedShoeSize;
                                if ($shoeNum >= 36 && $shoeNum <= 42) {
                                    $cardPreselectSizes[] = 'Smalls';
                                } elseif ($shoeNum >= 43 && $shoeNum <= 46) {
                                    $cardPreselectSizes[] = 'Biggie';
                                }
                            }

                            // Add clothing size filter selections
                            $cardPreselectSizes = array_values(array_unique(array_merge($cardPreselectSizes, $selectedSizes)));
                        }
                    @endphp
                    @if ($cardPreselectSizes !== [])
                        data-catalog-preselect-sizes='@json($cardPreselectSizes)'
                    @endif
                >
                    <a href="{{ route('shop.show', $product) }}" class="group block flex-1">
                        @if ($mediaUrl)
                            <div class="relative mb-3 h-52 w-full overflow-hidden rounded bg-white">
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
                            <div class="mb-3 h-52 w-full overflow-hidden rounded bg-white">
                                <img src="{{ asset('images/placeholder-product.svg') }}" alt="No image available" class="h-52 w-full object-contain" data-catalog-primary-image>
                            </div>
                        @endif
                        <h2 class="text-lg font-semibold">{{ $product->name }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $product->primaryCategory?->name ?? 'Uncategorized' }}</p>
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
                        <div class="mt-auto pt-3" data-catalog-hover-section>
                            <div class="space-y-2" data-catalog-options>
                                @foreach ($selectorData['groups'] as $attributeKey => $attributeGroup)
                                    <div>
                                        <p class="mb-1 text-xs font-medium text-slate-500">{{ $attributeGroup['label'] }}</p>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($attributeGroup['values'] as $attributeValue)
                                                @php
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

    @if ($activeCategory || $activeTag)
            </div>
        </div>
    @endif

    @script
    <script>
        // Re-initialize catalog card interactions after Livewire updates the DOM
        Livewire.hook('morph.updated', ({ component }) => {
            if (typeof window.initCatalogCards === 'function') {
                setTimeout(() => window.initCatalogCards(), 50);
            }
        });

        // Searchable tag combobox
        Alpine.data('tagCombobox', () => ({
            query: '',
            open: false,
            allTags: @js($shopTags->groupBy('type')->map(fn ($tags, $type) => [
                'type' => ucfirst($type),
                'tags' => $tags->map(fn ($tag) => [
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'url' => match ($tag->type) {
                        'artist' => route('shop.artist', $tag->slug),
                        'brand' => route('shop.brand', $tag->slug),
                        default => route('shop.tag', $tag->slug),
                    },
                ])->values(),
            ])->values()),
            filtered: [],

            init() {
                this.filtered = this.allTags;
            },

            showAll() {
                this.filtered = this.allTags;
                this.open = true;
            },

            filter() {
                const q = this.query.toLowerCase().trim();
                if (q === '') {
                    this.filtered = this.allTags;
                    this.open = true;
                    return;
                }

                this.filtered = this.allTags
                    .map(group => ({
                        type: group.type,
                        tags: group.tags.filter(tag => tag.name.toLowerCase().includes(q)),
                    }))
                    .filter(group => group.tags.length > 0);

                this.open = true;
            },
        }));

        // Dual-thumb price range slider
        Alpine.data('priceRangeSlider', (config) => ({
            floor: config.floor,
            ceiling: config.ceiling,
            rangeMin: config.initialMin,
            rangeMax: config.initialMax,

            get minPercent() {
                if (this.ceiling === this.floor) return 0;
                return ((this.rangeMin - this.floor) / (this.ceiling - this.floor)) * 100;
            },

            get maxPercent() {
                if (this.ceiling === this.floor) return 100;
                return ((this.rangeMax - this.floor) / (this.ceiling - this.floor)) * 100;
            },

            commitMin() {
                if (this.rangeMin > this.rangeMax) {
                    this.rangeMin = this.rangeMax;
                }
                let value = this.rangeMin <= this.floor ? null : String(this.rangeMin);
                $wire.set('minPrice', value);
            },

            commitMax() {
                if (this.rangeMax < this.rangeMin) {
                    this.rangeMax = this.rangeMin;
                }
                let value = this.rangeMax >= this.ceiling ? null : String(this.rangeMax);
                $wire.set('maxPrice', value);
            },
        }));
    </script>
    @endscript
</div>