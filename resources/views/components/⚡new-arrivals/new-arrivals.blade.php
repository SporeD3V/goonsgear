<div class="border-b border-slate-200 bg-white px-6 py-10">
    <div class="mx-auto max-w-6xl">
        <h2 class="mb-6 text-xl font-bold uppercase tracking-wide text-slate-900">New Arrivals</h2>

        @if ($products->isEmpty())
            <p class="text-sm text-slate-400">No products yet.</p>
        @else
            <div
                x-data="{
                    isDragging: false,
                    startX: 0,
                    scrollLeft: 0,
                    start(e) {
                        this.isDragging = true;
                        this.startX = (e.touches ? e.touches[0].pageX : e.pageX) - this.$el.offsetLeft;
                        this.scrollLeft = this.$el.scrollLeft;
                        this.$el.style.cursor = 'grabbing';
                    },
                    move(e) {
                        if (!this.isDragging) return;
                        e.preventDefault();
                        const x = (e.touches ? e.touches[0].pageX : e.pageX) - this.$el.offsetLeft;
                        const walk = (x - this.startX) * 1.5;
                        this.$el.scrollLeft = this.scrollLeft - walk;
                    },
                    stop() {
                        this.isDragging = false;
                        this.$el.style.cursor = 'grab';
                    }
                }"
                x-on:mousedown="start($event)"
                x-on:mousemove="move($event)"
                x-on:mouseup="stop()"
                x-on:mouseleave="stop()"
                x-on:touchstart.passive="start($event)"
                x-on:touchmove="move($event)"
                x-on:touchend="stop()"
                class="flex gap-4 overflow-x-auto pb-2 select-none scrollbar-hide"
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
                        class="group/card relative flex w-56 shrink-0 flex-col rounded border border-slate-200 bg-white p-4 shadow-sm"
                        data-catalog-card
                        @if ($hasGroups)
                            data-catalog-attribute-order="{{ implode(',', $selectorData['attributeOrder']) }}"
                            data-catalog-variant-media='@json($variantMediaMap)'
                        @endif
                        draggable="false"
                    >
                        <a href="{{ route('shop.show', $product) }}" class="group block flex-1" draggable="false">
                            @if ($mediaUrl)
                                <div class="relative mb-3 h-44 w-full overflow-hidden rounded bg-white">
                                    <img
                                        src="{{ $mediaUrl }}"
                                        alt="{{ $primaryMedia?->alt_text ?: $product->name }}"
                                        data-catalog-primary-image
                                        data-catalog-original-src="{{ $mediaUrl }}"
                                        class="h-44 w-full object-contain transition-opacity duration-200 {{ $secondaryMediaUrl ? 'group-hover:opacity-0' : '' }}"
                                        draggable="false"
                                    >
                                    @if ($secondaryMediaUrl)
                                        <img src="{{ $secondaryMediaUrl }}" alt="{{ $secondaryMedia?->alt_text ?: $product->name }}" class="pointer-events-none absolute inset-0 h-44 w-full object-contain opacity-0 transition-opacity duration-200 group-hover:opacity-100" draggable="false">
                                    @endif
                                </div>
                            @else
                                <div class="mb-3 h-44 w-full overflow-hidden rounded bg-white">
                                    <img src="{{ asset('images/placeholder-product.svg') }}" alt="No image available" class="h-44 w-full object-contain" data-catalog-primary-image draggable="false">
                                </div>
                            @endif
                            <h2 class="text-sm font-semibold leading-snug">{{ $product->name }}</h2>
                            <p class="mt-1 text-xs text-slate-600">{{ $product->primaryCategory?->name ?? 'Uncategorized' }}</p>
                            @if ($startingPrice !== null)
                                <p class="mt-1 text-xs font-medium text-slate-800" data-catalog-price>
                                    @if ($hasPriceRange)
                                        From &euro;{{ number_format((float) $startingPrice, 2) }}
                                    @else
                                        &euro;{{ number_format((float) $startingPrice, 2) }}
                                    @endif
                                </p>
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
                                        class="w-full rounded bg-slate-800 px-3 py-2 text-xs font-medium text-white transition hover:bg-slate-900"
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
                                            class="w-full rounded bg-slate-800 px-3 py-2 text-xs font-medium text-white transition hover:bg-slate-900"
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
        @endif
    </div>
</div>
