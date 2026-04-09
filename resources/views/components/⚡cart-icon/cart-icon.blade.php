@php
    $cart = $this->getCartData();
    $cartItems = $cart['items'];
    $cartItemCount = $cart['count'];
    $cartSubtotal = $cart['subtotal'];
@endphp

<div class="group relative" wire:poll.visible.30s>
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
                    <div class="flex gap-3 border-b border-slate-100 px-5 py-3 last:border-0" wire:key="cart-item-{{ $cartItem['variant_id'] }}">
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
                                <button
                                    type="button"
                                    wire:click="removeItem({{ $cartItem['variant_id'] }})"
                                    class="text-xs text-slate-400 transition-colors duration-150 hover:text-black"
                                    title="Remove"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                </button>
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
