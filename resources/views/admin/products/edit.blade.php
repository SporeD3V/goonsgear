@extends('admin.layout')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="truncate text-lg font-semibold text-stone-800">{{ $product->name }}</h2>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ match ($product->status) {
                        'active' => 'bg-emerald-100 text-emerald-700',
                        'draft' => 'bg-amber-100 text-amber-700',
                        'archived' => 'bg-stone-200 text-stone-600',
                        default => 'bg-stone-100 text-stone-500',
                    } }}">{{ ucfirst($product->status) }}</span>
                </div>
                <p class="text-[13px] text-stone-500">
                    <span class="font-mono">{{ $product->slug }}</span>
                    @if ($product->status === 'active')
                        &middot; <a href="{{ route('shop.show', $product) }}" target="_blank" rel="noreferrer" class="text-[#36a2eb] hover:underline">View in shop ↗</a>
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.products.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-stone-500 transition hover:bg-stone-100 hover:text-stone-700">
                    &larr; Back to Products
                </a>
                <button type="submit" form="product-form" class="rounded-lg bg-[#36a2eb] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b8ac9]">
                    Save changes
                </button>
            </div>
        </div>

        @include('admin.products._form', ['product' => $product])

        {{-- Current Media --}}
        @if ($product->media->isNotEmpty())
            @php
                $primaryMedia = $product->media->first();
            @endphp

            <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="5" data-media-gallery>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-stone-600">Current Media</h3>
                    @if ($product->variants->isNotEmpty())
                        <select data-media-variant-filter class="rounded-lg border border-stone-200 px-3 py-1.5 text-sm text-stone-600 focus:border-[#36a2eb] focus:outline-none">
                            <option value="all">All variants</option>
                            @foreach ($product->variants as $variant)
                                <option value="{{ $variant->id }}">{{ $variant->name }} ({{ $variant->sku }})</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                <div class="mb-4 overflow-hidden rounded-xl border border-stone-100 bg-stone-50">
                    @if ($primaryMedia !== null)
                        @php
                            $primaryMediaUrl = route('media.show', ['path' => $primaryMedia->path]);
                            $isPrimaryVideo = str_starts_with((string) $primaryMedia->mime_type, 'video/');
                        @endphp

                        <img
                            data-media-main-image
                            src="{{ $isPrimaryVideo ? '' : $primaryMediaUrl }}"
                            alt="{{ $primaryMedia->alt_text ?: $product->name }}"
                            class="{{ $isPrimaryVideo ? 'hidden' : '' }} h-72 w-full object-contain"
                        >

                        <video
                            data-media-main-video
                            controls
                            class="{{ $isPrimaryVideo ? '' : 'hidden' }} h-72 w-full bg-black object-contain"
                            src="{{ $isPrimaryVideo ? $primaryMediaUrl : '' }}"
                        ></video>
                    @else
                        <img src="{{ asset('images/placeholder-product.svg') }}" alt="No image available" class="h-72 w-full object-contain">
                    @endif
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-media-thumbnails>
                    @foreach ($product->media as $media)
                        @php
                            $mediaUrl = route('media.show', ['path' => $media->path]);
                            $isVideo = str_starts_with((string) $media->mime_type, 'video/');
                        @endphp

                        <div
                            class="group rounded-xl border border-stone-200 p-2 transition hover:border-stone-300 hover:shadow-sm"
                            tabindex="0"
                            data-media-thumb
                            data-media-url="{{ $mediaUrl }}"
                            data-media-type="{{ $isVideo ? 'video' : 'image' }}"
                            data-media-variant-id="{{ $media->product_variant_id ?? '' }}"
                            data-media-alt="{{ $media->alt_text ?: $product->name }}"
                        >
                            <div class="relative mb-2">
                                @if ($isVideo)
                                    <div class="flex h-24 items-center justify-center rounded-lg bg-stone-800 text-xs font-semibold text-white">VIDEO</div>
                                @else
                                    <img src="{{ $mediaUrl }}" alt="{{ $media->alt_text ?: $product->name }}" class="h-24 w-full rounded-lg bg-stone-50 object-cover">
                                @endif
                                @if ($media->is_primary)
                                    <span class="absolute left-1.5 top-1.5 rounded-full bg-[#36a2eb] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Primary</span>
                                @endif
                            </div>

                            <p class="truncate text-xs text-stone-600">{{ $media->alt_text ?: 'No alt text' }}</p>
                            <p class="mt-0.5 text-[11px] text-stone-400">
                                {{ $media->variant?->name ?? 'All variants' }}
                                &middot; <span class="{{ $media->is_converted ? 'text-emerald-600' : 'text-amber-600' }}">{{ $media->is_converted ? strtoupper((string) $media->converted_to) : 'Original' }}</span>
                            </p>

                            <div class="mt-2 flex items-center gap-1">
                                @if (! $media->is_primary)
                                    <form method="POST" action="{{ route('admin.products.media.primary', [$product, $media]) }}">
                                        @csrf
                                        <button type="submit" class="flex h-8 w-8 items-center justify-center rounded-lg text-stone-400 transition hover:bg-amber-50 hover:text-amber-500" title="Set as primary">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/></svg>
                                        </button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('admin.products.media.destroy', [$product, $media]) }}">
                                    @csrf
                                    <button type="submit" class="flex h-8 w-8 items-center justify-center rounded-lg text-stone-400 transition hover:bg-red-50 hover:text-red-600" title="Delete" onclick="return confirm('Delete this media item?')">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="5">
                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-stone-600">Current Media</h3>
                <p class="text-sm text-stone-400">No media uploaded yet — use the upload card above.</p>
            </div>
        @endif

        {{-- Variants --}}
        <div class="admin-card overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm" data-delay="6">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 px-5 py-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-stone-600">Variants</h3>
                <a href="{{ route('admin.products.variants.create', $product) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-[#36a2eb] px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b8ac9]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Add Variant
                </a>
            </div>

            <ul class="divide-y divide-stone-100">
                @forelse ($product->variants as $variant)
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3 transition hover:bg-stone-50/60">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-stone-800">{{ $variant->name }}</p>
                            <p class="mt-0.5 font-mono text-xs text-stone-400">{{ $variant->sku }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-stone-700">&euro;{{ number_format((float) $variant->price, 2) }}</span>
                            @php
                                $stockTone = $variant->stock_quantity === 0
                                    ? 'bg-red-100 text-red-700'
                                    : ($variant->stock_quantity <= 5 ? 'bg-amber-100 text-amber-700' : 'bg-stone-100 text-stone-600');
                            @endphp
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $stockTone }}">
                                {{ $variant->stock_quantity === 0 ? 'Out of stock' : $variant->stock_quantity.' in stock' }}
                            </span>
                            @if ($variant->is_preorder)
                                <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700">Pre-order</span>
                            @endif
                            @unless ($variant->is_active)
                                <span class="rounded-full bg-stone-200 px-2.5 py-0.5 text-xs font-semibold text-stone-600">Inactive</span>
                            @endunless
                        </div>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('admin.products.variants.edit', [$product, $variant]) }}" class="flex h-9 w-9 items-center justify-center rounded-lg text-stone-500 transition hover:bg-[#36a2eb]/10 hover:text-[#36a2eb]" title="Edit variant">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/></svg>
                            </a>
                            <form method="POST" action="{{ route('admin.products.variants.destroy', [$product, $variant]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="flex h-9 w-9 items-center justify-center rounded-lg text-stone-500 transition hover:bg-red-50 hover:text-red-600" title="Delete variant" onclick="return confirm('Delete this variant?')">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                </button>
                            </form>
                        </div>
                    </li>
                @empty
                    <li class="px-6 py-10 text-center">
                        <p class="text-sm text-stone-500">No variants yet — the product can't be purchased until it has at least one.</p>
                        <a href="{{ route('admin.products.variants.create', $product) }}" class="mt-3 inline-block rounded-lg bg-[#36a2eb] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#2b8ac9]">Add the first variant</a>
                    </li>
                @endforelse
            </ul>
        </div>

        {{-- Edit History --}}
        <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="7">
            @include('admin.partials.edit-history', ['histories' => $editHistories])
        </div>
    </div>
@endsection
