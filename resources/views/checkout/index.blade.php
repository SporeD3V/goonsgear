<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Checkout | GoonsGear</title>
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
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
                                <label for="country" class="mb-1 block text-sm font-medium text-slate-700">Country</label>
                                <select id="country" name="country" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">— Select country —</option>
                                    @foreach ($countries as $code => $name)
                                        <option value="{{ $code }}" @selected($formDefaults['country'] === $code)>{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('country')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div id="location-endpoints"
                             data-states-url="{{ route('api.locations.states') }}"
                             data-cities-url="{{ route('api.locations.cities') }}"></div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="state" class="mb-1 block text-sm font-medium text-slate-700">State / Region <span class="text-slate-400">(optional)</span></label>
                                <div id="state-wrapper" data-initial-state="{{ $formDefaults['state'] }}">
                                    <input id="state" name="state" type="text" value="{{ $formDefaults['state'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                </div>
                                @error('state')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="city" class="mb-1 block text-sm font-medium text-slate-700">City</label>
                                <div id="city-wrapper" data-initial-city="{{ $formDefaults['city'] }}">
                                    <input id="city" name="city" type="text" value="{{ $formDefaults['city'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                </div>
                                @error('city')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div>
                            <label for="postal_code" class="mb-1 block text-sm font-medium text-slate-700">Postal code</label>
                            <input id="postal_code" name="postal_code" type="text" value="{{ $formDefaults['postal_code'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                            @error('postal_code')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="street_name" class="mb-1 block text-sm font-medium text-slate-700">Street name</label>
                                <input id="street_name" name="street_name" type="text" value="{{ $formDefaults['street_name'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                @error('street_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="street_number" class="mb-1 block text-sm font-medium text-slate-700">Street number</label>
                                <input id="street_number" name="street_number" type="text" value="{{ $formDefaults['street_number'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                @error('street_number')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="apartment_block" class="mb-1 block text-sm font-medium text-slate-700">Apartment block <span class="text-slate-400">(optional)</span></label>
                                <input id="apartment_block" name="apartment_block" type="text" value="{{ $formDefaults['apartment_block'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                @error('apartment_block')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="entrance" class="mb-1 block text-sm font-medium text-slate-700">Entrance <span class="text-slate-400">(optional)</span></label>
                                <input id="entrance" name="entrance" type="text" value="{{ $formDefaults['entrance'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                @error('entrance')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="floor" class="mb-1 block text-sm font-medium text-slate-700">Floor <span class="text-slate-400">(optional)</span></label>
                                <input id="floor" name="floor" type="text" value="{{ $formDefaults['floor'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                @error('floor')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="apartment_number" class="mb-1 block text-sm font-medium text-slate-700">Apartment number <span class="text-slate-400">(optional)</span></label>
                                <input id="apartment_number" name="apartment_number" type="text" value="{{ $formDefaults['apartment_number'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                @error('apartment_number')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <button type="submit" class="mt-2 rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Place order</button>

                        @if ($paypalEnabled && $paypalClientId)
                            <div id="paypal-checkout"
                                 class="mt-2"
                                 data-create-order-url="{{ route('checkout.paypal.create-order') }}"
                                 data-capture-order-url="{{ route('checkout.paypal.capture-order') }}"
                                 data-csrf-token="{{ csrf_token() }}"></div>
                            <p class="text-xs text-slate-500">Or pay securely with PayPal.</p>
                            <div id="paypal-errors" class="hidden rounded border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700"></div>
                        @endif
                    </form>
                </section>

                <aside class="rounded border border-slate-200 bg-white p-5"
                       id="order-summary"
                       data-subtotal="{{ number_format((float) $subtotal, 2, '.', '') }}"
                       data-coupon-discount="{{ number_format((float) $discountTotal, 2, '.', '') }}"
                      data-bundle-discount="{{ number_format((float) $bundleDiscountTotal, 2, '.', '') }}"
                       data-regional-discount-url="{{ route('api.regional-discount') }}">
                    <h2 class="text-base font-semibold">Order summary</h2>
                    <div class="mt-4 space-y-3">
                        @foreach ($items as $item)
                            <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                                <div class="flex items-start gap-3">
                                    @if ($item['image'])
                                        <img src="{{ $item['image'] }}" alt="{{ $item['product_name'] }}" class="h-12 w-12 rounded object-cover">
                                    @else
                                        <div class="h-12 w-12 rounded bg-slate-100"></div>
                                    @endif
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">{{ $item['product_name'] }}</p>
                                        <p class="text-xs text-slate-600">{{ $item['variant_name'] }} x {{ $item['quantity'] }}</p>
                                    </div>
                                </div>
                                <p class="text-sm font-medium">${{ number_format((float) $item['price'] * (int) $item['quantity'], 2) }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex items-center justify-between border-t border-slate-200 pt-3">
                        <p class="text-sm text-slate-600">Subtotal</p>
                        <p class="text-lg font-semibold">${{ number_format((float) $subtotal, 2) }}</p>
                    </div>

                    @if ($discountTotal > 0)
                        <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-3">
                            <p class="text-sm text-emerald-700">Discount @if ($appliedCoupon)( {{ $appliedCoupon->code }} )@endif</p>
                            <p class="text-lg font-semibold text-emerald-700">- ${{ number_format((float) $discountTotal, 2) }}</p>
                        </div>
                    @endif

                    @if ($bundleDiscountTotal > 0)
                        <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-3">
                            <p class="text-sm text-emerald-700">Bundle discount @if ($appliedBundle)( {{ $appliedBundle->name }} )@endif</p>
                            <p class="text-lg font-semibold text-emerald-700">- ${{ number_format((float) $bundleDiscountTotal, 2) }}</p>
                        </div>
                    @endif

                    <div id="regional-discount-line" class="mt-3 hidden items-center justify-between border-t border-slate-200 pt-3">
                        <p class="text-sm text-emerald-700">Regional discount<br><span id="regional-discount-reason" class="text-xs font-normal"></span></p>
                        <p id="regional-discount-amount" class="text-lg font-semibold text-emerald-700"></p>
                    </div>

                    <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-3">
                        <p class="text-sm text-slate-600">Grand total</p>
                        <p id="grand-total" class="text-lg font-semibold">${{ number_format((float) $total, 2) }}</p>
                    </div>
                </aside>
            </div>
        </div>

        @if ($paypalEnabled && $paypalClientId)
            <script src="https://www.paypal.com/sdk/js?client-id={{ $paypalClientId }}&currency=EUR&intent=capture"></script>
        @endif

        <script>
            (function () {
                const summary = document.getElementById('order-summary');
                const countrySelect = document.getElementById('country');
                const regionalLine = document.getElementById('regional-discount-line');
                const regionalAmount = document.getElementById('regional-discount-amount');
                const regionalReason = document.getElementById('regional-discount-reason');
                const grandTotal = document.getElementById('grand-total');

                if (!summary || !countrySelect) return;

                const subtotal = parseFloat(summary.dataset.subtotal) || 0;
                const couponDiscount = parseFloat(summary.dataset.couponDiscount) || 0;
                const bundleDiscount = parseFloat(summary.dataset.bundleDiscount) || 0;
                const apiUrl = summary.dataset.regionalDiscountUrl;

                function fmt(n) {
                    return '$' + n.toFixed(2);
                }

                function updateRegionalDiscount(countryCode) {
                    if (!countryCode) {
                        regionalLine.classList.add('hidden');
                        regionalLine.classList.remove('flex');
                        grandTotal.textContent = fmt(Math.max(0, subtotal - couponDiscount - bundleDiscount));
                        return;
                    }

                    fetch(apiUrl + '?country=' + encodeURIComponent(countryCode) + '&subtotal=' + subtotal)
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            if (data && data.discount_total > 0) {
                                regionalAmount.textContent = '- ' + fmt(data.discount_total);
                                regionalReason.textContent = data.reason;
                                regionalLine.classList.remove('hidden');
                                regionalLine.classList.add('flex');
                                grandTotal.textContent = fmt(Math.max(0, subtotal - couponDiscount - bundleDiscount - data.discount_total));
                            } else {
                                regionalLine.classList.add('hidden');
                                regionalLine.classList.remove('flex');
                                grandTotal.textContent = fmt(Math.max(0, subtotal - couponDiscount - bundleDiscount));
                            }
                        })
                        .catch(function () {
                            regionalLine.classList.add('hidden');
                            regionalLine.classList.remove('flex');
                        });
                }

                countrySelect.addEventListener('change', function () {
                    updateRegionalDiscount(this.value);
                });

                // Run on page load for pre-selected country
                if (countrySelect.value) {
                    updateRegionalDiscount(countrySelect.value);
                }
            })();
        </script>
    </body>
</html>
