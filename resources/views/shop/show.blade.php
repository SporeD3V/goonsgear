<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}">
        @include('partials.favicons')
        <link rel="canonical" href="{{ $seo['canonical_url'] }}">
        <meta property="og:type" content="product">
        <meta property="og:title" content="{{ $seo['title'] }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:url" content="{{ $seo['canonical_url'] }}">
        @if ($seo['og_image'])
            <meta property="og:image" content="{{ $seo['og_image'] }}">
        @endif
        @php
            $formatAvailabilityDate = static fn ($value) => $value?->format('j. F Y');
            $jsonLdImage = $product->media->first() ? route('media.show', ['path' => $product->media->first()->path]) : null;
            $jsonLdOffers = $product->variants
                ->where('is_active', true)
                ->map(fn ($variant) => [
                    '@type' => 'Offer',
                    'priceCurrency' => 'EUR',
                    'price' => number_format((float) $variant->price, 2, '.', ''),
                    'sku' => $variant->sku,
                    'availability' => $variant->is_preorder || $variant->allow_backorder
                        ? 'https://schema.org/PreOrder'
                        : ($variant->stock_quantity > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'),
                    'url' => route('shop.show', $product),
                ])
                ->values();
            $jsonLdProduct = [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $product->name,
                'description' => $product->meta_description ?: $product->excerpt,
                'category' => $product->primaryCategory?->name,
                'image' => $jsonLdImage,
                'offers' => $jsonLdOffers,
            ];
        @endphp
        <script type="application/ld+json">{!! Js::from($jsonLdProduct) !!}</script>
        @if (count($breadcrumbs) > 1)
            <script type="application/ld+json">{!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => collect($breadcrumbs)->map(fn ($crumb, $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $crumb['name'],
                    ...($crumb['url'] ? ['item' => $crumb['url']] : []),
                ])->all(),
            ], JSON_UNESCAPED_SLASHES) !!}</script>
        @endif
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        <meta name="csrf-token" content="{{ csrf_token() }}">
    </head>
    <body class="bg-white text-black">
        @include('partials.header')

        <div class="mx-auto max-w-6xl p-6">

            @include('partials.breadcrumb', ['breadcrumbs' => $breadcrumbs])

            @if (session('status'))
                <div class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('status') }}</div>
            @endif

            @if ($errors->has('cart'))
                <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $errors->first('cart') }}</div>
            @endif

            @if ($errors->has('stock_alert'))
                <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $errors->first('stock_alert') }}</div>
            @endif

            <div class="grid gap-6 lg:grid-cols-2">
                <section data-media-gallery>
                    @php
                        $primaryMedia = $product->media->first();
                        $primaryMediaUrl = $primaryMedia ? route('media.show', ['path' => $primaryMedia->display_path ?? $primaryMedia->path]) : null;
                        $primaryIsVideo = $primaryMedia ? str_starts_with((string) $primaryMedia->mime_type, 'video/') : false;
                    @endphp

                    @if ($primaryMediaUrl)
                        <img
                            src="{{ $primaryMediaUrl }}"
                            alt="{{ $primaryMedia?->alt_text ?: $product->name }}"
                            class="{{ $primaryIsVideo ? 'hidden ' : '' }}h-112 w-full cursor-zoom-in rounded border border-black/10 bg-white object-contain p-2"
                            data-media-main-image
                            tabindex="0"
                        >
                        <video
                            class="{{ $primaryIsVideo ? '' : 'hidden ' }}h-112 w-full cursor-zoom-in rounded border border-black/10 bg-black object-contain p-2"
                            controls
                            @if ($primaryIsVideo)
                                src="{{ $primaryMediaUrl }}"
                            @endif
                            data-media-main-video
                            tabindex="0"
                        ></video>

                        <div class="mt-2 flex justify-end">
                            <button type="button" class="rounded border border-black/20 bg-white px-3 py-1.5 text-xs font-medium text-black/70 hover:bg-black/5" data-lightbox-launch>
                                Open lightbox
                            </button>
                        </div>
                    @else
                        <img src="{{ asset('images/placeholder-product.svg') }}" alt="No image available" class="h-112 w-full rounded border border-black/10 bg-white object-contain p-2">
                    @endif

                    @if ($product->media->count() > 0)
                        <div class="mt-3 grid grid-cols-4 gap-2">
                            @foreach ($product->media as $media)
                                @php
                                    $displayUrl = route('media.show', ['path' => $media->display_path ?? $media->path]);
                                    $thumbnailUrl = route('media.show', ['path' => $media->thumbnail_path ?? $media->path]);
                                    $zoomMediaUrl = route('media.show', ['path' => $media->zoom_path ?? $media->path]);
                                    $isVideo = str_starts_with((string) $media->mime_type, 'video/');
                                    $mediaVariantAttributes = $media->product_variant_id
                                        ? ($variantSelectorData['variantAttributesById'][$media->product_variant_id] ?? [])
                                        : [];
                                    $mediaVariantColor = $mediaVariantAttributes['color'] ?? '';
                                @endphp
                                <button
                                    type="button"
                                    class="h-20 w-full cursor-pointer rounded border border-black/10 bg-white p-0 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-black"
                                    data-media-thumb
                                    data-media-type="{{ $isVideo ? 'video' : 'image' }}"
                                    data-media-url="{{ $displayUrl }}"
                                    data-media-zoom-url="{{ $zoomMediaUrl }}"
                                    data-media-alt="{{ $media->alt_text ?: $product->name }}"
                                    data-media-variant-id="{{ $media->product_variant_id ?? '' }}"
                                    data-media-variant-color="{{ $mediaVariantColor }}"
                                    aria-pressed="false"
                                >
                                    @if ($isVideo)
                                        <span class="flex h-20 w-full items-center justify-center rounded bg-black text-xs font-medium text-white">VIDEO</span>
                                    @else
                                        <img src="{{ $thumbnailUrl }}" alt="{{ $media->alt_text ?: $product->name }}" class="h-20 w-full rounded bg-white object-cover">
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <div class="fixed inset-0 z-50 hidden bg-black/90 p-3 sm:p-6" data-lightbox role="dialog" aria-modal="true" aria-label="Product media lightbox">
                        <div class="mx-auto flex h-full w-full max-w-6xl flex-col" data-lightbox-backdrop>
                            <div class="mb-3 flex items-center justify-between gap-3 text-slate-200">
                                <p class="text-xs sm:text-sm" data-lightbox-caption>Media preview</p>
                                <button
                                    type="button"
                                    class="rounded border border-slate-500 px-3 py-1 text-sm font-semibold text-white hover:bg-slate-800"
                                    data-lightbox-close
                                    aria-label="Close lightbox"
                                >
                                    X
                                </button>
                            </div>

                            <div class="relative flex-1 overflow-hidden rounded border border-slate-700/80 bg-black/70" data-lightbox-stage>
                                <div class="absolute right-3 top-3 z-10 flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="rounded bg-black/60 px-3 py-1.5 text-sm font-semibold text-white hover:bg-black/80"
                                        data-lightbox-zoom-out
                                        aria-label="Zoom out"
                                    >
                                        -
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded bg-black/60 px-3 py-1.5 text-sm font-semibold text-white hover:bg-black/80"
                                        data-lightbox-zoom-in
                                        aria-label="Zoom in"
                                    >
                                        +
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded bg-black/60 px-3 py-1.5 text-xs font-semibold text-white hover:bg-black/80"
                                        data-lightbox-zoom-reset
                                        aria-label="Reset zoom"
                                    >
                                        1x
                                    </button>
                                </div>

                                <img
                                    src=""
                                    alt=""
                                    class="h-full w-full object-contain"
                                    data-lightbox-image
                                >
                                <video
                                    class="hidden h-full w-full object-contain"
                                    controls
                                    data-lightbox-video
                                ></video>

                                <button
                                    type="button"
                                    class="absolute left-2 top-1/2 -translate-y-1/2 rounded bg-black/60 px-3 py-2 text-lg font-semibold text-white hover:bg-black/80"
                                    data-lightbox-prev
                                    aria-label="Previous media"
                                >
                                    &#8592;
                                </button>
                                <button
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded bg-black/60 px-3 py-2 text-lg font-semibold text-white hover:bg-black/80"
                                    data-lightbox-next
                                    aria-label="Next media"
                                >
                                    &#8594;
                                </button>
                            </div>

                            <div class="mt-3 rounded border border-slate-700/80 bg-slate-900/70 p-3 text-xs text-slate-200 sm:text-sm">
                                <p class="font-semibold">Controls</p>
                                <p class="mt-1">Esc: close, Left/Right arrows: previous or next, Swipe left/right: navigate, wheel or +/-: zoom, drag: pan, X: close.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded border border-black/10 bg-white p-5">
                    <p class="text-sm text-black/50">Category: {{ $product->primaryCategory?->name ?? 'Uncategorized' }}</p>
                    @if ($product->formattedExcerpt() !== '')
                        <div class="mt-3 text-black/70">{!! $product->formattedExcerpt() !!}</div>
                    @endif

                    @if ($product->formattedDescription() !== '')
                        <div class="mt-4 text-sm leading-6 text-black/70">
                            {!! $product->formattedDescription() !!}
                        </div>
                    @endif

                    @if ($parentBundle !== null)
                        {{-- Get the Bundle Instead CTA --}}
                        <div class="mt-6 rounded border border-black/10 bg-black/5">
                            <div class="flex items-center gap-4 p-4">
                                @if ($parentBundle['media_url'])
                                    <a href="{{ route('shop.show', ['product' => $parentBundle['slug']]) }}" class="shrink-0">
                                        <img src="{{ $parentBundle['media_url'] }}" alt="{{ $parentBundle['name'] }}" class="h-16 w-16 rounded border border-black/10 bg-white object-contain p-1">
                                    </a>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-black/50">Part of a bundle</p>
                                    <a href="{{ route('shop.show', ['product' => $parentBundle['slug']]) }}" class="mt-0.5 block text-sm font-bold text-black hover:underline">{{ $parentBundle['name'] }}</a>
                                    <p class="mt-0.5 text-sm">
                                        <span class="font-semibold">&euro;{{ number_format($parentBundle['bundle_price'], 2) }}</span>
                                        <span class="ml-1 text-xs font-bold text-red-600">Save &euro;{{ number_format($parentBundle['savings'], 2) }}</span>
                                    </p>
                                </div>
                                @if ($parentBundle['auto_variant_ids'] !== null)
                                    <form method="POST" action="{{ route('cart.bundle.store') }}">
                                        @csrf
                                        @foreach ($parentBundle['auto_variant_ids'] as $variantId)
                                            <input type="hidden" name="variant_ids[]" value="{{ $variantId }}">
                                        @endforeach
                                        <button type="submit" class="shrink-0 rounded bg-black px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-white transition hover:bg-black/80">Add Bundle to Cart</button>
                                    </form>
                                @else
                                    <a href="{{ route('shop.show', ['product' => $parentBundle['slug']]) }}" class="shrink-0 rounded bg-black px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-white transition hover:bg-black/80">Get Bundle</a>
                                @endif
                            </div>

                            @if (count($parentBundle['items']) > 0)
                                <div class="border-t border-black/10 px-4 pb-3 pt-2">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-black/40">What's included</p>
                                    <div class="mt-2 flex items-center gap-3">
                                        @foreach ($parentBundle['items'] as $bundleItem)
                                            <div class="flex items-center gap-2">
                                                @if ($bundleItem['media_url'])
                                                    <img src="{{ $bundleItem['media_url'] }}" alt="{{ $bundleItem['name'] }}" class="h-10 w-10 rounded border border-black/10 bg-white object-contain p-0.5">
                                                @endif
                                                <span class="text-xs font-medium text-black/70">{{ $bundleItem['name'] }}</span>
                                            </div>
                                            @if (! $loop->last)
                                                <span class="text-xs font-bold text-black/30">+</span>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($bundleData !== null)
                        {{-- Bundle section: shows component products with variant selectors --}}
                        <div
                            class="mt-6"
                            x-data="bundleSelector()"
                            x-init='init(@json($bundleData["components"]), {{ $bundleData["bundle_price"] }})'
                        >
                            {{-- Bundle pricing banner --}}
                            <div class="rounded bg-black p-4 text-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wider text-white/60">Bundle Price</p>
                                        <p class="text-2xl font-black">&euro;{{ number_format($bundleData['bundle_price'], 2) }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-white/50 line-through">&euro;{{ number_format($bundleData['component_total'], 2) }}</p>
                                        <p class="text-sm font-bold text-red-500">Save &euro;{{ number_format($bundleData['savings'], 2) }}</p>
                                    </div>
                                </div>
                            </div>

                            <h2 class="mt-5 text-base font-semibold">What's Included</h2>

                            <div class="mt-3 space-y-4">
                                @foreach ($bundleData['components'] as $index => $component)
                                    <div class="rounded border border-black/10 bg-black/5 p-3">
                                        <div class="flex gap-3">
                                            <a href="{{ route('shop.show', ['product' => $component['slug']]) }}" class="shrink-0">
                                                <img
                                                    src="{{ $component['media_url'] }}"
                                                    alt="{{ $component['name'] }}"
                                                    class="h-20 w-20 rounded border border-black/10 bg-white object-contain p-1"
                                                >
                                            </a>
                                            <div class="min-w-0 flex-1">
                                                <a href="{{ route('shop.show', ['product' => $component['slug']]) }}" class="text-sm font-semibold text-black hover:underline">
                                                    {{ $component['name'] }}
                                                </a>
                                                <p class="text-xs text-black/50">{{ $component['category'] }}</p>

                                                @if (count($component['variants']) === 1)
                                                    {{-- Single variant — auto-selected --}}
                                                    @php $singleName = preg_replace('/^Default\s*[—–-]\s*/i', '', $component['variants'][0]['name']); @endphp
                                                    <p class="mt-1 text-sm text-black/70">{{ $singleName !== '' ? $singleName.' — ' : '' }}&euro;{{ number_format($component['variants'][0]['price'], 2) }}</p>
                                                @else
                                                    {{-- Multiple variants — show selector --}}
                                                    <div class="mt-2">
                                                        <select
                                                            x-model="selections[{{ $index }}]"
                                                            x-on:change="updateSelections()"
                                                            class="w-full rounded border border-black/20 bg-white px-3 py-1.5 text-sm"
                                                        >
                                                            <option value="">Select option…</option>
                                                            @foreach ($component['variants'] as $variant)
                                                                @php $variantName = preg_replace('/^Default\s*[—–-]\s*/i', '', $variant['name']); @endphp
                                                                <option
                                                                    value="{{ $variant['id'] }}"
                                                                    {{ !$variant['in_stock'] ? 'disabled' : '' }}
                                                                >
                                                                    {{ $variantName !== '' ? $variantName.' — ' : '' }}&euro;{{ number_format($variant['price'], 2) }}
                                                                    {{ !$variant['in_stock'] ? '(Out of stock)' : '' }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Add Bundle to Cart form --}}
                            <form method="POST" action="{{ route('cart.bundle.store') }}" class="mt-4" x-ref="bundleForm">
                                @csrf
                                <template x-for="(variantId, idx) in selectedVariantIds" :key="idx">
                                    <input type="hidden" name="variant_ids[]" x-bind:value="variantId">
                                </template>

                                <button
                                    type="submit"
                                    class="w-full rounded bg-black px-4 py-3 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-black/80 disabled:cursor-not-allowed disabled:opacity-60"
                                    x-bind:disabled="!allSelected"
                                >
                                    <span x-show="allSelected">Add Bundle to Cart &mdash; &euro;{{ number_format($bundleData['bundle_price'], 2) }}</span>
                                    <span x-show="!allSelected" x-cloak>Select all options to continue</span>
                                </button>
                            </form>
                        </div>
                    @else
                    <div class="mt-6">
                        <h2 class="text-base font-semibold">Available Variants</h2>
                        @if ($variantsWithStockState->isEmpty())
                            <p class="mt-2 text-sm text-black/60">No active variants available.</p>
                        @else
                            @php
                                $defaultVariant = $variantsWithStockState->first();
                                $hasAttributeGroups = !empty($variantSelectorData['groups']);
                                $variantPriceMin = $variantsWithStockState->min(fn ($variant) => (float) $variant->price);
                                $variantPriceMax = $variantsWithStockState->max(fn ($variant) => (float) $variant->price);
                                $unselectedPriceText = '--';

                                if ($hasAttributeGroups && $variantPriceMin !== null && $variantPriceMax !== null) {
                                    $unselectedPriceText = $variantPriceMin === $variantPriceMax
                                        ? number_format((float) $variantPriceMin, 2)
                                        : number_format((float) $variantPriceMin, 2).' - '.number_format((float) $variantPriceMax, 2);
                                }
                                $defaultStockStatus = $defaultVariant->is_preorder || $defaultVariant->allow_backorder
                                    ? 'Preorder'
                                    : ($defaultVariant->stock_quantity > 0 ? 'In stock' : 'Out of stock');
                                $defaultVariantAttributes = $hasAttributeGroups
                                    ? []
                                    : ($variantSelectorData['variantAttributesById'][$defaultVariant->id] ?? []);
                                $defaultAvailabilityDate = $formatAvailabilityDate(
                                    $defaultVariant->preorder_available_from
                                    ?? $defaultVariant->expected_ship_at
                                    ?? $product->preorder_available_from
                                    ?? $product->expected_ship_at
                                );
                            @endphp

                            <div
                                class="mt-3 rounded border border-black/10 bg-black/5 p-3"
                                data-product-variant-picker
                                data-requires-attribute-selection="{{ $hasAttributeGroups ? '1' : '0' }}"
                                data-unselected-price="{{ $unselectedPriceText }}"
                                data-variant-attribute-order="{{ implode(',', $variantSelectorData['attributeOrder']) }}"
                            >
                                @if (!empty($variantSelectorData['groups']))
                                    <div class="mb-3 space-y-3">
                                        @foreach ($variantSelectorData['groups'] as $attributeKey => $attributeGroup)
                                            <div data-variant-attribute-group="{{ $attributeKey }}">
                                                <p class="mb-2 text-sm font-medium text-black/70">{{ $attributeGroup['label'] }}</p>
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach ($attributeGroup['values'] as $attributeValue)
                                                        <button
                                                            type="button"
                                                            data-variant-attribute="{{ $attributeKey }}"
                                                            data-variant-attribute-value="{{ $attributeValue }}"
                                                            class="rounded border border-black/20 bg-white px-3 py-1.5 text-sm text-black/70 transition hover:border-black/50"
                                                        >
                                                            {{ $attributeValue }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <select data-variant-select class="hidden" aria-hidden="true" tabindex="-1">
                                    @if ($hasAttributeGroups)
                                        <option value="" selected disabled>Select variant options</option>
                                    @endif
                                    @foreach ($variantsWithStockState as $variant)
                                        @php
                                            $stockStatus = $variant->stock_quantity > 0
                                                ? 'In stock'
                                                : (($variant->allow_backorder || $variant->is_preorder) ? 'Preorder' : 'Out of stock');
                                        @endphp
                                        <option
                                            value="{{ $variant->id }}"
                                            {{ !$hasAttributeGroups && $loop->first ? 'selected' : '' }}
                                            data-variant-price="{{ number_format((float) $variant->price, 2) }}"
                                            @if ($variant->compare_at_price !== null && (float) $variant->compare_at_price > (float) $variant->price)
                                                data-variant-compare-price="{{ number_format((float) $variant->compare_at_price, 2) }}"
                                            @endif
                                            data-variant-sku="{{ $variant->sku }}"
                                            data-variant-status="{{ $stockStatus }}"
                                            data-variant-qty="{{ $variant->stock_quantity }}"
                                            data-variant-track-inventory="{{ $variant->track_inventory ? '1' : '0' }}"
                                            data-variant-allow-backorder="{{ $variant->allow_backorder ? '1' : '0' }}"
                                            data-variant-is-preorder="{{ $variant->is_preorder ? '1' : '0' }}"
                                            data-variant-availability="{{ $formatAvailabilityDate($variant->preorder_available_from ?? $variant->expected_ship_at ?? $product->preorder_available_from ?? $product->expected_ship_at) ?? '' }}"
                                            data-variant-attributes='@json($variantSelectorData['variantAttributesById'][$variant->id] ?? [])'
                                            data-variant-out-of-stock="{{ $variant->is_out_of_stock ? '1' : '0' }}"
                                            data-variant-stock-alert-subscribed="{{ in_array($variant->id, $activeStockAlertVariantIds, true) ? '1' : '0' }}"
                                        >
                                            {{ $variant->name }} ({{ $variant->sku }})
                                        </option>
                                    @endforeach
                                </select>

                                <div class="mt-3 grid gap-2 text-sm sm:grid-cols-2" data-variant-panel>
                                    @if ($hasAttributeGroups)
                                        <p class="sm:col-span-2 text-xs text-black/50">Select variant options to view details.</p>
                                    @endif
                                    @php
                                        $defaultIsOnSale = !$hasAttributeGroups && $defaultVariant->compare_at_price !== null && (float) $defaultVariant->compare_at_price > (float) $defaultVariant->price;
                                    @endphp
                                    <p>
                                        <span class="font-medium text-black/70">Price:</span>
                                        <span data-display-price-wrap>
                                            @if ($defaultIsOnSale)
                                                <span class="text-black/40 line-through" data-display-compare-price>&euro;{{ number_format((float) $defaultVariant->compare_at_price, 2) }}</span>
                                                <span class="font-semibold text-red-600">&euro;<span data-display-price>{{ number_format((float) $defaultVariant->price, 2) }}</span></span>
                                            @else
                                                &euro;<span data-display-price>{{ $hasAttributeGroups ? $unselectedPriceText : number_format((float) $defaultVariant->price, 2) }}</span>
                                            @endif
                                        </span>
                                    </p>
                                    <p><span class="font-medium text-black/70">SKU:</span> <span data-display-sku>{{ $hasAttributeGroups ? '--' : $defaultVariant->sku }}</span></p>
                                    <p><span class="font-medium text-black/70">Status:</span> <span data-display-status>{{ $hasAttributeGroups ? 'Select options' : $defaultStockStatus }}</span></p>
                                    <p><span class="font-medium text-black/70">Qty:</span> <span data-display-qty>{{ $hasAttributeGroups ? '--' : $defaultVariant->stock_quantity }}</span></p>
                                    <p class="{{ !$hasAttributeGroups && $defaultStockStatus === 'Preorder' && $defaultAvailabilityDate ? '' : 'hidden' }} sm:col-span-2" data-display-availability-line>
                                        <span class="font-medium text-black/70">Available on:</span>
                                        <span data-display-availability>{{ $hasAttributeGroups ? '' : $defaultAvailabilityDate }}</span>
                                    </p>
                                </div>

                                <form method="POST" action="{{ route('cart.items.store') }}" class="mt-4 flex flex-wrap items-end gap-3">
                                    @csrf
                                    <input type="hidden" name="variant_id" value="{{ $hasAttributeGroups ? '' : $defaultVariant->id }}" data-cart-variant-input>

                                    <div>
                                        <label for="cart-quantity" class="mb-1 block text-sm font-medium text-black/70">Quantity</label>
                                        <input
                                            id="cart-quantity"
                                            type="number"
                                            name="quantity"
                                            min="1"
                                            value="1"
                                            data-cart-quantity-input
                                            class="w-24 rounded border border-black/20 bg-white px-3 py-2 text-sm"
                                        >
                                    </div>

                                    <button type="submit" data-add-to-cart-button class="rounded bg-black px-4 py-2 text-sm font-medium text-white hover:bg-black/80 disabled:cursor-not-allowed disabled:opacity-60" {{ $hasAttributeGroups ? 'disabled' : '' }}>Add to cart</button>
                                </form>

                                <div
                                    class="{{ !$hasAttributeGroups && $defaultVariant->is_out_of_stock ? 'mt-4' : 'mt-4 hidden' }}"
                                    data-stock-alert-container
                                >
                                    <button
                                        type="button"
                                        class="w-full rounded border border-black/20 bg-white px-4 py-2.5 text-sm font-medium text-black/70 transition hover:border-black hover:text-black"
                                        onclick="document.getElementById('stock-alert-modal-pdp').showModal()"
                                    >
                                        <svg class="mr-1 inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
                                        Notify when back in stock
                                    </button>
                                    <span class="mt-1 block text-xs text-black/50 hidden" data-stock-alert-subscribed-label>Alert is active for this variant.</span>
                                </div>

                                <dialog id="stock-alert-modal-pdp" class="w-full max-w-sm rounded-xl border border-black/10 bg-white p-0 shadow-xl backdrop:bg-black/50">
                                    <div class="p-6" x-data="stockAlertModal({{ $defaultVariant->id }}, {{ auth()->check() ? 'true' : 'false' }})" data-stock-alert-modal-alpine>
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-lg font-bold">Get notified</h3>
                                            <button type="button" class="text-black/40 hover:text-black" onclick="this.closest('dialog').close()">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <p class="mt-2 text-sm text-black/60">We'll email you when <strong>{{ $product->name }}</strong> is back in stock.</p>

                                        <template x-if="isAuth">
                                            <div class="mt-4">
                                                <button
                                                    type="button"
                                                    class="w-full rounded bg-black px-4 py-2.5 text-sm font-medium text-white transition hover:bg-black/80 disabled:opacity-50"
                                                    x-on:click="submitAuth()"
                                                    x-bind:disabled="loading"
                                                >
                                                    <span x-show="!success" x-text="loading ? 'Saving...' : 'Notify me'"></span>
                                                    <span x-show="success" x-cloak>&#10003; Alert saved!</span>
                                                </button>
                                            </div>
                                        </template>

                                        <template x-if="!isAuth">
                                            <div class="mt-4 space-y-3">
                                                <div>
                                                    <label class="mb-1 block text-sm font-medium text-black/70">Email address</label>
                                                    <input
                                                        type="email"
                                                        x-model="email"
                                                        placeholder="you@example.com"
                                                        class="w-full rounded border border-black/20 bg-white px-3 py-2 text-sm focus:border-black focus:outline-none focus:ring-1 focus:ring-black"
                                                    >
                                                    <p x-show="error" x-text="error" x-cloak class="mt-1 text-xs text-red-600"></p>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="w-full rounded bg-black px-4 py-2.5 text-sm font-medium text-white transition hover:bg-black/80 disabled:opacity-50"
                                                    x-on:click="submitGuest()"
                                                    x-bind:disabled="loading"
                                                >
                                                    <span x-show="!success" x-text="loading ? 'Saving...' : 'Notify me'"></span>
                                                    <span x-show="success" x-cloak>&#10003; Alert saved!</span>
                                                </button>
                                                <p class="text-center text-xs text-black/50">
                                                    or <a href="{{ route('login') }}" class="font-medium text-black underline hover:no-underline">log in</a> to manage your alerts
                                                </p>
                                            </div>
                                        </template>
                                    </div>
                                </dialog>
                            </div>

                            @if ($variantsWithStockState->count() > 1)
                            <div class="mt-3 overflow-x-auto">
                                <table class="min-w-full border border-black/10 text-sm">
                                    <thead class="bg-black/5">
                                        <tr>
                                            <th class="border border-black/10 px-3 py-2 text-left">Variant</th>
                                            <th class="border border-black/10 px-3 py-2 text-left">SKU</th>
                                            <th class="border border-black/10 px-3 py-2 text-left">Price</th>
                                            <th class="border border-black/10 px-3 py-2 text-left">Stock</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($variantsWithStockState as $variant)
                                            @php
                                                $stockStatus = $variant->is_preorder || $variant->allow_backorder
                                                    ? 'Preorder'
                                                    : ($variant->stock_quantity > 0 ? 'In stock' : 'Out of stock');
                                                $availabilityDate = $formatAvailabilityDate(
                                                    $variant->preorder_available_from
                                                    ?? $variant->expected_ship_at
                                                    ?? $product->preorder_available_from
                                                    ?? $product->expected_ship_at
                                                );
                                            @endphp
                                            <tr>
                                                <td class="border border-black/10 px-3 py-2">{{ $variant->name }}</td>
                                                <td class="border border-black/10 px-3 py-2">{{ $variant->sku }}</td>
                                                <td class="border border-black/10 px-3 py-2">&euro;{{ number_format((float) $variant->price, 2) }}</td>
                                                <td class="border border-black/10 px-3 py-2">
                                                    <span class="inline-flex rounded px-2 py-0.5 text-xs {{ $stockStatus === 'In stock' ? 'bg-emerald-100 text-emerald-700' : ($stockStatus === 'Preorder' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700') }}">
                                                        {{ $stockStatus }}
                                                    </span>
                                                    <div class="mt-1 text-xs text-black/50">Qty: {{ $variant->stock_quantity }}</div>
                                                    @if ($stockStatus === 'Preorder' && $availabilityDate)
                                                        <div class="mt-1 text-xs text-black/50">Available on {{ $availabilityDate }}</div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @endif
                        @endif
                    </div>
                    @endif {{-- end bundle/variant toggle --}}
                </section>
            </div>
        </div>

        <livewire:recently-viewed :current-product-id="$product->id" />

        @include('partials.footer')
    </body>
</html>
