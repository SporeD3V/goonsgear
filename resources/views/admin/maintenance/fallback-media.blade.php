@extends('admin.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Fallback Media Maintenance</h2>
        <a href="{{ route('admin.products.index') }}" class="text-sm text-blue-700 hover:underline">Back to Products</a>
    </div>

    <form method="GET" action="{{ route('admin.maintenance.fallback-media.index') }}" class="mb-4 grid gap-3 rounded border border-slate-200 bg-slate-50 p-3 md:grid-cols-5">
        <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-medium text-slate-700">Search</label>
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] }}"
                placeholder="Product, slug, filename, or path"
                class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Optimization</label>
            <select name="optimization" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="all" @selected($filters['optimization'] === 'all')>All</option>
                <option value="missing" @selected($filters['optimization'] === 'missing')>Missing optimized</option>
                <option value="present" @selected($filters['optimization'] === 'present')>Has optimized</option>
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Current Usage</label>
            <select name="usage" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="all" @selected($filters['usage'] === 'all')>All</option>
                <option value="fallback" @selected($filters['usage'] === 'fallback')>Uses fallback</option>
                <option value="webp" @selected($filters['usage'] === 'webp')>Uses WEBP</option>
                <option value="avif" @selected($filters['usage'] === 'avif')>Uses AVIF</option>
                <option value="none" @selected($filters['usage'] === 'none')>Not linked in DB</option>
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Product Mapping</label>
            <select name="product_state" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="all" @selected($filters['product_state'] === 'all')>All</option>
                <option value="known" @selected($filters['product_state'] === 'known')>Known product</option>
                <option value="unknown" @selected($filters['product_state'] === 'unknown')>Unknown product</option>
            </select>
        </div>

        <div class="md:col-span-5 flex items-center gap-2">
            <button type="submit" class="rounded bg-slate-700 px-3 py-2 text-xs text-white hover:bg-slate-800">Apply Filters</button>
            <a href="{{ route('admin.maintenance.fallback-media.index') }}" class="rounded border border-slate-300 px-3 py-2 text-xs text-slate-700 hover:bg-white">Reset</a>
            <span class="text-xs text-slate-500">Showing {{ $entries->firstItem() ?? 0 }}–{{ $entries->lastItem() ?? 0 }} of {{ $entries->total() }} result(s)</span>
        </div>
    </form>

    @if ($entries->isEmpty())
        <p class="rounded border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">No fallback images were found.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full border border-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="border border-slate-200 px-3 py-2 text-left">Fallback Image</th>
                        <th class="border border-slate-200 px-3 py-2 text-left">Product</th>
                        <th class="border border-slate-200 px-3 py-2 text-left">Optimization Status</th>
                        <th class="border border-slate-200 px-3 py-2 text-left">Current Usage</th>
                        <th class="border border-slate-200 px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr>
                            <td class="border border-slate-200 px-3 py-2 align-top">
                                <div class="flex items-start gap-3">
                                    <img src="{{ $entry['fallback_url'] }}" alt="{{ $entry['filename'] }}" loading="lazy" class="h-14 w-14 rounded border border-slate-200 object-cover" onerror="this.onerror=null;this.src='';this.classList.add('bg-slate-100');this.alt='Image not found';this.title='Could not load: '+this.alt;">
                                    <div>
                                        <p class="font-medium text-slate-800">{{ $entry['filename'] }}</p>
                                        <p class="text-xs text-slate-500">{{ $entry['fallback_path'] }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="border border-slate-200 px-3 py-2 align-top">
                                @if ($entry['product'])
                                    <p class="font-medium text-slate-800">{{ $entry['product']->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $entry['product']->slug }}</p>
                                    <a href="{{ route('admin.products.edit', $entry['product']) }}" class="text-xs text-blue-700 hover:underline">Open product</a>
                                @else
                                    <p class="font-medium text-amber-700">Unknown product</p>
                                    <p class="text-xs text-slate-500">Slug: {{ $entry['product_slug'] ?: 'n/a' }}</p>
                                @endif
                            </td>
                            <td class="border border-slate-200 px-3 py-2 align-top">
                                @if ($entry['has_optimized'])
                                    <p class="text-emerald-700">Optimized files exist</p>
                                    <p class="text-xs text-slate-500">{{ implode(', ', $entry['optimized_variants']) }}</p>
                                @else
                                    <p class="text-amber-700">No optimized file yet</p>
                                @endif
                            </td>
                            <td class="border border-slate-200 px-3 py-2 align-top">
                                <p class="text-xs text-slate-700">Uses WEBP: {{ $entry['uses_webp'] ? 'yes' : 'no' }}</p>
                                <p class="text-xs text-slate-700">Uses AVIF: {{ $entry['uses_avif'] ? 'yes' : 'no' }}</p>
                                <p class="text-xs text-slate-700">Uses fallback: {{ $entry['uses_fallback'] ? 'yes' : 'no' }}</p>
                                <p class="text-xs text-slate-500">Matching media rows: {{ $entry['matching_media_count'] }}</p>
                            </td>
                            <td class="border border-slate-200 px-3 py-2 align-top text-right">
                                <div class="flex flex-col items-end gap-2">
                                    <form method="POST" action="{{ route('admin.maintenance.fallback-media.reconvert') }}">
                                        @csrf
                                        <input type="hidden" name="fallback_path" value="{{ $entry['fallback_path'] }}">
                                        <button type="submit" class="rounded bg-blue-600 px-3 py-1.5 text-xs text-white hover:bg-blue-700" {{ $entry['product'] ? '' : 'disabled' }}>
                                            Reconvert &amp; Use
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.maintenance.fallback-media.destroy') }}">
                                        @csrf
                                        <input type="hidden" name="fallback_path" value="{{ $entry['fallback_path'] }}">
                                        <button type="submit" class="rounded bg-red-600 px-3 py-1.5 text-xs text-white hover:bg-red-700" onclick="return confirm('Delete this fallback image?')">
                                            Delete Fallback
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $entries->links() }}
        </div>
    @endif
@endsection
