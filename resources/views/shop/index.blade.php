<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}">
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-white text-slate-900">
        @include('partials.header')

        @if ($activeCategory === null && $activeTag === null)
            {{-- Hero section --}}
            <section class="relative flex min-h-[480px] items-center overflow-hidden bg-slate-900 lg:min-h-[540px]">
                <picture>
                    <source srcset="{{ asset('images/hero-goonsgear.avif') }}" type="image/avif">
                    <img
                        src="{{ asset('images/hero-goonsgear.jpg') }}"
                        alt="SnowGoons Gear"
                        class="absolute inset-0 h-full w-full object-cover"
                        width="1920"
                        height="1080"
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
                        <div class="mt-8 flex flex-wrap gap-4">
                            <a href="{{ route('shop.category', 'wear') }}" class="inline-flex items-center gap-2 rounded border-2 border-white bg-white px-6 py-3 text-sm font-bold uppercase tracking-wider text-slate-900 transition hover:bg-slate-100">
                                Shop Now
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                            </a>
                            <a href="{{ route('shop.index') }}" class="inline-flex items-center gap-2 rounded border-2 border-white/80 px-6 py-3 text-sm font-bold uppercase tracking-wider text-white transition hover:border-white hover:bg-white/10">
                                New Arrivals
                            </a>
                        </div>
                    </div>
                </div>
            </section>

        @endif

        <div class="mx-auto max-w-6xl p-6">

            @include('partials.breadcrumb', ['breadcrumbs' => $breadcrumbs])

            <livewire:shop-catalog :forcedCategoryId="$activeCategory?->id" :forcedTagId="$activeTag?->id" />
        </div>

        @include('partials.footer')
    </body>
</html>
