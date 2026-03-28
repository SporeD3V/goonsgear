<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Shop | GoonsGear</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css'])
        @endif
    </head>
    <body class="bg-slate-100 text-slate-900">
        <div class="mx-auto max-w-6xl p-6">
            <header class="mb-6 flex items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold">GoonsGear Shop</h1>
                <a href="{{ url('/') }}" class="text-sm text-blue-700 hover:underline">Home</a>
            </header>

            @if ($products->isEmpty())
                <p class="rounded border border-slate-200 bg-white p-4 text-sm text-slate-600">No active products found.</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($products as $product)
                        @php
                            $primaryMedia = $product->media->first();
                            $mediaUrl = $primaryMedia ? route('media.show', ['path' => $primaryMedia->path]) : null;
                        @endphp

                        <article class="rounded border border-slate-200 bg-white p-4 shadow-sm">
                            <a href="{{ route('shop.show', $product) }}" class="block">
                                @if ($mediaUrl)
                                    <img src="{{ $mediaUrl }}" alt="{{ $primaryMedia?->alt_text ?: $product->name }}" class="mb-3 h-52 w-full rounded object-cover">
                                @else
                                    <div class="mb-3 flex h-52 items-center justify-center rounded bg-slate-100 text-sm text-slate-500">No image</div>
                                @endif
                                <h2 class="text-lg font-semibold">{{ $product->name }}</h2>
                                <p class="mt-1 text-sm text-slate-600">{{ $product->primaryCategory?->name ?? 'Uncategorized' }}</p>
                                @if ($product->excerpt)
                                    <p class="mt-2 text-sm text-slate-700">{{ $product->excerpt }}</p>
                                @endif
                            </a>
                        </article>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    </body>
</html>
