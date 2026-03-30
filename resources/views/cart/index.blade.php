<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Cart | GoonsGear</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css'])
        @endif
    </head>
    <body class="bg-slate-100 text-slate-900">
        <div class="mx-auto max-w-6xl p-6">
            <header class="mb-6 flex items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold">Your Cart</h1>
                <a href="{{ route('shop.index') }}" class="text-sm text-blue-700 hover:underline">Continue shopping</a>
            </header>

            @if (session('status'))
                <div class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('status') }}</div>
            @endif

            @if ($errors->has('cart'))
                <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $errors->first('cart') }}</div>
            @endif

            @if ($couponError)
                <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $couponError }}</div>
            @endif

            @if (empty($items))
                <p class="rounded border border-slate-200 bg-white p-4 text-sm text-slate-600">Your cart is empty.</p>
            @else
                <div class="mb-4 rounded border border-slate-200 bg-white p-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                        <form method="POST" action="{{ route('cart.coupon.apply') }}" class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-end">
                            @csrf
                            <div class="flex-1">
                                <label for="coupon_code" class="mb-1 block text-sm font-medium text-slate-700">Coupon code</label>
                                <input id="coupon_code" name="coupon_code" type="text" value="{{ old('coupon_code', $couponCode) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm uppercase" placeholder="SAVE10">
                                @error('coupon_code')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Add code</button>
                        </form>

                        @if (!empty($selectedCouponCodes))
                            <form method="POST" action="{{ route('cart.coupon.remove') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Clear selected coupons</button>
                            </form>
                        @endif
                    </div>

                    @if ($recommendationMessage)
                        <p class="mt-3 text-sm text-emerald-700">{{ $recommendationMessage }}</p>
                    @endif

                    @if (!empty($invalidCouponMessages))
                        <div class="mt-3 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            @foreach ($invalidCouponMessages as $invalidCode => $invalidMessage)
                                <p><span class="font-semibold">{{ $invalidCode }}:</span> {{ $invalidMessage }}</p>
                            @endforeach
                        </div>
                    @endif

                    @if ($appliedCoupons->isNotEmpty())
                        <div class="mt-3 space-y-2">
                            @foreach ($appliedCoupons as $coupon)
                                <div class="flex flex-wrap items-center gap-2 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                                    <span class="font-semibold">{{ $coupon->code }}</span>
                                    <span class="text-xs">{{ $coupon->is_stackable ? 'Can combine' : 'Exclusive coupon' }}</span>
                                    @if ($coupon->stack_group)
                                        <span class="text-xs">Group: {{ $coupon->stack_group }}</span>
                                    @endif

                                    <form method="POST" action="{{ route('cart.coupon.remove') }}" class="ml-auto">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="coupon_code" value="{{ $coupon->code }}">
                                        <button type="submit" class="text-xs text-emerald-900 hover:underline">Remove</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @auth
                        @if ($availableCoupons->isNotEmpty())
                            <form method="POST" action="{{ route('cart.coupon.select') }}" class="mt-4 rounded border border-slate-200 bg-slate-50 p-3">
                                @csrf
                                <p class="text-sm font-medium text-slate-800">Choose from your account coupons</p>
                                <p class="text-xs text-slate-500">Select one or more. We will automatically apply the best valid combination.</p>

                                <div class="mt-3 space-y-2">
                                    @foreach ($availableCoupons as $coupon)
                                        <label class="flex cursor-pointer items-start gap-2 rounded border border-slate-200 bg-white px-3 py-2 text-sm">
                                            <input
                                                type="checkbox"
                                                name="coupon_codes[]"
                                                value="{{ $coupon->code }}"
                                                class="mt-0.5 rounded border-slate-300"
                                                @checked(in_array($coupon->code, $selectedCouponCodes, true))
                                            >
                                            <span>
                                                <span class="font-medium text-slate-800">{{ $coupon->code }}</span>
                                                <span class="text-xs text-slate-600">{{ $coupon->is_stackable ? 'Can combine' : 'Exclusive' }}</span>
                                                @if ($coupon->stack_group)
                                                    <span class="text-xs text-slate-500">(Group {{ $coupon->stack_group }})</span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>

                                <div class="mt-3">
                                    <button type="submit" class="rounded bg-slate-800 px-3 py-2 text-xs font-medium text-white hover:bg-slate-900">Update selected coupons</button>
                                </div>
                            </form>
                        @endif
                    @endif
                </div>

                <div class="overflow-x-auto rounded border border-slate-200 bg-white">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="border-b border-slate-200 px-3 py-2 text-left">Item</th>
                                <th class="border-b border-slate-200 px-3 py-2 text-left">Price</th>
                                <th class="border-b border-slate-200 px-3 py-2 text-left">Qty</th>
                                <th class="border-b border-slate-200 px-3 py-2 text-left">Total</th>
                                <th class="border-b border-slate-200 px-3 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td class="border-b border-slate-200 px-3 py-3 align-top">
                                        <div class="flex gap-3">
                                            @if ($item['image'])
                                                <img src="{{ $item['image'] }}" alt="{{ $item['product_name'] }}" class="h-16 w-16 rounded object-cover">
                                            @else
                                                <img src="{{ asset('images/placeholder-product.svg') }}" alt="No image available" class="h-16 w-16 rounded object-cover">
                                            @endif
                                            <div>
                                                @if ($item['url'])
                                                    <a href="{{ $item['url'] }}" class="font-medium text-slate-900 hover:underline">{{ $item['product_name'] }}</a>
                                                @else
                                                    <p class="font-medium text-slate-900">{{ $item['product_name'] }}</p>
                                                @endif
                                                <p class="text-xs text-slate-600">{{ $item['variant_name'] }} ({{ $item['sku'] }})</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="border-b border-slate-200 px-3 py-3 align-top">${{ number_format((float) $item['price'], 2) }}</td>
                                    <td class="border-b border-slate-200 px-3 py-3 align-top">
                                        <form method="POST" action="{{ route('cart.items.update', $item['variant_id']) }}" class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <label for="cart_quantity_{{ $item['variant_id'] }}" class="sr-only">Quantity for {{ $item['product_name'] }}</label>
                                            <input
                                                id="cart_quantity_{{ $item['variant_id'] }}"
                                                type="number"
                                                name="quantity"
                                                min="1"
                                                @if ($item['max_quantity'] !== null)
                                                    max="{{ $item['max_quantity'] }}"
                                                @endif
                                                value="{{ $item['quantity'] }}"
                                                class="w-20 rounded border border-slate-300 px-2 py-1"
                                            >
                                            <button type="submit" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Update</button>
                                        </form>
                                    </td>
                                    <td class="border-b border-slate-200 px-3 py-3 align-top">${{ number_format((float) $item['price'] * (int) $item['quantity'], 2) }}</td>
                                    <td class="border-b border-slate-200 px-3 py-3 align-top">
                                        <form method="POST" action="{{ route('cart.items.destroy', $item['variant_id']) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 rounded border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between text-sm text-slate-600">
                        <p>Subtotal</p>
                        <p>${{ number_format((float) $subtotal, 2) }}</p>
                    </div>

                    @if ($discountTotal > 0)
                        <div class="mt-2 flex items-center justify-between text-sm text-emerald-700">
                            <p>Coupon Discount @if ($appliedCoupons->isNotEmpty()) ( {{ $appliedCoupons->pluck('code')->implode(', ') }} ) @endif</p>
                            <p>- ${{ number_format((float) $discountTotal, 2) }}</p>
                        </div>
                    @endif

                    @if ($bundleDiscountTotal > 0)
                        <div class="mt-2 flex items-center justify-between text-sm text-emerald-700">
                            <p>Bundle discount @if ($appliedBundle)( {{ $appliedBundle->name }} )@endif</p>
                            <p>- ${{ number_format((float) $bundleDiscountTotal, 2) }}</p>
                        </div>
                    @endif

                    <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-3">
                        <p class="text-sm text-slate-600">Total</p>
                        <div class="flex items-center gap-4">
                            <p class="text-lg font-semibold">${{ number_format((float) $total, 2) }}</p>
                            <a href="{{ route('checkout.index') }}" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Proceed to checkout</a>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </body>
</html>
