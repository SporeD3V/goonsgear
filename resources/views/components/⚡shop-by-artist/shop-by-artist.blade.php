<div class="border-b border-slate-200 bg-white px-6 py-16 lg:py-20">
    <div class="mx-auto max-w-6xl">
        {{-- Section header --}}
        <div class="mb-10 text-center">
            <h2 class="text-3xl font-black uppercase tracking-tight text-slate-900 md:text-4xl lg:text-5xl">How Do You Want to Shop?</h2>
            <p class="mt-3 text-base text-slate-500">Choose your shopping style</p>
        </div>

        {{-- Mode toggle --}}
        <div class="mx-auto mb-12 flex justify-center">
            <div class="inline-flex overflow-hidden rounded border border-slate-900">
                <button
                    wire:click="$set('mode', 'artist')"
                    type="button"
                    @class([
                        'px-8 py-3 text-sm font-bold uppercase tracking-widest transition-all duration-300',
                        'bg-slate-900 text-white' => $mode === 'artist',
                        'bg-white text-slate-900 hover:bg-slate-100' => $mode !== 'artist',
                    ])
                >
                    By Artist
                </button>
                <button
                    wire:click="$set('mode', 'category')"
                    type="button"
                    @class([
                        'px-8 py-3 text-sm font-bold uppercase tracking-widest border-l border-slate-900 transition-all duration-300',
                        'bg-slate-900 text-white' => $mode === 'category',
                        'bg-white text-slate-900 hover:bg-slate-100' => $mode !== 'category',
                    ])
                >
                    By Category
                </button>
            </div>
        </div>

        {{-- Content area with smooth transition --}}
        <div
            x-data="{ shown: true }"
            x-init="$watch('$wire.mode', () => { shown = false; setTimeout(() => shown = true, 150) })"
        >
            <div
                x-show="shown"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-2"
            >
                @if ($mode === 'artist')
                    {{-- Artist search --}}
                    <div class="mx-auto mb-10 max-w-lg">
                        <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-400">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0Z"/>
                                    </svg>
                                </span>
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="search"
                                    x-on:focus="open = true"
                                    x-on:input="open = true"
                                    placeholder="Search artists…"
                                    class="w-full rounded-lg border border-slate-300 bg-white py-3 pl-11 pr-4 text-sm text-slate-900 placeholder-slate-400 shadow-sm transition-shadow duration-200 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                                    autocomplete="off"
                                >
                            </div>

                            {{-- Search results dropdown --}}
                            @if ($searchResults->isNotEmpty())
                                <div
                                    x-show="open && $wire.search.trim() !== ''"
                                    x-cloak
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 -translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    class="absolute left-0 right-0 top-full z-20 mt-1 max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-xl"
                                >
                                    @foreach ($searchResults as $result)
                                        <a
                                            href="{{ route('shop.artist', $result->slug) }}"
                                            class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 text-sm text-slate-700 last:border-b-0 transition-colors duration-150 hover:bg-slate-50"
                                        >
                                            <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/></svg>
                                            {{ $result->name }}
                                        </a>
                                    @endforeach
                                </div>
                            @elseif (trim($this->search) !== '')
                                <div
                                    x-show="open"
                                    x-cloak
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 -translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    class="absolute left-0 right-0 top-full z-20 mt-1 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-xl"
                                >
                                    <p class="text-sm text-slate-500">No artists found.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Artist logo carousel --}}
                    @if ($carouselTags->isNotEmpty())
                        <div
                            class="relative px-12"
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
                                scrollBy(direction) {
                                    this.$refs.track.scrollBy({ left: direction * 300, behavior: 'smooth' });
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
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-75"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-on:click="scrollBy(-1)"
                                type="button"
                                aria-label="Scroll left"
                                class="absolute left-0 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white shadow-md transition-all duration-200 hover:bg-slate-50 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-slate-900/20"
                            >
                                <svg class="h-5 w-5 text-slate-700" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
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
                                @foreach ($carouselTags as $tag)
                                    <a
                                        href="{{ route('shop.artist', $tag->slug) }}"
                                        wire:key="carousel-tag-{{ $tag->id }}"
                                        class="group relative block w-[calc(50%-0.75rem)] shrink-0 overflow-hidden rounded-lg sm:w-[calc(33.333%-1rem)] lg:w-[calc(20%-1.2rem)]"
                                        draggable="false"
                                    >
                                        <div class="relative aspect-square w-full bg-white shadow-md transition-shadow duration-500 group-hover:shadow-xl">
                                            <img
                                                src="{{ route('media.show', ['path' => $tag->logo_path]) }}"
                                                alt="{{ $tag->name }} logo"
                                                class="absolute inset-0 h-full w-full object-contain p-4 transition-transform duration-500 ease-out group-hover:scale-110"
                                                width="200"
                                                height="200"
                                                draggable="false"
                                            >
                                            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent transition-opacity duration-300 group-hover:from-black/80"></div>
                                            <div class="absolute inset-x-0 bottom-0 p-4">
                                                <span class="text-sm font-black uppercase tracking-wide text-white drop-shadow-lg">{{ $tag->name }}</span>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>

                            {{-- Right arrow --}}
                            <button
                                x-show="canScrollRight"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-75"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-on:click="scrollBy(1)"
                                type="button"
                                aria-label="Scroll right"
                                class="absolute right-0 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white shadow-md transition-all duration-200 hover:bg-slate-50 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-slate-900/20"
                            >
                                <svg class="h-5 w-5 text-slate-700" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                            </button>
                        </div>
                    @else
                        <p class="text-center text-sm text-slate-500">No artists have been featured yet.</p>
                    @endif
                @else
                    {{-- Category cards grid --}}
                    @if ($categories->isNotEmpty())
                        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($categories as $category)
                                <a
                                    href="{{ route('shop.category', $category->slug) }}"
                                    wire:key="cat-{{ $category->id }}"
                                    class="group relative block aspect-square overflow-hidden rounded-lg bg-slate-100 shadow-md transition-shadow duration-300 hover:shadow-xl"
                                >
                                    @if ($category->cover_url)
                                        <img
                                            src="{{ $category->cover_url }}"
                                            alt="{{ $category->name }}"
                                            class="absolute inset-0 h-full w-full object-contain transition-transform duration-500 ease-out group-hover:scale-105"
                                            loading="lazy"
                                        >
                                    @else
                                        <div class="absolute inset-0 bg-gradient-to-br from-slate-700 to-slate-900"></div>
                                    @endif
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent transition-opacity duration-300 group-hover:from-black/80"></div>
                                    <div class="absolute inset-x-0 bottom-0 p-5">
                                        <h3 class="text-xl font-black uppercase tracking-wide text-white drop-shadow-lg md:text-2xl">{{ $category->name }}</h3>
                                        @if ($category->product_count > 0)
                                            <p class="mt-1 text-sm font-medium text-white/70">{{ $category->product_count }} {{ Str::plural('product', $category->product_count) }}</p>
                                        @endif
                                    </div>
                                    {{-- Hover arrow indicator --}}
                                    <div class="absolute right-4 top-4 flex h-8 w-8 items-center justify-center rounded-full bg-white/0 transition-all duration-300 group-hover:bg-white/20">
                                        <svg class="h-4 w-4 text-white opacity-0 transition-all duration-300 group-hover:opacity-100" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25"/></svg>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="text-center text-sm text-slate-500">No categories available yet.</p>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
