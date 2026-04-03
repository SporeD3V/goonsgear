<div>
    {{-- Loading overlay --}}
    <div wire:loading.delay class="pointer-events-none fixed inset-x-0 top-0 z-50 h-1 animate-pulse bg-slate-700"></div>

    {{-- Filter bar --}}
    <div class="mb-5 space-y-3 rounded border border-slate-200 bg-white p-4">
        {{-- Row 1: Search + Sort --}}
        <div class="flex flex-wrap items-end gap-3">
            {{-- Search --}}
            <div class="relative min-w-0 flex-1" x-data="searchAutocomplete()" x-on:click.outside="open = false">
                <label class="mb-1 block text-xs font-medium text-slate-700">Search</label>
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

            {{-- Sort --}}
            <div class="w-40 shrink-0">
                <label class="mb-1 block text-xs font-medium text-slate-700">Sort by</label>
                <select wire:model.live="sort" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    <option value="newest">Newest</option>
                    <option value="name_asc">Name A–Z</option>
                    <option value="name_desc">Name Z–A</option>
                    <option value="price_asc">Price low–high</option>
                    <option value="price_desc">Price high–low</option>
                </select>
            </div>
        </div>

        {{-- Row 2: Tag filter + Size profile + Reset --}}
        <div class="flex flex-wrap items-end gap-3">
            @if ($shopTags->isNotEmpty())
                {{-- Tag search combobox --}}
                <div
                    class="relative min-w-[200px] flex-1"
                    x-data="tagCombobox()"
                    x-on:click.outside="open = false"
                    wire:ignore
                >
                    <label class="mb-1 block text-xs font-medium text-slate-700">Artist / Brand</label>
                    <input
                        type="text"
                        x-model="query"
                        x-on:input="filter()"
                        x-on:focus="showAll()"
                        placeholder="Search artists & brands…"
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

            @if ($sizeProfiles->isNotEmpty())
                {{-- Shop for (size profiles) --}}
                <div class="w-40 shrink-0">
                    <label class="mb-1 block text-xs font-medium text-slate-700">Shop for</label>
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

            {{-- Reset --}}
            <button wire:click="resetFilters" type="button" class="shrink-0 rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Reset</button>
        </div>

        {{-- Row 2: Price range slider + Out-of-stock toggle --}}
        @if ($priceCeiling > 0 && $priceFloor < $priceCeiling)
            <div
                x-data="priceRangeSlider({
                    floor: {{ (int) $priceFloor }},
                    ceiling: {{ (int) $priceCeiling }},
                    initialMin: {{ $minPrice !== null && $minPrice !== '' ? (int) $minPrice : (int) $priceFloor }},
                    initialMax: {{ $maxPrice !== null && $maxPrice !== '' ? (int) $maxPrice : (int) $priceCeiling }},
                })"
                class="flex flex-wrap items-center gap-4"
            >
                <label class="text-xs font-medium text-slate-700">Price</label>
                <span class="text-xs tabular-nums text-slate-600" x-text="'€' + rangeMin"></span>

                <div class="relative h-6 min-w-[200px] flex-1">
                    {{-- Track background --}}
                    <div class="absolute inset-x-0 top-1/2 h-1 -translate-y-1/2 rounded bg-slate-200"></div>
                    {{-- Active range highlight --}}
                    <div
                        class="absolute top-1/2 h-1 -translate-y-1/2 rounded bg-slate-700"
                        :style="'left:' + minPercent + '%;right:' + (100 - maxPercent) + '%'"
                    ></div>
                    {{-- Min thumb --}}
                    <input
                        type="range"
                        :min="floor"
                        :max="ceiling"
                        x-model.number="rangeMin"
                        x-on:change="commitMin()"
                        class="pointer-events-none absolute inset-0 z-20 h-full w-full cursor-pointer appearance-none bg-transparent [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:cursor-pointer [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-slate-700 [&::-moz-range-thumb]:bg-white [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-slate-700 [&::-webkit-slider-thumb]:bg-white"
                    >
                    {{-- Max thumb --}}
                    <input
                        type="range"
                        :min="floor"
                        :max="ceiling"
                        x-model.number="rangeMax"
                        x-on:change="commitMax()"
                        class="pointer-events-none absolute inset-0 z-30 h-full w-full cursor-pointer appearance-none bg-transparent [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:cursor-pointer [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-slate-700 [&::-moz-range-thumb]:bg-white [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-slate-700 [&::-webkit-slider-thumb]:bg-white"
                    >
                </div>

                <span class="text-xs tabular-nums text-slate-600" x-text="'€' + rangeMax"></span>
            </div>
        @endif

        {{-- Row 3: Size filter + Out-of-stock --}}
        <div class="flex flex-wrap items-start gap-4">
            @if ($availableSizes !== [])
                <div>
                    <p class="mb-1 text-xs font-medium text-slate-700">Filter by size</p>
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

            @if ($activeCategory)
                <label class="inline-flex items-center gap-2 pt-5 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        wire:model.live="includeOutOfStock"
                        class="rounded border-slate-300"
                    >
                    Show out-of-stock
                </label>
            @endif
        </div>
    </div>

    <p class="mb-4 text-sm text-slate-600" wire:loading.remove>Showing {{ $products->total() }} product(s).</p>
    <p class="mb-4 text-sm text-slate-400" wire:loading>Updating results…</p>

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
                    'url' => route('shop.tag', $tag->slug),
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