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

<header
    class="sticky top-0 z-50 border-b border-slate-200 bg-white"
    x-data="{ mobileOpen: false }"
    x-on:keydown.escape.window="mobileOpen = false"
>
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
                    <a href="{{ route('shop.category', $cat->slug) }}"
                       class="rounded-lg px-3 py-2 text-sm font-bold uppercase tracking-wider text-rose-600 transition-all duration-200 hover:bg-rose-50 hover:text-rose-700"
                       title="Sale">
                        Sale
                    </a>
                @elseif ($cat->children->isNotEmpty())
                    <div class="group relative">
                        <a href="{{ route('shop.category', $cat->slug) }}"
                           class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-bold uppercase tracking-wider text-slate-700 transition-all duration-200 hover:bg-slate-100 hover:text-black">
                            {{ $cat->name }}
                            <svg class="h-3.5 w-3.5 transition-transform duration-200 group-hover:rotate-180" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                            </svg>
                        </a>
                        <div class="invisible absolute left-0 top-full z-50 min-w-48 rounded-xl border border-slate-100 bg-white py-2 opacity-0 shadow-xl transition-all duration-200 group-hover:visible group-hover:opacity-100">
                            @foreach ($cat->children as $child)
                                <a href="{{ route('shop.category', $child->slug) }}"
                                   class="block px-5 py-2.5 text-sm font-medium text-slate-600 transition-colors duration-150 hover:bg-black hover:text-white">
                                    {{ $child->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <a href="{{ route('shop.category', $cat->slug) }}"
                       class="rounded-lg px-3 py-2 text-sm font-bold uppercase tracking-wider text-slate-700 transition-all duration-200 hover:bg-slate-100 hover:text-black">
                        {{ $cat->name }}
                    </a>
                @endif
            @endforeach
        </nav>

        {{-- Right section --}}
        <div class="flex items-center gap-2">
            {{-- Cart icon --}}
            <div class="group relative">
                <a href="{{ route('cart.index') }}" class="relative inline-flex rounded-lg p-2.5 text-slate-600 transition-all duration-200 hover:bg-slate-100 hover:text-black" aria-label="Cart">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                    </svg>
                    @if ($cartItemCount > 0)
                        <span class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-black px-1 text-[10px] font-bold text-white">{{ $cartItemCount }}</span>
                    @endif
                </a>

                {{-- Cart dropdown (desktop) --}}
                <div class="invisible absolute right-0 top-full z-50 w-80 rounded-xl border border-slate-100 bg-white opacity-0 shadow-xl transition-all duration-200 group-hover:visible group-hover:opacity-100">
                    @if ($cartItems->isEmpty())
                        <div class="px-5 py-8 text-center text-sm text-slate-400">Your cart is empty</div>
                    @else
                        <div class="max-h-72 overflow-y-auto">
                            @foreach ($cartItems->take(5) as $cartItem)
                                <div class="flex gap-3 border-b border-slate-100 px-5 py-3 last:border-0">
                                    @if (! empty($cartItem['image']))
                                        <img src="{{ $cartItem['image'] }}" alt="{{ $cartItem['product_name'] ?? '' }}" class="h-12 w-12 shrink-0 rounded-lg object-contain bg-slate-50">
                                    @else
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-slate-50">
                                            <svg class="h-5 w-5 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/></svg>
                                        </div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-black">{{ $cartItem['product_name'] ?? '' }}</p>
                                        <p class="text-xs text-slate-400">{{ $cartItem['variant_name'] ?? '' }}</p>
                                        <div class="mt-1 flex items-center justify-between">
                                            <span class="text-xs text-slate-500">{{ $cartItem['quantity'] }} &times; &euro;{{ number_format((float) ($cartItem['price'] ?? 0), 2) }}</span>
                                            <form method="POST" action="{{ route('cart.items.destroy', $cartItem['variant_id']) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs text-slate-400 transition-colors duration-150 hover:text-red-500" title="Remove">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($cartItems->count() > 5)
                            <div class="border-t border-slate-100 px-5 py-2 text-center text-xs text-slate-400">
                                +{{ $cartItems->count() - 5 }} more {{ Str::plural('item', $cartItems->count() - 5) }}
                            </div>
                        @endif

                        <div class="border-t border-slate-100 px-5 py-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-500">Subtotal</span>
                                <span class="font-bold text-black">&euro;{{ number_format($cartSubtotal, 2) }}</span>
                            </div>
                            <p class="mt-1 text-[11px] text-slate-400">Before shipping &amp; taxes</p>
                            <a href="{{ route('cart.index') }}" class="mt-3 block rounded-lg bg-black px-4 py-2.5 text-center text-xs font-bold uppercase tracking-widest text-white transition-all duration-200 hover:bg-white hover:text-black hover:ring-2 hover:ring-black">View Cart</a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Account dropdown (desktop) --}}
            @auth
                <div class="group relative hidden lg:block">
                    <button type="button" class="inline-flex rounded-lg p-2.5 text-slate-600 transition-all duration-200 hover:bg-slate-100 hover:text-black" aria-label="My account">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                        </svg>
                    </button>
                    <div class="invisible absolute right-0 top-full z-50 min-w-44 rounded-xl border border-slate-100 bg-white py-2 opacity-0 shadow-xl transition-all duration-200 group-hover:visible group-hover:opacity-100">
                        <a href="{{ route('account.index') }}" class="block px-5 py-2.5 text-sm font-medium text-slate-600 transition-colors duration-150 hover:bg-black hover:text-white">My Account</a>
                        @if (auth()->user()?->is_admin)
                            <a href="{{ route('admin.products.index') }}" class="block px-5 py-2.5 text-sm font-medium text-slate-600 transition-colors duration-150 hover:bg-black hover:text-white" aria-label="Admin panel">Admin Panel</a>
                        @endif
                        <div class="my-1 border-t border-slate-100"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-5 py-2.5 text-left text-sm font-medium text-slate-600 transition-colors duration-150 hover:bg-black hover:text-white" aria-label="Log out">Logout</button>
                        </form>
                    </div>
                </div>
            @else
                <div class="hidden items-center gap-2 lg:flex">
                    <a href="{{ route('login') }}" class="rounded-lg px-4 py-2 text-sm font-bold uppercase tracking-wider text-slate-700 transition-all duration-200 hover:bg-slate-100 hover:text-black">Login</a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-black px-4 py-2 text-sm font-bold uppercase tracking-wider text-white transition-all duration-200 hover:bg-white hover:text-black hover:ring-2 hover:ring-black">Register</a>
                </div>
            @endauth

            {{-- Mobile menu toggle --}}
            <button
                type="button"
                class="rounded-lg p-2.5 text-slate-600 transition-all duration-200 hover:bg-slate-100 hover:text-black lg:hidden"
                aria-label="Toggle menu"
                x-on:click="mobileOpen = !mobileOpen"
            >
                <svg x-show="!mobileOpen" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                </svg>
                <svg x-show="mobileOpen" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile Menu — fullscreen overlay --}}
    <div
        x-show="mobileOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="absolute inset-x-0 top-full z-40 max-h-[calc(100dvh-4rem)] overflow-y-auto border-t border-slate-200 bg-white lg:hidden"
    >
        <nav class="mx-auto max-w-6xl px-6 py-6" aria-label="Mobile navigation">
            {{-- Shop categories --}}
            <div class="space-y-1">
                <p class="mb-3 text-xs font-bold uppercase tracking-widest text-slate-400">Shop</p>
                @foreach ($headerCategories as $cat)
                    @if ($cat->slug === 'sale')
                        <a href="{{ route('shop.category', $cat->slug) }}"
                           x-on:click="mobileOpen = false"
                           class="flex items-center gap-3 rounded-lg px-4 py-3 text-base font-bold uppercase tracking-wider text-rose-600 transition-all duration-200 hover:bg-rose-50">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/>
                            </svg>
                            Sale
                        </a>
                    @elseif ($cat->children->isNotEmpty())
                        <div x-data="{ expanded: false }">
                            <button
                                type="button"
                                x-on:click="expanded = !expanded"
                                class="flex w-full items-center justify-between rounded-lg px-4 py-3 text-base font-bold uppercase tracking-wider text-slate-900 transition-all duration-200 hover:bg-slate-50"
                            >
                                {{ $cat->name }}
                                <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                </svg>
                            </button>
                            <div
                                x-show="expanded"
                                x-cloak
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 -translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                class="ml-4 space-y-0.5 border-l-2 border-slate-100 pl-4"
                            >
                                <a href="{{ route('shop.category', $cat->slug) }}"
                                   x-on:click="mobileOpen = false"
                                   class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-700 transition-colors duration-150 hover:bg-black hover:text-white">
                                    All {{ $cat->name }}
                                </a>
                                @foreach ($cat->children as $child)
                                    <a href="{{ route('shop.category', $child->slug) }}"
                                       x-on:click="mobileOpen = false"
                                       class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-700 transition-colors duration-150 hover:bg-black hover:text-white">
                                        {{ $child->name }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <a href="{{ route('shop.category', $cat->slug) }}"
                           x-on:click="mobileOpen = false"
                           class="block rounded-lg px-4 py-3 text-base font-bold uppercase tracking-wider text-slate-900 transition-all duration-200 hover:bg-slate-50">
                            {{ $cat->name }}
                        </a>
                    @endif
                @endforeach
            </div>

            {{-- Divider --}}
            <div class="my-5 border-t border-slate-100"></div>

            {{-- Account section --}}
            <div class="space-y-1">
                <p class="mb-3 text-xs font-bold uppercase tracking-widest text-slate-400">Account</p>
                @auth
                    <a href="{{ route('account.index') }}"
                       x-on:click="mobileOpen = false"
                       class="flex items-center gap-3 rounded-lg px-4 py-3 text-base font-bold uppercase tracking-wider text-slate-900 transition-all duration-200 hover:bg-slate-50">
                        <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                        </svg>
                        My Account
                    </a>
                    @if (auth()->user()?->is_admin)
                        <a href="{{ route('admin.products.index') }}"
                           x-on:click="mobileOpen = false"
                           class="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-semibold text-slate-600 transition-all duration-200 hover:bg-slate-50">
                            <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                            </svg>
                            Admin Panel
                        </a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="flex w-full items-center gap-3 rounded-lg px-4 py-3 text-sm font-semibold text-slate-600 transition-all duration-200 hover:bg-slate-50"
                        >
                            <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/>
                            </svg>
                            Logout
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}"
                       x-on:click="mobileOpen = false"
                       class="block rounded-lg px-4 py-3 text-base font-bold uppercase tracking-wider text-slate-900 transition-all duration-200 hover:bg-slate-50">
                        Login
                    </a>
                    <a href="{{ route('register') }}"
                       x-on:click="mobileOpen = false"
                       class="block rounded-lg px-4 py-3 text-base font-bold uppercase tracking-wider text-slate-900 transition-all duration-200 hover:bg-slate-50">
                        Register
                    </a>
                @endauth
            </div>

            {{-- Divider --}}
            <div class="my-5 border-t border-slate-100"></div>

            {{-- Checkout CTA — last item --}}
            <a href="{{ route('cart.index') }}"
               x-on:click="mobileOpen = false"
               class="flex items-center justify-between rounded-lg bg-black px-5 py-4 text-sm font-bold uppercase tracking-widest text-white transition-all duration-200 hover:bg-[#242424]">
                <span>Cart{{ $cartItemCount > 0 ? ' ('.$cartItemCount.')' : '' }}</span>
                @if ($cartItemCount > 0)
                    <span>&euro;{{ number_format($cartSubtotal, 2) }}</span>
                @endif
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25"/></svg>
            </a>
        </nav>
    </div>
</header>
