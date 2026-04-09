<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}">
        @include('partials.favicons')
        <link rel="canonical" href="{{ $seo['canonical_url'] }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $seo['title'] }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:url" content="{{ $seo['canonical_url'] }}">
        <meta property="og:site_name" content="GoonsGear">
        @if ($seo['og_image'])
            <meta property="og:image" content="{{ $seo['og_image'] }}">
        @endif
        @if (! ($showCatalog ?? false))
            <script type="application/ld+json">{!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => 'GoonsGear',
                'url' => route('shop.index'),
                'logo' => asset('images/goonsgear-logo.avif'),
                'description' => $seo['description'],
            ], JSON_UNESCAPED_SLASHES) !!}</script>
        @endif
        @if ($activeCategory ?? false)
            <script type="application/ld+json">{!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $activeCategory->name,
                'description' => $seo['description'],
                'url' => $seo['canonical_url'],
                'isPartOf' => ['@type' => 'WebSite', 'name' => 'GoonsGear', 'url' => route('shop.index')],
            ], JSON_UNESCAPED_SLASHES) !!}</script>
        @endif
        @if ($activeTag ?? false)
            <script type="application/ld+json">{!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $activeTag->name,
                'description' => $seo['description'],
                'url' => $seo['canonical_url'],
                'isPartOf' => ['@type' => 'WebSite', 'name' => 'GoonsGear', 'url' => route('shop.index')],
            ], JSON_UNESCAPED_SLASHES) !!}</script>
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
    </head>
    <body class="bg-white text-black">
        @include('partials.header')

        @if (! $showCatalog)
            {{-- Hero section --}}
            <section class="relative flex min-h-screen items-center overflow-hidden bg-black shadow-[0_30px_80px_-10px_rgba(0,0,0,0.5)]">
                <div class="pointer-events-none absolute inset-x-0 bottom-0 z-20 h-40 bg-gradient-to-t from-black/70 to-transparent"></div>
                <picture>
                    <source srcset="{{ asset('images/hero-goonsgear.avif') }}" type="image/avif">
                    <img
                        src="{{ asset('images/hero-goonsgear.jpg') }}"
                        alt="SnowGoons Gear"
                        class="absolute inset-0 h-full w-full object-cover"
                        width="1920"
                        height="1080"
                        fetchpriority="high"
                    >
                </picture>
                <div class="absolute inset-0 bg-black/50"></div>
                <div class="relative z-10 mx-auto w-full max-w-6xl px-6 py-16 lg:py-24">
                    <div class="max-w-xl">
                        <h1 class="text-4xl font-black uppercase leading-none tracking-wide text-white md:text-5xl lg:text-6xl">
                            Official<br>SnowGoons<br>Gear
                        </h1>
                        <p class="mt-6 text-lg leading-relaxed text-white/80 md:text-xl">
                            Exclusive merchandise, limited edition vinyl, and official drops from the legendary hip-hop production group. Worldwide shipping available.
                        </p>
                        <div class="mt-8">
                            <a href="{{ route('shop.catalog') }}" class="inline-flex items-center gap-2 rounded border-2 border-white bg-white px-6 py-3 text-sm font-bold uppercase tracking-wider text-black transition hover:bg-white/90">
                                Shop Now
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                            </a>
                        </div>
                    </div>

                    {{-- Feature cards --}}
                    <div class="mt-12 grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <div class="group flex flex-col items-center rounded-lg bg-white/10 px-4 py-6 text-center backdrop-blur-sm">
                            <picture>
                                <source srcset="{{ asset('images/worldwide-shipping-icon.avif') }}" type="image/avif">
                                <img src="{{ asset('images/worldwide-shipping-icon.png') }}" alt="Globe icon representing worldwide shipping" class="mb-3 h-10 w-10 brightness-0 invert transition-transform duration-300 ease-out group-hover:scale-110" width="80" height="80" loading="lazy">
                            </picture>
                            <h3 class="text-base font-bold uppercase tracking-wide text-white">Worldwide Shipping</h3>
                            <p class="mt-1 text-sm leading-relaxed text-white/70">We do worldwide shipping with traceable tracking number.</p>
                        </div>

                        <div class="group flex flex-col items-center rounded-lg bg-white/10 px-4 py-6 text-center backdrop-blur-sm">
                            <picture>
                                <source srcset="{{ asset('images/vinyl-record-icon.avif') }}" type="image/avif">
                                <img src="{{ asset('images/vinyl-record-icon.png') }}" alt="Vinyl record icon representing exclusive vinyl collection" class="mb-3 h-10 w-10 brightness-0 invert transition-transform duration-300 ease-out group-hover:scale-110" width="80" height="80" loading="lazy">
                            </picture>
                            <h3 class="text-base font-bold uppercase tracking-wide text-white">Sick Vinyl</h3>
                            <p class="mt-1 text-sm leading-relaxed text-white/70">We got a lot of exclusive vinyl and only ship with premium boxes.</p>
                        </div>

                        <div class="group flex flex-col items-center rounded-lg bg-white/10 px-4 py-6 text-center backdrop-blur-sm">
                            <picture>
                                <source srcset="{{ asset('images/wholesale-price-tag-icon.avif') }}" type="image/avif">
                                <img src="{{ asset('images/wholesale-price-tag-icon.png') }}" alt="Price tag icon representing wholesale orders" class="mb-3 h-10 w-10 brightness-0 invert transition-transform duration-300 ease-out group-hover:scale-110" width="80" height="80" loading="lazy">
                            </picture>
                            <h3 class="text-base font-bold uppercase tracking-wide text-white">Wholesale</h3>
                            <p class="mt-1 text-sm leading-relaxed text-white/70">For wholesale and shop orders please contact us.</p>
                        </div>

                        <div class="group flex flex-col items-center rounded-lg bg-white/10 px-4 py-6 text-center backdrop-blur-sm">
                            <picture>
                                <source srcset="{{ asset('images/secure-payment-lock-icon.avif') }}" type="image/avif">
                                <img src="{{ asset('images/secure-payment-lock-icon.png') }}" alt="Padlock icon representing secure PayPal payments" class="mb-3 h-10 w-10 brightness-0 invert transition-transform duration-300 ease-out group-hover:scale-110" width="80" height="80" loading="lazy">
                            </picture>
                            <h3 class="text-base font-bold uppercase tracking-wide text-white">Secure Payments</h3>
                            <p class="mt-1 text-sm leading-relaxed text-white/70">We use PayPal Plus for your secure payments with all possible options.</p>
                        </div>
                    </div>
                </div>
            </section>

        @endif

        @if (! $showCatalog)
            <livewire:shop-by-artist />
            <livewire:new-arrivals />
            <livewire:bundle-highlights />
            <livewire:newsletter />
        @endif

        @if ($showCatalog)
            <div class="mx-auto max-w-6xl p-6">

                @include('partials.breadcrumb', ['breadcrumbs' => $breadcrumbs])

                <livewire:shop-catalog :forcedCategoryId="$activeCategory?->id" :forcedTagId="$activeTag?->id" />
            </div>
        @endif

        @include('partials.footer', ['light' => !$showCatalog])
    </body>
</html>
