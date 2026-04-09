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
                                    <p><span class="font-medium text-black/70">Price:</span> &euro;<span data-display-price>{{ $hasAttributeGroups ? $unselectedPriceText : number_format((float) $defaultVariant->price, 2) }}</span></p>
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

                                @auth
                                    <form
                                        method="POST"
                                        action="{{ route('stock-alert-subscriptions.store') }}"
                                        class="{{ !$hasAttributeGroups && $defaultVariant->is_out_of_stock ? 'mt-4' : 'mt-4 hidden' }} rounded border border-black/10 bg-white p-3"
                                        data-stock-alert-form
                                    >
                                        @csrf
                                        <input type="hidden" name="variant_id" value="{{ $defaultVariant->id }}" data-stock-alert-variant-input>

                                        <label class="inline-flex items-center gap-2 text-sm text-black/70">
                                            <input
                                                type="checkbox"
                                                name="subscribe_stock_alert"
                                                value="1"
                                                class="rounded border-black/20 text-black focus:ring-black"
                                                checked
                                                data-stock-alert-checkbox
                                            >
                                            Notify me when this variant is back in stock
                                        </label>

                                        <div class="mt-3 flex items-center gap-3">
                                            <button type="submit" class="rounded bg-black px-3 py-2 text-sm text-white hover:bg-black/80">Save alert</button>
                                            <span class="text-xs text-black/50 hidden" data-stock-alert-subscribed-label>Alert is active for this variant.</span>
                                        </div>
                                    </form>
                                @else
                                    <p class="{{ !$hasAttributeGroups && $defaultVariant->is_out_of_stock ? 'mt-4 text-sm text-black/60' : 'mt-4 hidden text-sm text-black/60' }}" data-stock-alert-login-note>
                                        <a href="{{ route('login') }}" class="font-medium text-black underline hover:no-underline">Login</a> to enable back-in-stock alerts.
                                    </p>
                                @endauth
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
                </section>
            </div>
        </div>

        @include('partials.footer')
    </body>
</html>
