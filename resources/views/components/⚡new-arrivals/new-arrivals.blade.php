<div class="bg-black px-6 py-16 lg:py-20">
    <div class="mx-auto max-w-6xl">
        <div class="mb-10 text-center">
            <h2 class="text-3xl font-black uppercase tracking-tight text-white md:text-4xl">New Arrivals</h2>
            <p class="mt-3 text-base text-slate-400">Fresh drops from the GoonsGear collection</p>
        </div>

        @if ($products->isEmpty())
            <p class="text-center text-sm text-slate-500">No products yet.</p>
        @else
            <div
                class="relative px-0 md:px-12"
                x-data="{
                    isDragging: false,
                    startX: 0,
                    startY: 0,
                    scrollLeft: 0,
                    direction: null,
                    canScrollLeft: false,
                    canScrollRight: true,
                    start(e) {
                        this.isDragging = true;
                        this.direction = null;
                        const point = e.touches ? e.touches[0] : e;
                        this.startX = point.pageX - this.$refs.track.offsetLeft;
                        this.startY = point.pageY;
                        this.scrollLeft = this.$refs.track.scrollLeft;
                        this.$refs.track.style.cursor = 'grabbing';
                    },
                    move(e) {
                        if (!this.isDragging) return;
                        const point = e.touches ? e.touches[0] : e;
                        const x = point.pageX - this.$refs.track.offsetLeft;
                        const diffX = Math.abs(x - this.startX);
                        const diffY = Math.abs(point.pageY - this.startY);
                        if (!this.direction) {
                            if (diffX < 3 && diffY < 3) return;
                            this.direction = diffX > diffY ? 'horizontal' : 'vertical';
                        }
                        if (this.direction === 'vertical') { this.isDragging = false; return; }
                        e.preventDefault();
                        const walk = (x - this.startX) * 1.5;
                        this.$refs.track.scrollLeft = this.scrollLeft - walk;
                    },
                    stop() {
                        this.isDragging = false;
                        this.direction = null;
                        this.$refs.track.style.cursor = 'grab';
                    },
                    scrollByCard(direction) {
                        const card = this.$refs.track.querySelector('article');
                        const step = card ? card.offsetWidth + 24 : 350;
                        this.$refs.track.scrollBy({ left: direction * step, behavior: 'smooth' });
                    },
                    updateArrows() {
                        const el = this.$refs.track;
                        this.canScrollLeft = el.scrollLeft > 0;
                        this.canScrollRight = el.scrollLeft < el.scrollWidth - el.clientWidth - 1;
                    }
                }"
                x-init="$nextTick(() => updateArrows())"
            >
                {{-- Left arrow --}}
                <button
                    x-show="canScrollLeft"
                    x-on:click="scrollByCard(-1)"
                    type="button"
                    aria-label="Scroll left"
                    class="absolute -left-2 top-1/2 z-10 hidden -translate-y-1/2 items-center justify-center transition-opacity hover:opacity-70 focus:outline-none md:flex"
                >
                    <svg class="h-10 w-10 text-white" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                </button>

                <div
                    x-ref="track"
                    x-on:scroll.debounce.50ms="updateArrows()"
                    x-on:mousedown="start($event)"
                    x-on:mousemove="move($event)"
                    x-on:mouseup="stop()"
                    x-on:mouseleave="stop()"
                    x-on:touchstart.passive="start($event)"
                    x-on:touchmove="move($event)"
                    x-on:touchend="stop()"
                    class="no-scrollbar flex gap-6 overflow-x-auto select-none"
                    style="cursor: grab; -webkit-overflow-scrolling: touch; scroll-behavior: smooth;"
                >
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
                        class="group/card relative flex w-full shrink-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-md sm:w-[calc(50%-0.75rem)] lg:w-[calc(33.333%-1rem)]"
                        data-catalog-card
                        @if ($hasGroups)
                            data-catalog-attribute-order="{{ implode(',', $selectorData['attributeOrder']) }}"
                            data-catalog-variant-media='@json($variantMediaMap)'
                        @endif
                        draggable="false"
                    >
                        <a href="{{ route('shop.show', $product) }}" class="group block flex-1" draggable="false">
                            @if ($mediaUrl)
                                <div class="relative aspect-square w-full overflow-hidden bg-slate-50">
                                    @if (in_array($product->id, $bundleProductIds))
                                        <span class="absolute right-2 top-2 z-10 rounded border border-slate-300 bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-black">Part of a bundle</span>
                                    @endif
                                    <img
                                        src="{{ $mediaUrl }}"
                                        alt="{{ $primaryMedia?->alt_text ?: $product->name }}"
                                        data-catalog-primary-image
                                        data-catalog-original-src="{{ $mediaUrl }}"
                                        class="h-full w-full object-contain p-4 transition-opacity duration-200 {{ $secondaryMediaUrl ? 'group-hover:opacity-0' : '' }}"
                                        draggable="false"
                                    >
                                    @if ($secondaryMediaUrl)
                                        <img src="{{ $secondaryMediaUrl }}" alt="{{ $secondaryMedia?->alt_text ?: $product->name }}" class="pointer-events-none absolute inset-0 h-full w-full object-contain p-4 opacity-0 transition-opacity duration-200 group-hover:opacity-100" draggable="false">
                                    @endif
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent transition-opacity duration-300 group-hover:from-black/70"></div>
                                </div>
                            @else
                                <div class="aspect-square w-full overflow-hidden bg-slate-50">
                                    <img src="{{ asset('images/placeholder-product.svg') }}" alt="No image available" class="h-full w-full object-contain p-4" data-catalog-primary-image draggable="false">
                                </div>
                            @endif

                            <div class="p-4">
                                <p class="text-xs font-medium uppercase tracking-wide text-amber-600">{{ $product->primaryCategory?->name ?? 'Uncategorized' }}</p>
                                <h3 class="mt-1 text-base font-bold leading-snug text-black">{{ $product->name }}</h3>
                                @if ($startingPrice !== null)
                                    <p class="mt-1 text-sm font-semibold text-black" data-catalog-price>
                                        @if ($hasPriceRange)
                                            From &euro;{{ number_format((float) $startingPrice, 2) }}
                                        @else
                                            &euro;{{ number_format((float) $startingPrice, 2) }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </a>

                        @if ($hasGroups && $hasMultipleVariants)
                            <div class="mt-auto border-t border-slate-100 p-4" data-catalog-hover-section>
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
                                                            class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs text-slate-700 transition hover:border-black"
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

                                <form method="POST" action="{{ route('cart.items.store') }}" data-catalog-cart-form class="mt-3">
                                    @csrf
                                    <input type="hidden" name="variant_id" value="" data-catalog-cart-variant-input>
                                    <input type="hidden" name="quantity" value="1">
                                    <button
                                        type="submit"
                                        data-catalog-add-to-cart
                                        class="w-full rounded-lg border-2 border-black bg-black px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-white transition hover:bg-transparent hover:text-black"
                                    >
                                        Select options
                                    </button>
                                </form>
                            </div>
                        @elseif (!$hasMultipleVariants && $catalogVariants->count() === 1)
                            @php $singleVariant = $catalogVariants->first(); @endphp
                            @if (!$singleVariant->is_out_of_stock)
                                <div class="mt-auto border-t border-slate-100 p-4" data-catalog-hover-section>
                                    <form method="POST" action="{{ route('cart.items.store') }}" data-catalog-cart-form>
                                        @csrf
                                        <input type="hidden" name="variant_id" value="{{ $singleVariant->id }}">
                                        <input type="hidden" name="quantity" value="1">
                                        <button
                                            type="submit"
                                            data-catalog-add-to-cart
                                            data-catalog-single-variant
                                            class="w-full rounded-lg border-2 border-black bg-black px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-white transition hover:bg-transparent hover:text-black"
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

                {{-- Right arrow --}}
                <button
                    x-show="canScrollRight"
                    x-on:click="scrollByCard(1)"
                    type="button"
                    aria-label="Scroll right"
                    class="absolute -right-2 top-1/2 z-10 hidden -translate-y-1/2 items-center justify-center transition-opacity hover:opacity-70 focus:outline-none md:flex"
                >
                    <svg class="h-10 w-10 text-white" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </button>
            </div>
        @endif
    </div>
</div>
