<div class="relative overflow-hidden bg-black px-6 py-16 lg:py-20">
    {{-- Snowflake animation --}}
    <style>
        @keyframes snowflake-fall {
            0% { transform: translateY(-40px) translateX(0) rotate(0deg); opacity: 0; }
            5% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(calc(100vh + 40px)) translateX(var(--snow-drift)) rotate(var(--snow-spin)); opacity: 0; }
        }
        @keyframes snowflake-sway {
            0%, 100% { margin-left: 0; }
            25% { margin-left: var(--snow-sway-amount); }
            75% { margin-left: calc(var(--snow-sway-amount) * -1); }
        }
        .snowflake-particle {
            position: absolute;
            top: -40px;
            pointer-events: none;
            z-index: 1;
            animation:
                snowflake-fall var(--snow-duration) var(--snow-delay) linear infinite,
                snowflake-sway var(--snow-sway-speed) var(--snow-delay) ease-in-out infinite;
            will-change: transform, margin-left;
        }
    </style>
    <div class="pointer-events-none absolute inset-0 z-[1]" aria-hidden="true">
        {{-- Left-side flakes (negative delays = already mid-fall on load) --}}
        <img src="{{ asset('images/snowgoons-snowflake.png') }}" alt="" class="snowflake-particle" style="left:4%;width:34px;height:34px;opacity:.18;--snow-duration:12s;--snow-delay:-4s;--snow-drift:20px;--snow-spin:180deg;--snow-sway-amount:14px;--snow-sway-speed:4s;">
        <img src="{{ asset('images/snowgoons-snowflake.png') }}" alt="" class="snowflake-particle" style="left:15%;width:22px;height:22px;opacity:.14;--snow-duration:16s;--snow-delay:-10s;--snow-drift:-15px;--snow-spin:-120deg;--snow-sway-amount:10px;--snow-sway-speed:5s;">
        <img src="{{ asset('images/snowgoons-snowflake.png') }}" alt="" class="snowflake-particle" style="left:33%;width:28px;height:28px;opacity:.10;--snow-duration:14s;--snow-delay:-7s;--snow-drift:25px;--snow-spin:240deg;--snow-sway-amount:12px;--snow-sway-speed:3.5s;">
        <img src="{{ asset('images/snowgoons-snowflake.png') }}" alt="" class="snowflake-particle" style="left:10%;width:18px;height:18px;opacity:.12;--snow-duration:18s;--snow-delay:-2s;--snow-drift:-10px;--snow-spin:90deg;--snow-sway-amount:8px;--snow-sway-speed:6s;">
        <img src="{{ asset('images/snowgoons-snowflake.png') }}" alt="" class="snowflake-particle" style="left:26%;width:40px;height:40px;opacity:.07;--snow-duration:15s;--snow-delay:-12s;--snow-drift:18px;--snow-spin:-200deg;--snow-sway-amount:11px;--snow-sway-speed:4.5s;">
        <img src="{{ asset('images/snowgoons-snowflake.png') }}" alt="" class="snowflake-particle" style="left:8%;width:26px;height:26px;opacity:.11;--snow-duration:13s;--snow-delay:-9s;--snow-drift:14px;--snow-spin:130deg;--snow-sway-amount:9px;--snow-sway-speed:5.2s;">
        <img src="{{ asset('images/snowgoons-snowflake.png') }}" alt="" class="snowflake-particle" style="left:38%;width:20px;height:20px;opacity:.09;--snow-duration:17s;--snow-delay:-5s;--snow-drift:-12px;--snow-spin:-170deg;--snow-sway-amount:7px;--snow-sway-speed:4.8s;">
        <img src="{{ asset('images/snowgoons-snowflake.png') }}" alt="" class="snowflake-particle" style="left:20%;width:36px;height:36px;opacity:.08;--snow-duration:11s;--snow-delay:0s;--snow-drift:22px;--snow-spin:260deg;--snow-sway-amount:13px;--snow-sway-speed:3.6s;">

        {{-- Right-side flakes --}}
        <img src="{{ asset('images/snowgoons-snowflake.png') }}" alt="" class="snowflake-particle" style="right:5%;left:auto;width:30px;height:30px;opacity:.16;--snow-duration:13s;--snow-delay:-6s;--snow-drift:-22px;--snow-spin:160deg;--snow-sway-amount:13px;--snow-sway-speed:4.2s;">
        <img src="{{ asset('images/snowgoons-snowflake.png') }}" alt="" class="snowflake-particle" style="right:18%;left:auto;width:20px;height:20px;opacity:.13;--snow-duration:17s;--snow-delay:-11s;--snow-drift:12px;--snow-spin:-150deg;--snow-sway-amount:9px;--snow-sway-speed:5.5s;">
        <img src="{{ asset('images/snowgoons-snowflake.png') }}" alt="" class="snowflake-particle" style="right:32%;left:auto;width:38px;height:38px;opacity:.08;--snow-duration:11s;--snow-delay:-3s;--snow-drift:-20px;--snow-spin:220deg;--snow-sway-amount:15px;--snow-sway-speed:3.8s;">
        <img src="{{ asset('images/snowgoons-snowflake.png') }}" alt="" class="snowflake-particle" style="right:12%;left:auto;width:24px;height:24px;opacity:.11;--snow-duration:19s;--snow-delay:-14s;--snow-drift:15px;--snow-spin:-100deg;--snow-sway-amount:8px;--snow-sway-speed:6.2s;">
        <img src="{{ asset('images/snowgoons-snowflake.png') }}" alt="" class="snowflake-particle" style="right:3%;left:auto;width:26px;height:26px;opacity:.15;--snow-duration:14s;--snow-delay:-8s;--snow-drift:-18px;--snow-spin:280deg;--snow-sway-amount:12px;--snow-sway-speed:4.8s;">
        <img src="{{ asset('images/snowgoons-snowflake.png') }}" alt="" class="snowflake-particle" style="right:25%;left:auto;width:18px;height:18px;opacity:.10;--snow-duration:16s;--snow-delay:-1s;--snow-drift:16px;--snow-spin:-230deg;--snow-sway-amount:10px;--snow-sway-speed:5.8s;">
        <img src="{{ asset('images/snowgoons-snowflake.png') }}" alt="" class="snowflake-particle" style="right:8%;left:auto;width:34px;height:34px;opacity:.09;--snow-duration:12s;--snow-delay:-10s;--snow-drift:-14px;--snow-spin:190deg;--snow-sway-amount:11px;--snow-sway-speed:4.4s;">
        <img src="{{ asset('images/snowgoons-snowflake.png') }}" alt="" class="snowflake-particle" style="right:36%;left:auto;width:22px;height:22px;opacity:.07;--snow-duration:15s;--snow-delay:-13s;--snow-drift:20px;--snow-spin:-140deg;--snow-sway-amount:14px;--snow-sway-speed:3.4s;">
    </div>

    <div class="relative z-[2] mx-auto max-w-6xl">
        <div class="mb-10 text-center">
            <h2 class="text-3xl font-black uppercase tracking-tight text-white md:text-4xl lg:text-5xl">New Arrivals</h2>
            <p class="mt-3 text-base text-white/50">Fresh drops from the GoonsGear collection</p>
        </div>

        @if ($products->isEmpty())
            <p class="text-center text-sm text-white/40">No products yet.</p>
        @else
            <div
                class="relative px-0 md:px-14"
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
                    class="absolute -left-2 top-1/2 z-10 hidden -translate-y-1/2 items-center justify-center transition-opacity duration-200 hover:opacity-60 focus:outline-none md:flex"
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
                        class="group/card relative flex w-full shrink-0 flex-col overflow-hidden rounded-xl bg-white shadow-lg transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl sm:w-[calc(50%-0.75rem)] lg:w-[calc(33.333%-1rem)]"
                        data-catalog-card
                        @if ($hasGroups)
                            data-catalog-attribute-order="{{ implode(',', $selectorData['attributeOrder']) }}"
                            data-catalog-variant-media='@json($variantMediaMap)'
                        @endif
                        draggable="false"
                    >
                        <a href="{{ route('shop.show', $product) }}" class="group block flex-1" draggable="false">
                            @if ($mediaUrl)
                                <div class="relative aspect-square w-full overflow-hidden bg-white">
                                    @if (in_array($product->id, $bundleProductIds))
                                        <span class="absolute right-3 top-3 z-10 rounded-md bg-black px-2.5 py-1 text-[10px] font-bold uppercase tracking-widest text-white">Bundle</span>
                                    @endif
                                    <img
                                        src="{{ $mediaUrl }}"
                                        alt="{{ $primaryMedia?->alt_text ?: $product->name }}"
                                        data-catalog-primary-image
                                        data-catalog-original-src="{{ $mediaUrl }}"
                                        class="h-full w-full object-contain p-6 transition-all duration-500 {{ $secondaryMediaUrl ? 'group-hover:opacity-0 group-hover:scale-105' : 'group-hover:scale-105' }}"
                                        draggable="false"
                                    >
                                    @if ($secondaryMediaUrl)
                                        <img src="{{ $secondaryMediaUrl }}" alt="{{ $secondaryMedia?->alt_text ?: $product->name }}" class="pointer-events-none absolute inset-0 h-full w-full object-contain p-6 opacity-0 transition-all duration-500 group-hover:scale-105 group-hover:opacity-100" draggable="false">
                                    @endif
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent transition-opacity duration-300 group-hover:from-black/50"></div>
                                </div>
                            @else
                                <div class="aspect-square w-full overflow-hidden bg-white">
                                    <img src="{{ asset('images/placeholder-product.svg') }}" alt="No image available" class="h-full w-full object-contain p-6" data-catalog-primary-image draggable="false">
                                </div>
                            @endif

                            <div class="p-5">
                                <p class="text-xs font-bold uppercase tracking-widest text-amber-500">{{ $product->primaryCategory?->name ?? 'Uncategorized' }}</p>
                                <h3 class="mt-2 text-lg font-black leading-tight text-black">{{ $product->name }}</h3>
                                @if ($startingPrice !== null)
                                    <p class="mt-2 text-base font-bold text-black" data-catalog-price>
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
                            <div class="mt-auto border-t border-slate-200 p-5" data-catalog-hover-section>
                                <div class="space-y-3" data-catalog-options>
                                    @foreach ($selectorData['groups'] as $attributeKey => $attributeGroup)
                                        <div>
                                            <p class="mb-1.5 text-xs font-bold uppercase tracking-wider text-slate-400">{{ $attributeGroup['label'] }}</p>
                                            <div class="flex flex-wrap gap-1.5">
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
                                                            class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition-all duration-200 hover:border-black hover:bg-black hover:text-white"
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

                                <form method="POST" action="{{ route('cart.items.store') }}" data-catalog-cart-form class="mt-4">
                                    @csrf
                                    <input type="hidden" name="variant_id" value="" data-catalog-cart-variant-input>
                                    <input type="hidden" name="quantity" value="1">
                                    <button
                                        type="submit"
                                        data-catalog-add-to-cart
                                        class="w-full rounded-lg border-2 border-black bg-black px-4 py-3 text-xs font-bold uppercase tracking-widest text-white transition-all duration-200 hover:bg-white hover:text-black"
                                    >
                                        Select options
                                    </button>
                                </form>
                            </div>
                        @elseif (!$hasMultipleVariants && $catalogVariants->count() === 1)
                            @php $singleVariant = $catalogVariants->first(); @endphp
                            @if (!$singleVariant->is_out_of_stock)
                                <div class="mt-auto border-t border-slate-200 p-5" data-catalog-hover-section>
                                    <form method="POST" action="{{ route('cart.items.store') }}" data-catalog-cart-form>
                                        @csrf
                                        <input type="hidden" name="variant_id" value="{{ $singleVariant->id }}">
                                        <input type="hidden" name="quantity" value="1">
                                        <button
                                            type="submit"
                                            data-catalog-add-to-cart
                                            data-catalog-single-variant
                                            class="w-full rounded-lg border-2 border-black bg-black px-4 py-3 text-xs font-bold uppercase tracking-widest text-white transition-all duration-200 hover:bg-white hover:text-black"
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
                    class="absolute -right-2 top-1/2 z-10 hidden -translate-y-1/2 items-center justify-center transition-opacity duration-200 hover:opacity-60 focus:outline-none md:flex"
                >
                    <svg class="h-10 w-10 text-white" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </button>
            </div>
        @endif
    </div>
</div>
