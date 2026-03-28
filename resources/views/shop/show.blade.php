<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $product->name }} | GoonsGear</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css'])
        @endif
    </head>
    <body class="bg-slate-100 text-slate-900">
        <div class="mx-auto max-w-6xl p-6">
            <header class="mb-6 flex items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold">{{ $product->name }}</h1>
                <a href="{{ route('shop.index') }}" class="text-sm text-blue-700 hover:underline">Back to shop</a>
            </header>

            <div class="grid gap-6 lg:grid-cols-2">
                <section>
                    @php
                        $primaryMedia = $product->media->first();
                        $primaryMediaUrl = $primaryMedia ? route('media.show', ['path' => $primaryMedia->path]) : null;
                    @endphp

                    @if ($primaryMediaUrl)
                        <img src="{{ $primaryMediaUrl }}" alt="{{ $primaryMedia?->alt_text ?: $product->name }}" class="h-112 w-full rounded border border-slate-200 bg-white object-contain p-2">
                    @else
                        <div class="flex h-112 items-center justify-center rounded border border-slate-200 bg-white text-sm text-slate-500">No media available</div>
                    @endif

                    @if ($product->media->count() > 1)
                        <div class="mt-3 grid grid-cols-4 gap-2">
                            @foreach ($product->media as $media)
                                @php
                                    $thumbnailUrl = route('media.show', ['path' => $media->path]);
                                @endphp
                                <img src="{{ $thumbnailUrl }}" alt="{{ $media->alt_text ?: $product->name }}" class="h-20 w-full rounded border border-slate-200 bg-white object-cover">
                            @endforeach
                        </div>
                    @endif
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
                        @if ($product->variants->isEmpty())
                            <p class="mt-2 text-sm text-slate-600">No active variants available.</p>
                        @else
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
                                        @foreach ($product->variants as $variant)
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
