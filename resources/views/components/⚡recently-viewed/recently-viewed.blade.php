<div
    x-data="{
        init() {
            const key = 'recently_viewed';
            const currentId = {{ $currentProductId }};
            let ids = [];

            try {
                ids = JSON.parse(localStorage.getItem(key) || '[]');
            } catch (e) {
                ids = [];
            }

            ids = [currentId, ...ids.filter(id => id !== currentId)].slice(0, 20);
            localStorage.setItem(key, JSON.stringify(ids));

            const others = ids.filter(id => id !== currentId);
            if (others.length > 0) {
                $wire.loadProducts(others);
            }
        }
    }"
>
    @if ($products->isNotEmpty())
        <section class="border-t border-black/10 bg-neutral-50 px-6 py-16 lg:py-20">
            <div class="mx-auto max-w-6xl">
                <h2 class="text-center text-3xl font-black uppercase tracking-wide text-black md:text-4xl">
                    Recently Viewed
                </h2>
                <p class="mt-3 text-center text-base text-black/50">
                    Products you checked out earlier
                </p>

                <div
                    class="relative mt-10 px-0 md:px-14"
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
                            const card = this.$refs.track.querySelector('a');
                            const step = card ? card.offsetWidth + 24 : 300;
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
                        <svg class="h-10 w-10 text-black" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
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
                        class="no-scrollbar flex snap-x snap-mandatory gap-6 overflow-x-auto py-2 select-none"
                        style="cursor: grab; -webkit-overflow-scrolling: touch; scroll-behavior: smooth;"
                    >
                        @foreach ($products as $product)
                            @php
                                $primaryMedia = $product->media->first();
                                $mediaUrl = $primaryMedia
                                    ? route('media.show', ['path' => $primaryMedia->catalog_path ?? $primaryMedia->path])
                                    : null;
                                $startingPrice = $product->min_active_variant_price;
                            @endphp
                            <a
                                href="{{ route('shop.show', $product) }}"
                                class="group flex w-full shrink-0 snap-start flex-col overflow-hidden rounded-xl bg-white shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl sm:w-[calc(50%-0.75rem)] lg:w-[calc(25%-1.125rem)]"
                                wire:key="rv-{{ $product->id }}"
                                draggable="false"
                            >
                                <div class="relative aspect-square w-full overflow-hidden bg-white">
                                    @if ($mediaUrl)
                                        <img
                                            src="{{ $mediaUrl }}"
                                            alt="{{ $primaryMedia?->alt_text ?: $product->name }}"
                                            class="h-full w-full object-contain p-6 transition-transform duration-500 group-hover:scale-105"
                                            loading="lazy"
                                            draggable="false"
                                        >
                                    @else
                                        <img
                                            src="{{ asset('images/placeholder-product.svg') }}"
                                            alt="No image available"
                                            class="h-full w-full object-contain p-6"
                                            loading="lazy"
                                            draggable="false"
                                        >
                                    @endif
                                </div>

                                <div class="flex flex-1 flex-col p-4">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-black/40">
                                        {{ $product->primaryCategory?->name ?? 'Uncategorized' }}
                                    </p>
                                    <h3 class="mt-1.5 line-clamp-2 text-sm font-bold leading-snug text-black">
                                        {{ $product->name }}
                                    </h3>
                                    @if ($startingPrice !== null)
                                        <p class="mt-auto pt-3 text-sm font-black text-black">
                                            &euro;{{ number_format((float) $startingPrice, 2) }}
                                        </p>
                                    @endif
                                </div>
                            </a>
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
                        <svg class="h-10 w-10 text-black" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    </button>
                </div>
            </div>
        </section>
    @endif
</div>
