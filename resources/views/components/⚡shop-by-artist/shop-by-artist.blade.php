<div class="border-b border-slate-200 bg-white px-6 py-10">
    <div class="mx-auto max-w-6xl">
        <h2 class="mb-6 text-xl font-bold uppercase tracking-wide text-slate-900">Shop by Artist / Brand</h2>

        {{-- Controls row: type selector + search --}}
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center">
            {{-- Type selector --}}
            <div class="flex rounded border border-slate-300 overflow-hidden shrink-0">
                <button
                    wire:click="$set('type', 'artist')"
                    type="button"
                    @class([
                        'px-4 py-2 text-sm font-medium transition',
                        'bg-slate-900 text-white' => $type === 'artist',
                        'bg-white text-slate-700 hover:bg-slate-50' => $type !== 'artist',
                    ])
                >
                    Artists
                </button>
                <button
                    wire:click="$set('type', 'brand')"
                    type="button"
                    @class([
                        'px-4 py-2 text-sm font-medium border-l border-slate-300 transition',
                        'bg-slate-900 text-white' => $type === 'brand',
                        'bg-white text-slate-700 hover:bg-slate-50' => $type !== 'brand',
                    ])
                >
                    Brands
                </button>
            </div>

            {{-- Live search --}}
            <div class="relative flex-1" x-data="{ open: false }" x-on:click.outside="open = false">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0Z"/>
                        </svg>
                    </span>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        x-on:focus="open = true"
                        x-on:input="open = true"
                        placeholder="Search {{ $type === 'artist' ? 'artists' : 'brands' }}…"
                        class="w-full rounded border border-slate-300 py-2 pl-9 pr-4 text-sm focus:border-slate-500 focus:outline-none"
                        autocomplete="off"
                    >
                </div>

                {{-- Search results dropdown --}}
                @if ($searchResults->isNotEmpty())
                    <div
                        x-show="open && $wire.search.trim() !== ''"
                        x-cloak
                        class="absolute left-0 right-0 top-full z-20 mt-1 max-h-64 overflow-y-auto rounded border border-slate-200 bg-white shadow-lg"
                    >
                        @foreach ($searchResults as $result)
                            <a
                                href="{{ $result->type === 'artist' ? route('shop.artist', $result->slug) : ($result->type === 'brand' ? route('shop.brand', $result->slug) : route('shop.tag', $result->slug)) }}"
                                class="flex items-center gap-3 border-b border-slate-100 px-4 py-2.5 text-sm text-slate-800 last:border-b-0 hover:bg-slate-50"
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
                        class="absolute left-0 right-0 top-full z-20 mt-1 rounded border border-slate-200 bg-white px-4 py-3 shadow-lg"
                    >
                        <p class="text-sm text-slate-500">No {{ $type === 'artist' ? 'artists' : 'brands' }} found.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Logo carousel --}}
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
                    x-on:click="scrollBy(-1)"
                    type="button"
                    aria-label="Scroll left"
                    class="absolute left-0 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-slate-300 bg-white shadow-lg transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400"
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
                    @foreach ($carouselTags as $tag)
                        <a
                            href="{{ $tag->type === 'artist' ? route('shop.artist', $tag->slug) : route('shop.brand', $tag->slug) }}"
                            wire:key="carousel-tag-{{ $tag->id }}"
                            class="group flex shrink-0 flex-col items-center gap-2"
                            draggable="false"
                        >
                            <div class="flex h-28 w-28 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white p-3 transition group-hover:border-slate-500 sm:h-32 sm:w-32">
                                <img
                                    src="{{ route('media.show', ['path' => $tag->logo_path]) }}"
                                    alt="{{ $tag->name }}"
                                    class="max-h-full max-w-full object-contain grayscale transition duration-300 group-hover:grayscale-0"
                                    draggable="false"
                                >
                            </div>
                            <span class="max-w-[8rem] text-center text-xs font-medium text-slate-700 group-hover:text-slate-900">{{ $tag->name }}</span>
                        </a>
                    @endforeach
                </div>

                {{-- Right arrow --}}
                <button
                    x-show="canScrollRight"
                    x-on:click="scrollBy(1)"
                    type="button"
                    aria-label="Scroll right"
                    class="absolute right-0 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-slate-300 bg-white shadow-lg transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400"
                >
                    <svg class="h-5 w-5 text-slate-800" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </button>
            </div>
        @else
            <p class="text-sm text-slate-400">No {{ $type === 'artist' ? 'artists' : 'brands' }} have been featured yet.</p>
        @endif
    </div>
</div>
