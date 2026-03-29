<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Checkout | GoonsGear</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css'])
        @endif
    </head>
    <body class="bg-slate-100 text-slate-900">
        <div class="mx-auto max-w-6xl p-6">
            <header class="mb-6 flex items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold">Checkout</h1>
                <a href="{{ route('cart.index') }}" class="text-sm text-blue-700 hover:underline">Back to cart</a>
            </header>

            @if ($errors->has('cart'))
                <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $errors->first('cart') }}</div>
            @endif

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded border border-slate-200 bg-white p-5">
                    <h2 class="text-base font-semibold">Shipping details</h2>

                    <form method="POST" action="{{ route('checkout.store') }}" class="mt-4 grid gap-3">
                        @csrf

                        <div>
                            <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                            <input id="email" name="email" type="email" value="{{ $formDefaults['email'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                            @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="first_name" class="mb-1 block text-sm font-medium text-slate-700">First name</label>
                                <input id="first_name" name="first_name" type="text" value="{{ $formDefaults['first_name'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                @error('first_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="last_name" class="mb-1 block text-sm font-medium text-slate-700">Last name</label>
                                <input id="last_name" name="last_name" type="text" value="{{ $formDefaults['last_name'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                @error('last_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="phone" class="mb-1 block text-sm font-medium text-slate-700">Phone (optional)</label>
                                <input id="phone" name="phone" type="text" value="{{ $formDefaults['phone'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                @error('phone')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="country" class="mb-1 block text-sm font-medium text-slate-700">Country (ISO2)</label>
                                <input id="country" name="country" type="text" maxlength="2" value="{{ $formDefaults['country'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm uppercase">
                                @error('country')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div>
                            <label for="address_line_1" class="mb-1 block text-sm font-medium text-slate-700">Address line 1</label>
                            <input id="address_line_1" name="address_line_1" type="text" value="{{ $formDefaults['address_line_1'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                            @error('address_line_1')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="address_line_2" class="mb-1 block text-sm font-medium text-slate-700">Address line 2 (optional)</label>
                            <input id="address_line_2" name="address_line_2" type="text" value="{{ $formDefaults['address_line_2'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                            @error('address_line_2')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="city" class="mb-1 block text-sm font-medium text-slate-700">City</label>
                                <input id="city" name="city" type="text" value="{{ $formDefaults['city'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                @error('city')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="postal_code" class="mb-1 block text-sm font-medium text-slate-700">Postal code</label>
                                <input id="postal_code" name="postal_code" type="text" value="{{ $formDefaults['postal_code'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                @error('postal_code')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <button type="submit" class="mt-2 rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Place order</button>
                    </form>
                </section>

                <aside class="rounded border border-slate-200 bg-white p-5">
                    <h2 class="text-base font-semibold">Order summary</h2>
                    <div class="mt-4 space-y-3">
                        @foreach ($items as $item)
                            <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                                <div>
                                    <p class="text-sm font-medium text-slate-900">{{ $item['product_name'] }}</p>
                                    <p class="text-xs text-slate-600">{{ $item['variant_name'] }} x {{ $item['quantity'] }}</p>
                                </div>
                                <p class="text-sm font-medium">${{ number_format((float) $item['price'] * (int) $item['quantity'], 2) }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex items-center justify-between border-t border-slate-200 pt-3">
                        <p class="text-sm text-slate-600">Total</p>
                        <p class="text-lg font-semibold">${{ number_format((float) $subtotal, 2) }}</p>
                    </div>
                </aside>
            </div>
        </div>
    </body>
</html>
