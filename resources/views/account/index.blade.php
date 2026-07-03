<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>My Account | GoonsGear</title>
        @include('partials.favicons')
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-white text-black">
        @include('partials.header')

        @php
            $user = auth()->user();
            $firstName = \Illuminate\Support\Str::of($user?->name ?? '')->trim()->explode(' ')->first() ?: 'there';

            $sizeOptions = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '2XL', '3XL', '4XL', '5XL'];

            $statusBadge = fn (string $status): string => match ($status) {
                'paid', 'completed', 'delivered' => 'bg-emerald-100 text-emerald-800',
                'processing', 'shipped', 'pre-ordered' => 'bg-blue-100 text-blue-800',
                'pending', 'on-hold' => 'bg-amber-100 text-amber-800',
                'cancelled', 'refunded', 'failed' => 'bg-red-100 text-red-700',
                default => 'bg-black/10 text-black/70',
            };

            $navSections = [
                'orders' => 'My Orders',
                'discounts' => 'Discount Codes',
                'favorites' => 'Favorites',
                'sizes' => 'Size Profiles',
                'address' => 'Delivery Address',
                'notifications' => 'Notifications',
                'profile' => 'Profile & Security',
            ];

            $hasApartmentDetails = collect(['delivery_apartment_block', 'delivery_entrance', 'delivery_floor', 'delivery_apartment_number'])
                ->contains(fn ($field) => filled(old($field, $user?->{$field})));
        @endphp

        <main class="mx-auto max-w-6xl px-6 py-8">

            {{-- Page heading --}}
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-black/40">My Account</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight sm:text-3xl">Welcome back, {{ $firstName }}</h1>
                    <p class="mt-1 text-sm text-black/50">{{ $user?->email }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-black/15 px-4 py-2 text-sm font-medium text-black/70 transition hover:bg-black/5 hover:text-black">
                        Log out
                    </button>
                </form>
            </div>

            @if (session('status'))
                <div class="mt-6 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    {{ session('status') }}
                </div>
            @endif

            <div class="mt-8 grid gap-8 lg:grid-cols-[210px_minmax(0,1fr)]">

                {{-- Section navigation: sticky rail on desktop, scrollable chips on mobile --}}
                <nav aria-label="Account sections" class="lg:sticky lg:top-24 lg:self-start">
                    <ul class="no-scrollbar -mx-6 flex gap-2 overflow-x-auto px-6 lg:mx-0 lg:flex-col lg:gap-1 lg:px-0">
                        @foreach ($navSections as $anchor => $label)
                            <li class="shrink-0">
                                <a href="#{{ $anchor }}"
                                   class="flex items-center gap-2.5 whitespace-nowrap rounded-lg border border-black/10 px-3 py-2 text-sm font-medium text-black/60 transition hover:bg-black/5 hover:text-black lg:border-transparent">
                                    @switch($anchor)
                                        @case('orders')
                                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z"/></svg>
                                            @break
                                        @case('discounts')
                                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-12-.75h14.25A2.25 2.25 0 0 0 21 15v-1.5a1.5 1.5 0 0 1 0-3V9a2.25 2.25 0 0 0-2.25-2.25H4.5A2.25 2.25 0 0 0 2.25 9v1.5a1.5 1.5 0 0 1 0 3V15a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                                            @break
                                        @case('favorites')
                                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/></svg>
                                            @break
                                        @case('sizes')
                                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"/></svg>
                                            @break
                                        @case('address')
                                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                                            @break
                                        @case('profile')
                                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                                            @break
                                        @default
                                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
                                    @endswitch
                                    {{ $label }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <div class="min-w-0 space-y-8">

                    {{-- My Orders --}}
                    <section id="orders" class="scroll-mt-28 rounded-xl border border-black/10 bg-white p-6 shadow-sm">
                        <div class="mb-4">
                            <h2 class="text-lg font-bold tracking-tight">My Orders</h2>
                            <p class="mt-0.5 text-sm text-black/50">Recent orders placed with your account email.</p>
                        </div>

                        @if ($recentOrders->isNotEmpty())
                            <ul class="divide-y divide-black/5">
                                @foreach ($recentOrders as $order)
                                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 py-4 first:pt-0 last:pb-0">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-bold">{{ $order->order_number }}</p>
                                            <p class="mt-0.5 text-xs text-black/50">
                                                {{ optional($order->placed_at)->format('M d, Y') ?? '—' }}
                                                &middot; {{ $order->items_count }} {{ \Illuminate\Support\Str::plural('item', $order->items_count) }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadge($order->status) }}">{{ ucfirst($order->status) }}</span>
                                            @if ($order->payment_status !== $order->status)
                                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadge($order->payment_status) }}">{{ ucfirst($order->payment_status) }}</span>
                                            @endif
                                        </div>
                                        <p class="w-24 text-right text-sm font-bold">
                                            {{ $order->currency === 'EUR' ? '€' : $order->currency.' ' }}{{ number_format((float) $order->total, 2) }}
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="rounded-lg border border-dashed border-black/15 px-6 py-10 text-center">
                                <p class="text-sm font-medium text-black/60">No orders yet.</p>
                                <a href="{{ route('shop.catalog') }}" class="mt-3 inline-block rounded-lg bg-black px-4 py-2 text-xs font-bold uppercase tracking-wider text-white transition hover:bg-black/80">
                                    Browse the shop
                                </a>
                            </div>
                        @endif
                    </section>

                    {{-- My Discount Codes --}}
                    <section id="discounts" class="scroll-mt-28 rounded-xl border border-black/10 bg-white p-6 shadow-sm">
                        <div class="mb-4">
                            <h2 class="text-lg font-bold tracking-tight">My Discount Codes</h2>
                            <p class="mt-0.5 text-sm text-black/50">Active coupons assigned to your account — apply them in your cart.</p>
                        </div>

                        @if ($availableCoupons->isNotEmpty())
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($availableCoupons as $coupon)
                                    <div class="rounded-xl border border-dashed border-black/25 bg-black/[0.02] p-4">
                                        <div class="flex items-start justify-between gap-2">
                                            <code class="text-base font-black tracking-widest">{{ $coupon->code }}</code>
                                            <button
                                                type="button"
                                                x-data="{ copied: false }"
                                                x-on:click="navigator.clipboard?.writeText(@js($coupon->code)); copied = true; setTimeout(() => copied = false, 1500)"
                                                class="shrink-0 rounded-md border border-black/15 px-2 py-1 text-xs font-medium text-black/60 transition hover:bg-black/5 hover:text-black"
                                            >
                                                <span x-show="!copied">Copy</span>
                                                <span x-show="copied" x-cloak class="text-emerald-700">Copied!</span>
                                            </button>
                                        </div>
                                        <p class="mt-1.5 text-sm font-bold text-red-600">
                                            @if ($coupon->type === \App\Models\Coupon::TYPE_PERCENT)
                                                {{ rtrim(rtrim(number_format((float) $coupon->value, 2), '0'), '.') }}% off
                                            @else
                                                €{{ number_format((float) $coupon->value, 2) }} off
                                            @endif
                                        </p>
                                        <p class="mt-1 text-xs text-black/50">
                                            {{ $coupon->is_stackable ? 'Can be combined' : 'Cannot be combined' }}
                                            &middot; {{ ucfirst($coupon->scope_type ?: \App\Models\Coupon::SCOPE_ALL) === 'All' ? 'Whole shop' : ucfirst($coupon->scope_type).' only' }}
                                            &middot;
                                            @if ($coupon->pivot->usage_limit !== null)
                                                {{ max(0, (int) $coupon->pivot->usage_limit - (int) $coupon->pivot->used_count) }} {{ \Illuminate\Support\Str::plural('use', max(0, (int) $coupon->pivot->usage_limit - (int) $coupon->pivot->used_count)) }} left
                                            @else
                                                Unlimited uses
                                            @endif
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="rounded-lg border border-dashed border-black/15 px-6 py-8 text-center">
                                <p class="text-sm text-black/50">No active discount codes are assigned to your account.</p>
                            </div>
                        @endif
                    </section>

                    {{-- My Favorites --}}
                    <section id="favorites" class="scroll-mt-28 rounded-xl border border-black/10 bg-white p-6 shadow-sm">
                        <div class="mb-4">
                            <h2 class="text-lg font-bold tracking-tight">My Favorites</h2>
                            <p class="mt-0.5 text-sm text-black/50">Follow artists, brands, and tags — get emails for new drops and discounts.</p>
                        </div>

                        @if ($availableTags->isNotEmpty())
                            <form method="POST" action="{{ route('account.tag-follows.store') }}" class="rounded-lg bg-black/[0.03] p-4">
                                @csrf
                                <div class="grid gap-4 md:grid-cols-[1fr_auto_auto] md:items-end">
                                    <div>
                                        <label for="follow-tag" class="mb-1 block text-sm font-medium text-black/70">Follow an artist, brand, or tag</label>
                                        <select id="follow-tag" name="tag_id" class="w-full rounded-lg border border-black/15 bg-white px-3 py-2 text-sm focus:border-black focus:outline-none" required>
                                            <option value="">Select one...</option>
                                            @foreach (['artist' => 'Artists', 'brand' => 'Brands', 'custom' => 'Custom Tags'] as $type => $label)
                                                @php $grouped = $availableTags->where('type', $type); @endphp
                                                @if ($grouped->isNotEmpty())
                                                    <optgroup label="{{ $label }}">
                                                        @foreach ($grouped as $tag)
                                                            <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                                                        @endforeach
                                                    </optgroup>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <label class="flex cursor-pointer items-center gap-2 text-sm text-black/70">
                                            <input type="checkbox" name="notify_new_drops" value="1" checked class="h-4 w-4 rounded border-black/20 text-black focus:ring-black">
                                            New drops
                                        </label>
                                        <label class="flex cursor-pointer items-center gap-2 text-sm text-black/70">
                                            <input type="checkbox" name="notify_discounts" value="1" checked class="h-4 w-4 rounded border-black/20 text-black focus:ring-black">
                                            Discounts
                                        </label>
                                    </div>

                                    <button type="submit" class="rounded-lg bg-black px-5 py-2 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-black/80">
                                        Follow
                                    </button>
                                </div>
                            </form>
                        @endif

                        @php
                            $followsByType = $tagFollows->getCollection()->groupBy(fn ($f) => $f->tag->type);
                            $typeLabels = ['artist' => 'Artists', 'brand' => 'Brands', 'custom' => 'Custom Tags'];
                        @endphp

                        @foreach ($typeLabels as $type => $label)
                            @if (($followsByType[$type] ?? collect())->isNotEmpty())
                                <h3 class="mt-6 text-xs font-bold uppercase tracking-wider text-black/40">{{ $label }}</h3>
                                <div class="mt-2 space-y-2">
                                    @foreach ($followsByType[$type] as $tagFollow)
                                        <article class="rounded-lg border border-black/10 p-4">
                                            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                                                <div class="flex items-center gap-2">
                                                    <h4 class="text-sm font-bold">{{ $tagFollow->tag->name }}</h4>
                                                    @if (! $tagFollow->tag->is_active)
                                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Inactive</span>
                                                    @endif
                                                </div>
                                                <form method="POST" action="{{ route('account.tag-follows.destroy', $tagFollow) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline" onclick="return confirm('Remove this favorite?')">Unfollow</button>
                                                </form>
                                            </div>

                                            <form method="POST" action="{{ route('account.tag-follows.update', $tagFollow) }}" class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2">
                                                @csrf
                                                @method('PATCH')

                                                <label class="flex cursor-pointer items-center gap-2 text-sm text-black/70">
                                                    <input type="checkbox" name="notify_new_drops" value="1" @checked($tagFollow->notify_new_drops) class="h-4 w-4 rounded border-black/20 text-black focus:ring-black">
                                                    New drops
                                                </label>

                                                <label class="flex cursor-pointer items-center gap-2 text-sm text-black/70">
                                                    <input type="checkbox" name="notify_discounts" value="1" @checked($tagFollow->notify_discounts) class="h-4 w-4 rounded border-black/20 text-black focus:ring-black">
                                                    Discounts
                                                </label>

                                                <button type="submit" class="rounded-lg border border-black/15 px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-black/70 transition hover:bg-black hover:text-white">
                                                    Save
                                                </button>
                                            </form>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach

                        @if ($tagFollows->isEmpty())
                            <div class="mt-4 rounded-lg border border-dashed border-black/15 px-6 py-8 text-center">
                                <p class="text-sm text-black/50">You are not following any artists, brands, or tags yet.</p>
                            </div>
                        @endif

                        @if ($tagFollows->hasPages())
                            <div class="mt-4">
                                {{ $tagFollows->links() }}
                            </div>
                        @endif
                    </section>

                    {{-- Size Profiles --}}
                    <section id="sizes" class="scroll-mt-28 rounded-xl border border-black/10 bg-white p-6 shadow-sm">
                        <div class="mb-4">
                            <h2 class="text-lg font-bold tracking-tight">Size Profiles</h2>
                            <p class="mt-0.5 text-sm text-black/50">Save your sizes and sizes for people you buy for — then filter the shop by size.</p>
                        </div>

                        @if ($sizeProfiles->isNotEmpty())
                            <div class="space-y-3">
                                @foreach ($sizeProfiles as $profile)
                                    <div class="rounded-lg border border-black/10 p-4">
                                        <form method="POST" action="{{ route('account.size-profiles.update', $profile) }}">
                                            @csrf
                                            @method('PATCH')

                                            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                                                <div class="flex items-center gap-2">
                                                    <input
                                                        type="text"
                                                        name="name"
                                                        value="{{ old('name', $profile->name) }}"
                                                        class="rounded-lg border border-black/15 px-3 py-1.5 text-sm font-bold focus:border-black focus:outline-none"
                                                        required
                                                    >
                                                    @if ($profile->is_self)
                                                        <span class="rounded-full bg-black px-2.5 py-0.5 text-xs font-bold text-white">You</span>
                                                    @endif
                                                </div>
                                                @unless ($profile->is_self)
                                                    <button type="submit"
                                                            form="remove-profile-{{ $profile->id }}"
                                                            class="text-xs font-medium text-red-600 hover:underline"
                                                            onclick="return confirm('Remove this person?')">
                                                        Remove
                                                    </button>
                                                @endunless
                                            </div>

                                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-black/50">Top size</label>
                                                    <select name="top_size" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none">
                                                        <option value="">—</option>
                                                        @foreach ($sizeOptions as $size)
                                                            <option value="{{ $size }}" @selected(old('top_size', $profile->top_size) === $size)>{{ $size }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-black/50">Bottom size</label>
                                                    <select name="bottom_size" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none">
                                                        <option value="">—</option>
                                                        @foreach ($sizeOptions as $size)
                                                            <option value="{{ $size }}" @selected(old('bottom_size', $profile->bottom_size) === $size)>{{ $size }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-black/50">Shoe size</label>
                                                    <input type="text" name="shoe_size" value="{{ old('shoe_size', $profile->shoe_size) }}" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none" placeholder="e.g. 42">
                                                </div>
                                            </div>

                                            <div class="mt-3">
                                                <button type="submit" class="rounded-lg border border-black/15 px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-black/70 transition hover:bg-black hover:text-white">
                                                    Save
                                                </button>
                                            </div>
                                        </form>

                                        @unless ($profile->is_self)
                                            <form id="remove-profile-{{ $profile->id }}" method="POST" action="{{ route('account.size-profiles.destroy', $profile) }}">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                        @endunless
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if (!$sizeProfiles->where('is_self', true)->count())
                            <div class="mt-4 rounded-lg border border-dashed border-black/20 bg-black/[0.02] p-4">
                                <h3 class="text-sm font-bold">Add your sizes</h3>
                                <form method="POST" action="{{ route('account.size-profiles.store') }}" class="mt-3">
                                    @csrf
                                    <input type="hidden" name="is_self" value="1">
                                    <input type="hidden" name="name" value="{{ $user?->name ?? 'Me' }}">

                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-black/50">Top size</label>
                                            <select name="top_size" class="w-full rounded-lg border border-black/15 bg-white px-3 py-2 text-sm focus:border-black focus:outline-none">
                                                <option value="">—</option>
                                                @foreach ($sizeOptions as $size)
                                                    <option value="{{ $size }}">{{ $size }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-black/50">Bottom size</label>
                                            <select name="bottom_size" class="w-full rounded-lg border border-black/15 bg-white px-3 py-2 text-sm focus:border-black focus:outline-none">
                                                <option value="">—</option>
                                                @foreach ($sizeOptions as $size)
                                                    <option value="{{ $size }}">{{ $size }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-black/50">Shoe size</label>
                                            <input type="text" name="shoe_size" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none" placeholder="e.g. 42">
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <button type="submit" class="rounded-lg bg-black px-4 py-2 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-black/80">Save my sizes</button>
                                    </div>
                                </form>
                            </div>
                        @endif

                        <details class="group mt-4 rounded-lg border border-black/10">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 p-4 text-sm font-bold text-black/70 transition hover:text-black [&::-webkit-details-marker]:hidden">
                                Add another person
                                <svg class="h-4 w-4 shrink-0 transition-transform group-open:rotate-45" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            </summary>
                            <form method="POST" action="{{ route('account.size-profiles.store') }}" class="border-t border-black/10 p-4">
                                @csrf
                                <div class="mb-3">
                                    <label class="mb-1 block text-xs font-medium text-black/50">Name</label>
                                    <input type="text" name="name" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none sm:max-w-xs" placeholder="e.g. John" required>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-black/50">Top size</label>
                                        <select name="top_size" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none">
                                            <option value="">—</option>
                                            @foreach ($sizeOptions as $size)
                                                <option value="{{ $size }}">{{ $size }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-black/50">Bottom size</label>
                                        <select name="bottom_size" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none">
                                            <option value="">—</option>
                                            @foreach ($sizeOptions as $size)
                                                <option value="{{ $size }}">{{ $size }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-black/50">Shoe size</label>
                                        <input type="text" name="shoe_size" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none" placeholder="e.g. 42">
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" class="rounded-lg bg-black px-4 py-2 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-black/80">Add person</button>
                                </div>
                            </form>
                        </details>
                    </section>

                    {{-- Saved Delivery Address --}}
                    <section id="address" class="scroll-mt-28 rounded-xl border border-black/10 bg-white p-6 shadow-sm">
                        <div class="mb-4">
                            <h2 class="text-lg font-bold tracking-tight">Saved Delivery Address</h2>
                            <p class="mt-0.5 text-sm text-black/50">Pre-filled at checkout when you are logged in.</p>
                        </div>

                        <form method="POST" action="{{ route('account.delivery-address.update') }}" class="space-y-4">
                            @csrf
                            @method('PATCH')

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="delivery_phone" class="mb-1 block text-sm font-medium text-black/70">Phone</label>
                                    <input id="delivery_phone" type="text" name="delivery_phone" value="{{ old('delivery_phone', $user?->delivery_phone) }}" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none">
                                    @error('delivery_phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="delivery_country" class="mb-1 block text-sm font-medium text-black/70">Country code</label>
                                    <input id="delivery_country" type="text" name="delivery_country" maxlength="2" value="{{ old('delivery_country', $user?->delivery_country) }}" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm uppercase focus:border-black focus:outline-none" placeholder="DE">
                                    @error('delivery_country')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label for="delivery_state" class="mb-1 block text-sm font-medium text-black/70">State / Region</label>
                                    <input id="delivery_state" type="text" name="delivery_state" value="{{ old('delivery_state', $user?->delivery_state) }}" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none">
                                </div>
                                <div>
                                    <label for="delivery_city" class="mb-1 block text-sm font-medium text-black/70">City</label>
                                    <input id="delivery_city" type="text" name="delivery_city" value="{{ old('delivery_city', $user?->delivery_city) }}" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none">
                                </div>
                                <div>
                                    <label for="delivery_postal_code" class="mb-1 block text-sm font-medium text-black/70">Postal code</label>
                                    <input id="delivery_postal_code" type="text" name="delivery_postal_code" value="{{ old('delivery_postal_code', $user?->delivery_postal_code) }}" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none">
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-[1fr_140px]">
                                <div>
                                    <label for="delivery_street_name" class="mb-1 block text-sm font-medium text-black/70">Street name</label>
                                    <input id="delivery_street_name" type="text" name="delivery_street_name" value="{{ old('delivery_street_name', $user?->delivery_street_name) }}" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none">
                                </div>
                                <div>
                                    <label for="delivery_street_number" class="mb-1 block text-sm font-medium text-black/70">Number</label>
                                    <input id="delivery_street_number" type="text" name="delivery_street_number" value="{{ old('delivery_street_number', $user?->delivery_street_number) }}" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none">
                                </div>
                            </div>

                            <details class="group rounded-lg border border-black/10" @if ($hasApartmentDetails) open @endif>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-2 p-3 text-sm font-medium text-black/60 transition hover:text-black [&::-webkit-details-marker]:hidden">
                                    Apartment details (optional)
                                    <svg class="h-4 w-4 shrink-0 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                                </summary>
                                <div class="grid gap-4 border-t border-black/10 p-3 sm:grid-cols-2">
                                    <div>
                                        <label for="delivery_apartment_block" class="mb-1 block text-sm font-medium text-black/70">Apartment block</label>
                                        <input id="delivery_apartment_block" type="text" name="delivery_apartment_block" value="{{ old('delivery_apartment_block', $user?->delivery_apartment_block) }}" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none">
                                    </div>
                                    <div>
                                        <label for="delivery_entrance" class="mb-1 block text-sm font-medium text-black/70">Entrance</label>
                                        <input id="delivery_entrance" type="text" name="delivery_entrance" value="{{ old('delivery_entrance', $user?->delivery_entrance) }}" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none">
                                    </div>
                                    <div>
                                        <label for="delivery_floor" class="mb-1 block text-sm font-medium text-black/70">Floor</label>
                                        <input id="delivery_floor" type="text" name="delivery_floor" value="{{ old('delivery_floor', $user?->delivery_floor) }}" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none">
                                    </div>
                                    <div>
                                        <label for="delivery_apartment_number" class="mb-1 block text-sm font-medium text-black/70">Apartment number</label>
                                        <input id="delivery_apartment_number" type="text" name="delivery_apartment_number" value="{{ old('delivery_apartment_number', $user?->delivery_apartment_number) }}" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none">
                                    </div>
                                </div>
                            </details>

                            <div>
                                <button type="submit" class="rounded-lg bg-black px-5 py-2 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-black/80">Save address</button>
                            </div>
                        </form>
                    </section>

                    {{-- Email Notifications --}}
                    <section id="notifications" class="scroll-mt-28 rounded-xl border border-black/10 bg-white p-6 shadow-sm">
                        <div class="mb-4">
                            <h2 class="text-lg font-bold tracking-tight">Email Notifications</h2>
                            <p class="mt-0.5 text-sm text-black/50">Choose which emails you'd like to receive about items in your cart.</p>
                        </div>

                        <form method="POST" action="{{ route('account.email-preferences.update') }}">
                            @csrf
                            @method('PATCH')

                            <fieldset class="divide-y divide-black/5">
                                <legend class="sr-only">Email notification preferences</legend>

                                <label class="flex cursor-pointer items-center justify-between gap-4 py-4">
                                    <span class="text-sm">
                                        <span class="font-bold">Price drops on cart items</span><br>
                                        <span class="text-black/50">Get notified when the price of an item in your cart is reduced.</span>
                                    </span>
                                    <input
                                        type="checkbox"
                                        name="notify_cart_discounts"
                                        value="1"
                                        class="peer sr-only"
                                        {{ $user?->notify_cart_discounts ? 'checked' : '' }}
                                    >
                                    <span aria-hidden="true" class="relative h-6 w-11 shrink-0 rounded-full bg-black/20 transition after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:bg-black peer-checked:after:translate-x-5"></span>
                                </label>

                                <label class="flex cursor-pointer items-center justify-between gap-4 py-4">
                                    <span class="text-sm">
                                        <span class="font-bold">Low stock alerts</span><br>
                                        <span class="text-black/50">Get notified when an item in your cart is running low on stock.</span>
                                    </span>
                                    <input
                                        type="checkbox"
                                        name="notify_cart_low_stock"
                                        value="1"
                                        class="peer sr-only"
                                        {{ $user?->notify_cart_low_stock ? 'checked' : '' }}
                                    >
                                    <span aria-hidden="true" class="relative h-6 w-11 shrink-0 rounded-full bg-black/20 transition after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:bg-black peer-checked:after:translate-x-5"></span>
                                </label>
                            </fieldset>

                            <div class="mt-4">
                                <button type="submit" class="rounded-lg bg-black px-5 py-2 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-black/80">
                                    Save preferences
                                </button>
                            </div>
                        </form>
                    </section>

                    {{-- Profile & Security --}}
                    <section id="profile" class="scroll-mt-28 rounded-xl border border-black/10 bg-white p-6 shadow-sm">
                        <div class="mb-4">
                            <h2 class="text-lg font-bold tracking-tight">Profile &amp; Security</h2>
                            <p class="mt-0.5 text-sm text-black/50">Update your name or change your password.</p>
                        </div>

                        <form method="POST" action="{{ route('account.profile.update') }}">
                            @csrf
                            @method('PATCH')

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="profile_name" class="mb-1 block text-sm font-medium text-black/70">Name</label>
                                    <input id="profile_name" type="text" name="name" value="{{ old('name', $user?->name) }}" required autocomplete="name" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none">
                                    @error('name', 'updateProfile')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-black/70">Email</label>
                                    <p class="w-full rounded-lg border border-black/10 bg-black/[0.03] px-3 py-2 text-sm text-black/60">{{ $user?->email }}</p>
                                    <p class="mt-1 text-xs text-black/40">Your email is linked to your order history — contact us if you need to change it.</p>
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="rounded-lg bg-black px-5 py-2 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-black/80">Save profile</button>
                            </div>
                        </form>

                        <div class="my-6 border-t border-black/5"></div>

                        <h3 class="text-sm font-bold">Change password</h3>
                        @if ($errors->updatePassword->isNotEmpty())
                            <div class="mt-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                {{ $errors->updatePassword->first() }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('account.password.update') }}" class="mt-3">
                            @csrf
                            @method('PUT')

                            <div class="grid gap-4 sm:max-w-sm">
                                <div>
                                    <label for="current_password" class="mb-1 block text-sm font-medium text-black/70">Current password</label>
                                    <input id="current_password" type="password" name="current_password" required autocomplete="current-password" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none">
                                    @error('current_password', 'updatePassword')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="new_password" class="mb-1 block text-sm font-medium text-black/70">New password</label>
                                    <input id="new_password" type="password" name="password" required autocomplete="new-password" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none">
                                    @error('password', 'updatePassword')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="new_password_confirmation" class="mb-1 block text-sm font-medium text-black/70">Confirm new password</label>
                                    <input id="new_password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-lg border border-black/15 px-3 py-2 text-sm focus:border-black focus:outline-none">
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="rounded-lg bg-black px-5 py-2 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-black/80">Update password</button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </main>

        @include('partials.footer')
    </body>
</html>
