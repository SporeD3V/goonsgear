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

        {{-- Content area with smooth crossfade and fixed height --}}
        <div
            x-data="{
                mode: @js($mode),
                init() {
                    this.mode = @js($mode);
                    $watch('$wire.mode', (val) => this.mode = val);
                }
            }"
            class="relative"
        >
            {{-- Artist panel --}}
            <div
                x-show="mode === 'artist'"
                x-transition:enter="transition ease-out duration-400"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                :class="mode === 'artist' ? 'relative' : 'absolute inset-x-0 top-0'"
            >
                {{-- Search bar — full width on mobile, grid tile on larger screens --}}
                <div class="mb-3 sm:hidden">
                    <div class="relative flex items-center gap-3 rounded-lg border-2 border-dashed border-slate-200 bg-slate-50 px-4 py-3 transition-colors duration-300 focus-within:border-black focus-within:bg-white">
                        <svg class="h-5 w-5 shrink-0 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0Z"/>
                        </svg>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search artist…"
                            class="w-full border-0 bg-transparent p-0 text-sm font-bold uppercase tracking-widest text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-0"
                            autocomplete="off"
                        >
                    </div>
                </div>

                <div class="grid min-h-[21rem] grid-cols-3 content-start gap-3 sm:min-h-[22rem] sm:grid-cols-4 lg:min-h-[23rem] lg:grid-cols-6">
                    {{-- Search tile — hidden on mobile, first cell on sm+ --}}
                    <div
                        class="relative hidden aspect-square items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-slate-200 bg-slate-50 transition-colors duration-300 focus-within:border-black focus-within:bg-white sm:flex"
                    >
                        <div class="flex w-full flex-col items-center gap-3 px-4">
                            <svg class="h-7 w-7 text-slate-300 transition-colors duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0Z"/>
                            </svg>
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="search"
                                placeholder="Search artist…"
                                class="w-full border-0 bg-transparent p-0 text-center text-sm font-bold uppercase tracking-widest text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-0"
                                autocomplete="off"
                            >
                        </div>
                    </div>

                    {{-- Artist logo tiles — live-filtered by search --}}
                    @forelse ($displayTags as $tag)
                        <a
                            href="{{ route('shop.artist', $tag->slug) }}"
                            wire:key="display-tag-{{ $tag->id }}"
                            class="group relative block aspect-square overflow-hidden rounded-lg bg-white shadow-sm transition-all duration-300 hover:shadow-lg hover:ring-2 hover:ring-black/10"
                            draggable="false"
                        >
                            <img
                                src="{{ route('media.show', ['path' => $tag->logo_path]) }}"
                                alt="{{ $tag->name }} logo"
                                class="absolute inset-0 h-full w-full object-contain p-4 transition-transform duration-300 ease-out group-hover:scale-110"
                                width="200"
                                height="200"
                                loading="lazy"
                                draggable="false"
                            >
                        </a>
                    @empty
                        @if (trim($this->search) !== '')
                            <div class="col-span-full py-8 text-center">
                                <p class="text-sm text-slate-500">No artists found matching &ldquo;{{ $this->search }}&rdquo;</p>
                            </div>
                        @endif
                    @endforelse
                </div>
            </div>

            {{-- Category panel --}}
            <div
                x-show="mode === 'category'"
                x-transition:enter="transition ease-out duration-400"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                :class="mode === 'category' ? 'relative' : 'absolute inset-x-0 top-0'"
            >
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
                                <div class="absolute right-4 top-4 flex h-8 w-8 items-center justify-center rounded-full bg-white/0 transition-all duration-300 group-hover:bg-white/20">
                                    <svg class="h-4 w-4 text-white opacity-0 transition-all duration-300 group-hover:opacity-100" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25"/></svg>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-center text-sm text-slate-500">No categories available yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>
