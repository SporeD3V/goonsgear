@php
    $headerCategories = \App\Models\Category::query()
        ->where('is_active', true)
        ->whereNull('parent_id')
        ->where(function ($q) {
            $q->whereHas('products')
              ->orWhereHas('children', fn ($cq) => $cq->where('is_active', true)->whereHas('products'));
        })
        ->with(['children' => fn ($q) => $q->where('is_active', true)->whereHas('products')->orderBy('name')])
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get(['id', 'name', 'slug']);

    $cartItems = collect(session('cart.items', []));
    $cartItemCount = $cartItems->sum('quantity');
    $cartSubtotal = $cartItems->sum(fn ($i) => (float) ($i['price'] ?? 0) * (int) $i['quantity']);
@endphp

<header class="sticky top-0 z-50 border-b border-slate-200 bg-white shadow-sm">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-3">
        {{-- Logo --}}
        <a href="{{ url('/') }}" class="shrink-0">
            <picture>
                <source srcset="{{ asset('images/goonsgear-shop-by-snowgoons-logo.avif') }}" type="image/avif">
                <img
                    src="{{ asset('images/goonsgear-shop-by-snowgoons-logo.png') }}"
                    alt="GoonsGear"
                    class="h-14 w-auto"
                    width="168"
                    height="140"
                >
            </picture>
        </a>

        {{-- Categories Nav (desktop) --}}
        <nav class="hidden items-center gap-1 lg:flex" aria-label="Categories">
            @foreach ($headerCategories as $cat)
                @if ($cat->slug === 'sale')
                    {{-- SALE icon --}}
                    <a href="{{ route('shop.category', $cat->slug) }}"
                       class="rounded px-2 py-1 text-rose-600 transition hover:bg-rose-50 hover:text-rose-700"
                       title="Sale">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/>
                        </svg>
                    </a>
                @elseif ($cat->children->isNotEmpty())
                    {{-- Dropdown for parent categories (Music, Wear) — link goes to parent, hover shows children --}}
                    <div class="group relative">
                        <a href="{{ route('shop.category', $cat->slug) }}"
                           class="flex items-center gap-1 rounded px-2 py-1 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-900">
                            {{ $cat->name }}
                            <svg class="h-3 w-3 transition group-hover:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                            </svg>
                        </a>
                        <div class="invisible absolute left-0 top-full z-50 min-w-40 rounded-md border border-slate-200 bg-white py-1 shadow-lg transition-all group-hover:visible">
                            @foreach ($cat->children as $child)
                                <a href="{{ route('shop.category', $child->slug) }}"
                                   class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 hover:text-slate-900">
                                    {{ $child->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <a href="{{ route('shop.category', $cat->slug) }}"
                       class="rounded px-2 py-1 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-900">
                        {{ $cat->name }}
                    </a>
                @endif
            @endforeach
        </nav>

        {{-- Right Icons --}}
        <div class="flex items-center gap-3">
            {{-- Mobile menu toggle --}}
            <button
                type="button"
                class="rounded p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
                aria-label="Toggle menu"
                onclick="document.getElementById('mobile-menu').classList.toggle('hidden')"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                </svg>
            </button>

            {{-- Cart --}}
            <div class="group relative">
                <a href="{{ route('cart.index') }}" class="relative inline-flex rounded p-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900" aria-label="Cart">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                    </svg>
                    @if ($cartItemCount > 0)
                        <span class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white">{{ $cartItemCount }}</span>
                    @endif
                </a>

                {{-- Cart Dropdown --}}
                <div class="invisible absolute right-0 top-full z-50 w-80 rounded-lg border border-slate-200 bg-white opacity-0 shadow-xl transition-all group-hover:visible group-hover:opacity-100">
                    @if ($cartItems->isEmpty())
                        <div class="px-4 py-6 text-center text-sm text-slate-500">Your cart is empty</div>
                    @else
                        <div class="max-h-72 overflow-y-auto">
                            @foreach ($cartItems->take(5) as $cartItem)
                                <div class="flex gap-3 border-b border-slate-100 px-4 py-3 last:border-0">
                                    @if (! empty($cartItem['image']))
                                        <img src="{{ $cartItem['image'] }}" alt="{{ $cartItem['product_name'] ?? '' }}" class="h-12 w-12 shrink-0 rounded object-contain bg-slate-50">
                                    @else
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded bg-slate-100">
                                            <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/></svg>
                                        </div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-slate-900">{{ $cartItem['product_name'] ?? '' }}</p>
                                        <p class="text-xs text-slate-500">{{ $cartItem['variant_name'] ?? '' }}</p>
                                        <div class="mt-1 flex items-center justify-between">
                                            <span class="text-xs text-slate-600">{{ $cartItem['quantity'] }} &times; &euro;{{ number_format((float) ($cartItem['price'] ?? 0), 2) }}</span>
                                            <form method="POST" action="{{ route('cart.items.destroy', $cartItem['variant_id']) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs text-rose-500 hover:text-rose-700" title="Remove">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($cartItems->count() > 5)
                            <div class="border-t border-slate-100 px-4 py-2 text-center text-xs text-slate-500">
                                +{{ $cartItems->count() - 5 }} more {{ Str::plural('item', $cartItems->count() - 5) }}
                            </div>
                        @endif

                        <div class="border-t border-slate-200 px-4 py-3">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-600">Subtotal</span>
                                <span class="font-semibold text-slate-900">&euro;{{ number_format($cartSubtotal, 2) }}</span>
                            </div>
                            <p class="mt-1 text-[11px] text-slate-400">Before shipping &amp; taxes</p>
                            <a href="{{ route('cart.index') }}" class="mt-3 block rounded bg-slate-800 px-4 py-2 text-center text-sm font-medium text-white transition hover:bg-slate-900">View Cart</a>
                        </div>
                    @endif
                </div>
            </div>

            @auth
                {{-- Account --}}
                <a href="{{ route('account.index') }}" class="rounded p-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900" aria-label="My account">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                    </svg>
                </a>

                {{-- Admin --}}
                @if (auth()->user()?->is_admin)
                    <a href="{{ route('admin.products.index') }}" class="rounded p-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900" aria-label="Admin panel">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                        </svg>
                    </a>
                @endif

                {{-- Logout --}}
                <form method="POST" action="{{ route('logout') }}" class="flex">
                    @csrf
                    <button type="submit" class="rounded p-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900" aria-label="Log out">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/>
                        </svg>
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="rounded px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Login</a>
                <a href="{{ route('register') }}" class="rounded bg-slate-900 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-slate-800">Register</a>
            @endauth
        </div>
    </div>

    {{-- Mobile Menu --}}
    <nav id="mobile-menu" class="hidden border-t border-slate-200 bg-white px-6 pb-4 pt-2 lg:hidden" aria-label="Mobile categories">
        <div class="flex flex-col gap-1">
            @foreach ($headerCategories as $cat)
                @if ($cat->slug === 'sale')
                    <a href="{{ route('shop.category', $cat->slug) }}"
                       class="flex items-center gap-2 rounded px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/>
                        </svg>
                        Sale
                    </a>
                @elseif ($cat->children->isNotEmpty())
                    <div class="rounded px-3 py-2">
                        <a href="{{ route('shop.category', $cat->slug) }}"
                           class="text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-900">{{ $cat->name }}</a>
                        <div class="mt-1 flex flex-col gap-1">
                            @foreach ($cat->children as $child)
                                <a href="{{ route('shop.category', $child->slug) }}"
                                   class="rounded px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100">
                                    {{ $child->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <a href="{{ route('shop.category', $cat->slug) }}"
                       class="rounded px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
                        {{ $cat->name }}
                    </a>
                @endif
            @endforeach
        </div>
    </nav>
</header>
