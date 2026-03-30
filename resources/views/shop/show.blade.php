<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}">
        <link rel="canonical" href="{{ $seo['canonical_url'] }}">
        <meta property="og:type" content="product">
        <meta property="og:title" content="{{ $seo['title'] }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:url" content="{{ $seo['canonical_url'] }}">
        @if ($seo['og_image'])
            <meta property="og:image" content="{{ $seo['og_image'] }}">
        @endif
        @php
            $jsonLdImage = $product->media->first() ? route('media.show', ['path' => $product->media->first()->path]) : null;
            $jsonLdOffers = $product->variants
                ->where('is_active', true)
                ->map(fn ($variant) => [
                    '@type' => 'Offer',
                    'priceCurrency' => 'EUR',
                    'price' => number_format((float) $variant->price, 2, '.', ''),
                    'sku' => $variant->sku,
                    'availability' => $variant->stock_quantity > 0
                        ? 'https://schema.org/InStock'
                        : (($variant->allow_backorder || $variant->is_preorder) ? 'https://schema.org/PreOrder' : 'https://schema.org/OutOfStock'),
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
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-slate-100 text-slate-900">
        <div class="mx-auto max-w-6xl p-6">
            <header class="mb-6 flex items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold">{{ $product->name }}</h1>
                <div class="flex items-center gap-4">
                    <a href="{{ route('cart.index') }}" class="text-sm text-blue-700 hover:underline">Cart</a>
                    <a href="{{ route('shop.index') }}" class="text-sm text-blue-700 hover:underline">Back to shop</a>
                </div>
            </header>

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
                        $primaryMediaUrl = $primaryMedia ? route('media.show', ['path' => $primaryMedia->path]) : null;
                    @endphp

                    @if ($primaryMediaUrl)
                        <img
                            src="{{ $primaryMediaUrl }}"
                            alt="{{ $primaryMedia?->alt_text ?: $product->name }}"
                            class="h-112 w-full cursor-zoom-in rounded border border-slate-200 bg-white object-contain p-2"
                            data-media-main-image
                            tabindex="0"
                        >
                        <video
                            class="hidden h-112 w-full rounded border border-slate-200 bg-black object-contain p-2"
                            controls
                            data-media-main-video
                        ></video>

                        <div class="mt-2 flex justify-end">
                            <button type="button" class="rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50" data-lightbox-launch>
                                Open lightbox
                            </button>
                        </div>
                    @else
                        <img src="{{ asset('images/placeholder-product.svg') }}" alt="No image available" class="h-112 w-full rounded border border-slate-200 bg-white object-contain p-2">
                    @endif

                    @if ($product->media->count() > 1)
                        @if ($product->variants->isNotEmpty())
                            <div class="mt-3">
                                <label for="media-variant-filter" class="mb-1 block text-sm font-medium text-slate-700">Filter gallery by variant</label>
                                <select id="media-variant-filter" data-media-variant-filter class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                    <option value="all">All variants</option>
                                    @foreach ($product->variants as $variant)
                                        <option value="{{ $variant->id }}">{{ $variant->name }} ({{ $variant->sku }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="mt-3 grid grid-cols-4 gap-2">
                            @foreach ($product->media as $media)
                                @php
                                    $thumbnailUrl = route('media.show', ['path' => $media->path]);
                                    $isVideo = str_starts_with((string) $media->mime_type, 'video/');
                                @endphp
                                <button
                                    type="button"
                                    class="h-20 w-full cursor-pointer rounded border border-slate-200 bg-white p-0 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                    data-media-thumb
                                    data-media-type="{{ $isVideo ? 'video' : 'image' }}"
                                    data-media-url="{{ $thumbnailUrl }}"
                                    data-media-alt="{{ $media->alt_text ?: $product->name }}"
                                    data-media-variant-id="{{ $media->product_variant_id ?? '' }}"
                                    aria-pressed="false"
                                >
                                    @if ($isVideo)
                                        <span class="flex h-20 w-full items-center justify-center rounded bg-slate-900 text-xs font-medium text-white">VIDEO</span>
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
                                <p class="mt-1">Esc: close, Left/Right arrows: previous or next, Swipe left/right: navigate, X: close.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded border border-slate-200 bg-white p-5">
                    <p class="text-sm text-slate-500">Category: {{ $product->primaryCategory?->name ?? 'Uncategorized' }}</p>
                    @if ($product->excerpt)
                        <p class="mt-3 text-slate-700">{{ $product->excerpt }}</p>
                    @endif

                    @if ($product->description)
                        <div class="mt-4 text-sm leading-6 text-slate-700">
                            {!! nl2br(e($product->description)) !!}
                        </div>
                    @endif

                    <div class="mt-6">
                        <h2 class="text-base font-semibold">Available Variants</h2>
                        @if ($variantsWithStockState->isEmpty())
                            <p class="mt-2 text-sm text-slate-600">No active variants available.</p>
                        @else
                            @php
                                $defaultVariant = $variantsWithStockState->first();
                                $defaultStockStatus = $defaultVariant->stock_quantity > 0
                                    ? 'In stock'
                                    : (($defaultVariant->allow_backorder || $defaultVariant->is_preorder) ? 'Preorder' : 'Out of stock');
                            @endphp

                            <div
                                class="mt-3 rounded border border-slate-200 bg-slate-50 p-3"
                                data-product-variant-picker
                                data-gallery-filter-id="media-variant-filter"
                            >
                                <label for="shop-variant-select" class="mb-1 block text-sm font-medium text-slate-700">Choose variant</label>
                                <select id="shop-variant-select" data-variant-select class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm">
                                    @foreach ($variantsWithStockState as $variant)
                                        @php
                                            $stockStatus = $variant->stock_quantity > 0
                                                ? 'In stock'
                                                : (($variant->allow_backorder || $variant->is_preorder) ? 'Preorder' : 'Out of stock');
                                        @endphp
                                        <option
                                            value="{{ $variant->id }}"
                                            data-variant-price="{{ number_format((float) $variant->price, 2) }}"
                                            data-variant-sku="{{ $variant->sku }}"
                                            data-variant-status="{{ $stockStatus }}"
                                            data-variant-qty="{{ $variant->stock_quantity }}"
                                            data-variant-out-of-stock="{{ $variant->is_out_of_stock ? '1' : '0' }}"
                                            data-variant-stock-alert-subscribed="{{ in_array($variant->id, $activeStockAlertVariantIds, true) ? '1' : '0' }}"
                                        >
                                            {{ $variant->name }} ({{ $variant->sku }})
                                        </option>
                                    @endforeach
                                </select>

                                <div class="mt-3 grid gap-2 text-sm sm:grid-cols-2" data-variant-panel>
                                    <p><span class="font-medium text-slate-700">Price:</span> $<span data-variant-price>{{ number_format((float) $defaultVariant->price, 2) }}</span></p>
                                    <p><span class="font-medium text-slate-700">SKU:</span> <span data-variant-sku>{{ $defaultVariant->sku }}</span></p>
                                    <p><span class="font-medium text-slate-700">Status:</span> <span data-variant-status>{{ $defaultStockStatus }}</span></p>
                                    <p><span class="font-medium text-slate-700">Qty:</span> <span data-variant-qty>{{ $defaultVariant->stock_quantity }}</span></p>
                                </div>

                                <form method="POST" action="{{ route('cart.items.store') }}" class="mt-4 flex flex-wrap items-end gap-3">
                                    @csrf
                                    <input type="hidden" name="variant_id" value="{{ $defaultVariant->id }}" data-cart-variant-input>

                                    <div>
                                        <label for="cart-quantity" class="mb-1 block text-sm font-medium text-slate-700">Quantity</label>
                                        <input
                                            id="cart-quantity"
                                            type="number"
                                            name="quantity"
                                            min="1"
                                            value="1"
                                            class="w-24 rounded border border-slate-300 bg-white px-3 py-2 text-sm"
                                        >
                                    </div>

                                    <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Add to cart</button>
                                </form>

                                @auth
                                    <form
                                        method="POST"
                                        action="{{ route('stock-alert-subscriptions.store') }}"
                                        class="{{ $defaultVariant->is_out_of_stock ? 'mt-4' : 'mt-4 hidden' }} rounded border border-slate-200 bg-white p-3"
                                        data-stock-alert-form
                                    >
                                        @csrf
                                        <input type="hidden" name="variant_id" value="{{ $defaultVariant->id }}" data-stock-alert-variant-input>

                                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                            <input
                                                type="checkbox"
                                                name="subscribe_stock_alert"
                                                value="1"
                                                class="rounded border-slate-300"
                                                checked
                                                data-stock-alert-checkbox
                                            >
                                            Notify me when this variant is back in stock
                                        </label>

                                        <div class="mt-3 flex items-center gap-3">
                                            <button type="submit" class="rounded bg-slate-700 px-3 py-2 text-sm text-white hover:bg-slate-800">Save alert</button>
                                            <span class="text-xs text-slate-500 hidden" data-stock-alert-subscribed-label>Alert is active for this variant.</span>
                                        </div>
                                    </form>
                                @else
                                    <p class="{{ $defaultVariant->is_out_of_stock ? 'mt-4 text-sm text-slate-600' : 'mt-4 hidden text-sm text-slate-600' }}" data-stock-alert-login-note>
                                        <a href="{{ route('login') }}" class="text-blue-700 hover:underline">Login</a> to enable back-in-stock alerts.
                                    </p>
                                @endauth
                            </div>

                            <div class="mt-3 overflow-x-auto">
                                <table class="min-w-full border border-slate-200 text-sm">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="border border-slate-200 px-3 py-2 text-left">Variant</th>
                                            <th class="border border-slate-200 px-3 py-2 text-left">SKU</th>
                                            <th class="border border-slate-200 px-3 py-2 text-left">Price</th>
                                            <th class="border border-slate-200 px-3 py-2 text-left">Stock</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($variantsWithStockState as $variant)
                                            @php
                                                $stockStatus = $variant->stock_quantity > 0
                                                    ? 'In stock'
                                                    : ($variant->allow_backorder || $variant->is_preorder ? 'Preorder' : 'Out of stock');
                                            @endphp
                                            <tr>
                                                <td class="border border-slate-200 px-3 py-2">{{ $variant->name }}</td>
                                                <td class="border border-slate-200 px-3 py-2">{{ $variant->sku }}</td>
                                                <td class="border border-slate-200 px-3 py-2">${{ number_format((float) $variant->price, 2) }}</td>
                                                <td class="border border-slate-200 px-3 py-2">
                                                    <span class="inline-flex rounded px-2 py-0.5 text-xs {{ $stockStatus === 'In stock' ? 'bg-emerald-100 text-emerald-700' : ($stockStatus === 'Preorder' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700') }}">
                                                        {{ $stockStatus }}
                                                    </span>
                                                    <div class="mt-1 text-xs text-slate-500">Qty: {{ $variant->stock_quantity }}</div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </body>
</html>
