<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}">
        <meta name="robots" content="noindex, follow">
        @include('partials.favicons')
        <link rel="canonical" href="{{ $seo['canonical_url'] }}">
        <meta property="og:type" content="product">
        <meta property="og:title" content="{{ $seo['title'] }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:url" content="{{ $seo['canonical_url'] }}">
        @if ($seo['og_image'])
            <meta property="og:image" content="{{ $seo['og_image'] }}">
        @endif
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

            <div class="mx-auto max-w-2xl py-16 text-center">
                @if ($primaryMedia)
                    <img
                        src="{{ route('media.show', ['path' => $primaryMedia->path]) }}"
                        alt="{{ $primaryMedia->alt_text ?: $product->name }}"
                        class="mx-auto mb-8 h-64 w-64 rounded border border-black/10 object-contain p-4 opacity-50 grayscale"
                    >
                @endif

                <h1 class="text-3xl font-black uppercase tracking-wide text-black md:text-4xl">
                    {{ $product->name }}
                </h1>

                <p class="mt-4 text-lg text-black/60">
                    This product has been discontinued and is no longer available.
                </p>

                <a
                    href="{{ $product->primaryCategory ? route('shop.category', $product->primaryCategory) : route('shop.index') }}"
                    class="mt-8 inline-block rounded-lg bg-black px-6 py-3 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-black/80"
                >
                    Browse {{ $product->primaryCategory?->name ?? 'All Products' }}
                </a>
            </div>
        </div>

        @if ($suggestedProducts->isNotEmpty())
            <section class="border-t border-black/10 bg-neutral-50 px-6 py-16 lg:py-20">
                <div class="mx-auto max-w-6xl">
                    <h2 class="text-center text-3xl font-black uppercase tracking-wide text-black md:text-4xl">
                        You Might Also Like
                    </h2>
                    <p class="mt-3 text-center text-base text-black/50">
                        Similar products still available
                    </p>

                    <div class="mt-10 grid grid-cols-2 gap-6 lg:grid-cols-4">
                        @foreach ($suggestedProducts as $suggested)
                            @php
                                $suggestedMedia = $suggested->media->first();
                                $suggestedMediaUrl = $suggestedMedia
                                    ? route('media.show', ['path' => $suggestedMedia->catalog_path ?? $suggestedMedia->path])
                                    : null;
                                $startingPrice = $suggested->variants->min('price');
                            @endphp
                            <a
                                href="{{ route('shop.show', $suggested) }}"
                                class="group flex flex-col overflow-hidden rounded-xl bg-white shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
                            >
                                <div class="relative aspect-square w-full overflow-hidden bg-white">
                                    @if ($suggestedMediaUrl)
                                        <img
                                            src="{{ $suggestedMediaUrl }}"
                                            alt="{{ $suggestedMedia?->alt_text ?: $suggested->name }}"
                                            class="h-full w-full object-contain p-6 transition-transform duration-500 group-hover:scale-105"
                                            loading="lazy"
                                        >
                                    @else
                                        <img
                                            src="{{ asset('images/placeholder-product.svg') }}"
                                            alt="No image available"
                                            class="h-full w-full object-contain p-6"
                                            loading="lazy"
                                        >
                                    @endif
                                </div>

                                <div class="flex flex-1 flex-col p-4">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-black/40">
                                        {{ $suggested->primaryCategory?->name ?? 'Uncategorized' }}
                                    </p>
                                    <h3 class="mt-1.5 line-clamp-2 text-sm font-bold leading-snug text-black">
                                        {{ $suggested->name }}
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
                </div>
            </section>
        @endif

        @include('partials.footer')
    </body>
</html>
