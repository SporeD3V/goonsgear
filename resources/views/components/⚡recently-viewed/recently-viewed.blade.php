<div
    x-data="{
        loaded: false,
        init() {
            const key = 'recently_viewed';
            const currentId = {{ $currentProductId }};
            let ids = [];

            try {
                ids = JSON.parse(localStorage.getItem(key) || '[]');
            } catch (e) {
                ids = [];
            }

            // Add current product to front, deduplicate, cap at 12
            ids = [currentId, ...ids.filter(id => id !== currentId)].slice(0, 12);
            localStorage.setItem(key, JSON.stringify(ids));

            // Load other products (exclude current)
            const others = ids.filter(id => id !== currentId);
            if (others.length > 0) {
                $wire.loadProducts(others).then(() => {
                    this.loaded = true;
                });
            }
        }
    }"
>
    <template x-if="loaded">
        <section class="border-t border-black/10 bg-neutral-50 px-6 py-16 lg:py-20">
            <div class="mx-auto max-w-6xl">
                <h2 class="text-center text-3xl font-black uppercase tracking-wide text-black md:text-4xl">
                    Recently Viewed
                </h2>
                <p class="mt-3 text-center text-base text-black/50">
                    Products you checked out earlier
                </p>

                @if ($products->isNotEmpty())
                    <div class="mt-10 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
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
                                class="group flex flex-col overflow-hidden rounded-xl bg-white shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
                                wire:key="rv-{{ $product->id }}"
                            >
                                <div class="relative aspect-square w-full overflow-hidden bg-white">
                                    @if ($mediaUrl)
                                        <img
                                            src="{{ $mediaUrl }}"
                                            alt="{{ $primaryMedia?->alt_text ?: $product->name }}"
                                            class="h-full w-full object-contain p-4 transition-transform duration-500 group-hover:scale-105"
                                            loading="lazy"
                                        >
                                    @else
                                        <img
                                            src="{{ asset('images/placeholder-product.svg') }}"
                                            alt="No image available"
                                            class="h-full w-full object-contain p-4"
                                            loading="lazy"
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
                @endif
            </div>
        </section>
    </template>
</div>
