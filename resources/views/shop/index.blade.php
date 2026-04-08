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
    <body class="bg-white text-slate-900">
        @include('partials.header')

        @if (! $showCatalog)
            {{-- Hero section --}}
            <section class="relative flex min-h-screen items-center overflow-hidden bg-slate-900">
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
                        <h1 class="text-4xl font-black uppercase leading-none tracking-tight text-white md:text-5xl lg:text-6xl">
                            Official<br>SnowGoons<br>Gear
                        </h1>
                        <p class="mt-6 text-lg leading-relaxed text-slate-200 md:text-xl">
                            Exclusive merchandise, limited edition vinyl, and official drops from the legendary hip-hop production group. Worldwide shipping available.
                        </p>
                        <div class="mt-8">
                            <a href="{{ route('shop.catalog') }}" class="inline-flex items-center gap-2 rounded border-2 border-white bg-white px-6 py-3 text-sm font-bold uppercase tracking-wider text-slate-900 transition hover:bg-slate-100">
                                Shop Now
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
            </section>

        @endif

        @if (! $showCatalog)
            <livewire:shop-by-artist />
            <livewire:new-arrivals />
        @endif

        @if ($showCatalog)
            <div class="mx-auto max-w-6xl p-6">

                @include('partials.breadcrumb', ['breadcrumbs' => $breadcrumbs])

                <livewire:shop-catalog :forcedCategoryId="$activeCategory?->id" :forcedTagId="$activeTag?->id" />
            </div>
        @endif

        @include('partials.footer')
    </body>
</html>
