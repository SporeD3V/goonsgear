<div class="bg-white px-6 py-16 lg:py-20">
    <div class="mx-auto max-w-6xl">
        <div class="mb-10 text-center">
            <h2 class="text-3xl font-black uppercase tracking-tight text-black md:text-4xl">Bundle Up &amp; Save</h2>
            <p class="mt-3 text-base text-slate-500">Get more for less with our exclusive product bundles</p>
        </div>

        @if ($bundles->isEmpty())
            <p class="text-center text-sm text-slate-400">No bundles available right now.</p>
        @else
            <div
                class="relative px-0 md:px-12"
                x-data="{
                    isDragging: false,
                    startX: 0,
                    scrollLeft: 0,
                    canScrollLeft: false,
                    canScrollRight: true,
                    start(e) {
                        this.isDragging = true;
                        this.startX = (e.touches ? e.touches[0].pageX : e.pageX) - this.$refs.track.offsetLeft;
                        this.scrollLeft = this.$refs.track.scrollLeft;
                        this.$refs.track.style.cursor = 'grabbing';
                    },
                    move(e) {
                        if (!this.isDragging) return;
                        e.preventDefault();
                        const x = (e.touches ? e.touches[0].pageX : e.pageX) - this.$refs.track.offsetLeft;
                        const walk = (x - this.startX) * 1.5;
                        this.$refs.track.scrollLeft = this.scrollLeft - walk;
                    },
                    stop() {
                        this.isDragging = false;
                        this.$refs.track.style.cursor = 'grab';
                    },
                    scrollByBundle(direction) {
                        const bundle = this.$refs.track.querySelector('.bundle-slide');
                        const step = bundle ? bundle.offsetWidth + 24 : 600;
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
                    x-on:click="scrollByBundle(-1)"
                    type="button"
                    aria-label="Scroll left"
                    class="absolute left-0 top-1/2 z-10 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-slate-300 bg-white shadow-lg transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400 md:flex"
                >
                    <svg class="h-5 w-5 text-slate-800" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
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
                    @foreach ($bundles as $bundle)
                        <div class="bundle-slide w-full shrink-0" draggable="false">
                            <div class="flex flex-col gap-4 md:flex-row md:items-stretch md:gap-0">
                                {{-- Product cards --}}
                                @foreach ($bundle->items as $index => $item)
                                    @if ($index > 0)
                                        {{-- Plus sign between products --}}
                                        <div class="flex shrink-0 items-center justify-center py-2 md:px-3 md:py-0">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-slate-300 bg-white md:h-12 md:w-12">
                                                <svg class="h-5 w-5 text-black md:h-6 md:w-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="flex min-w-0 flex-1 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" draggable="false">
                                        <a href="{{ route('shop.show', $item->variant->product) }}" class="group block flex-1" draggable="false">
                                            <div class="relative aspect-square w-full overflow-hidden bg-slate-50">
                                                <span class="absolute right-2 top-2 z-10 rounded border border-slate-300 bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-black">Part of a bundle</span>
                                                <img
                                                    src="{{ $item->media_url }}"
                                                    alt="{{ $item->variant->product->name }}"
                                                    class="h-full w-full object-contain p-4"
                                                    draggable="false"
                                                >
                                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent transition-opacity duration-300 group-hover:from-black/70"></div>
                                            </div>
                                            <div class="p-4">
                                                <h3 class="text-sm font-bold leading-snug text-black">{{ $item->variant->product->name }}</h3>
                                                <p class="mt-1 text-xs text-slate-500">{{ $item->variant->product->primaryCategory?->name ?? 'Uncategorized' }}</p>
                                                <p class="mt-1 text-sm font-semibold text-black">&euro;{{ number_format((float) $item->variant->price, 2) }}</p>
                                            </div>
                                        </a>
                                    </div>
                                @endforeach

                                {{-- Equals sign --}}
                                <div class="flex shrink-0 items-center justify-center py-2 md:px-3 md:py-0">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-slate-300 bg-white md:h-12 md:w-12">
                                        <svg class="h-5 w-5 text-black md:h-6 md:w-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5"/></svg>
                                    </div>
                                </div>

                                {{-- Savings card --}}
                                <div class="flex min-w-0 flex-1 flex-col items-center justify-center overflow-hidden rounded-2xl bg-black p-6 text-center text-white shadow-sm" draggable="false">
                                    <h3 class="text-sm font-black uppercase tracking-wider">Bundle Savings</h3>
                                    <div class="mt-4 rounded-lg border border-slate-600 px-6 py-4">
                                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Save</p>
                                        <p class="mt-1 text-4xl font-black">&euro;{{ number_format((float) $bundle->savings, 0) }}</p>
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-white">Total: &euro;{{ number_format((float) $bundle->total_price - (float) $bundle->savings, 2) }}</p>
                                    <p class="text-xs text-slate-400 line-through">&euro;{{ number_format((float) $bundle->total_price, 2) }}</p>
                                    <p class="mt-3 text-xs leading-relaxed text-slate-400">Get both items together and save on your order</p>
                                    <a
                                        href="{{ route('shop.category', 'sale') }}"
                                        class="mt-4 block w-full rounded-lg border-2 border-white bg-white px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-black transition hover:bg-transparent hover:text-white"
                                    >
                                        Get Bundle
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Right arrow --}}
                <button
                    x-show="canScrollRight"
                    x-on:click="scrollByBundle(1)"
                    type="button"
                    aria-label="Scroll right"
                    class="absolute right-0 top-1/2 z-10 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-slate-300 bg-white shadow-lg transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400 md:flex"
                >
                    <svg class="h-5 w-5 text-slate-800" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </button>
            </div>
        @endif
    </div>
</div>
