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
    <body class="bg-slate-100 text-slate-900">
        @include('partials.header')

        <div class="mx-auto max-w-6xl p-6">

            @include('partials.breadcrumb', ['breadcrumbs' => $breadcrumbs])

            <livewire:shop-catalog :forcedCategoryId="$activeCategory?->id" :forcedTagId="$activeTag?->id" />
        </div>

        @include('partials.footer')
    </body>
</html>
