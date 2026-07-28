<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Server Error | GoonsGear</title>
        @include('partials.favicons')
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css'])
        @endif
    </head>
    <body class="bg-white text-black">
        <div class="flex min-h-screen flex-col items-center justify-center p-6">
            <div class="w-full max-w-md rounded border border-black/10 bg-white p-8 shadow-sm text-center">
                <p class="text-5xl font-bold text-black/20">500</p>
                <h1 class="mt-3 text-xl font-semibold">Something went wrong</h1>
                <p class="mt-2 text-sm text-black/60">We're experiencing a temporary issue. Please try again in a moment.</p>
                <a href="{{ url('/') }}" class="mt-6 inline-block rounded bg-black px-4 py-2 text-sm font-medium text-white transition-opacity hover:opacity-80">
                    Return to homepage
                </a>
            </div>
        </div>
    </body>
</html>
