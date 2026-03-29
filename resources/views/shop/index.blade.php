<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}">
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css'])
        @endif
    </head>
    <body class="bg-slate-100 text-slate-900">
        <div class="mx-auto max-w-6xl p-6">
            <header class="mb-6 flex items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold">{{ $activeCategory?->name ? $activeCategory->name.' | GoonsGear Shop' : 'GoonsGear Shop' }}</h1>
                <a href="{{ url('/') }}" class="text-sm text-blue-700 hover:underline">Home</a>
            </header>

            <form method="GET" action="{{ route('shop.index') }}" class="mb-5 grid gap-3 rounded border border-slate-200 bg-white p-3 md:grid-cols-5">
                <div class="relative md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-slate-700">Search</label>
                    <input
                        type="text"
                        name="q"
                        id="search-input"
                        value="{{ $filters['q'] }}"
                        placeholder="Search name or excerpt"
                        class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                        autocomplete="off"
                        data-search-endpoint="{{ route('api.shop.search') }}"
                    >
                    <div id="search-results" class="absolute left-0 right-0 top-full z-10 mt-1 hidden max-h-64 overflow-y-auto rounded border border-slate-300 bg-white shadow-lg"></div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Category</label>
                    <select name="category" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All categories</option>
                        @foreach ($shopCategories as $shopCategory)
                            <option value="{{ $shopCategory->slug }}" @selected($filters['category'] === $shopCategory->slug)>{{ $shopCategory->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Sort</label>
                    <select name="sort" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        <option value="newest" @selected($filters['sort'] === 'newest')>Newest</option>
                        <option value="name_asc" @selected($filters['sort'] === 'name_asc')>Name A-Z</option>
                        <option value="name_desc" @selected($filters['sort'] === 'name_desc')>Name Z-A</option>
                        <option value="price_asc" @selected($filters['sort'] === 'price_asc')>Price low-high</option>
                        <option value="price_desc" @selected($filters['sort'] === 'price_desc')>Price high-low</option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded bg-slate-700 px-3 py-2 text-sm text-white hover:bg-slate-800">Filter</button>
                    <a href="{{ route('shop.index') }}" class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Reset</a>
                </div>
            </form>

            @if ($shopCategories->isNotEmpty())
                <div class="mb-5 flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-slate-500">Category pages:</span>
                    @foreach ($shopCategories as $shopCategory)
                        <a href="{{ route('shop.category', $shopCategory) }}" class="rounded border border-slate-300 bg-white px-2 py-1 text-slate-700 hover:bg-slate-50">{{ $shopCategory->name }}</a>
                    @endforeach
                </div>
            @endif

            <p class="mb-4 text-sm text-slate-600">Showing {{ $products->total() }} product(s).</p>

            @if ($products->isEmpty())
                <p class="rounded border border-slate-200 bg-white p-4 text-sm text-slate-600">No active products found.</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($products as $product)
                        @php
                            $primaryMedia = $product->media->first();
                            $mediaUrl = $primaryMedia ? route('media.show', ['path' => $primaryMedia->path]) : null;
                            $startingPrice = $product->min_active_variant_price;
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
                                @if ($startingPrice !== null)
                                    <p class="mt-1 text-sm font-medium text-slate-800">From ${{ number_format((float) $startingPrice, 2) }}</p>
                                @endif
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
