<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>My Account | GoonsGear</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-slate-100 text-slate-900">
        <main class="mx-auto max-w-4xl p-6">
            <header class="mb-6 flex items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold">My Account</h1>
                <div class="flex items-center gap-3 text-sm">
                    <a href="{{ route('shop.index') }}" class="text-blue-700 hover:underline">Shop</a>
                    <a href="{{ route('cart.index') }}" class="text-blue-700 hover:underline">Cart</a>
                </div>
            </header>

            @if (session('status'))
                <div class="mb-4 rounded border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <section class="rounded border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">Profile</h2>
                <dl class="mt-4 grid gap-3 text-sm text-slate-700 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Name</dt>
                        <dd class="mt-1 font-medium">{{ auth()->user()?->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Email</dt>
                        <dd class="mt-1 font-medium">{{ auth()->user()?->email }}</dd>
                    </div>
                </dl>

                <form method="POST" action="{{ route('logout') }}" class="mt-6">
                    @csrf
                    <button type="submit" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Log out</button>
                </form>
            </section>

            <section class="mt-6 rounded border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">Email Notifications</h2>
                <p class="mt-1 text-sm text-slate-500">Choose which emails you'd like to receive about items in your cart.</p>

                <form method="POST" action="{{ route('account.email-preferences.update') }}" class="mt-5">
                    @csrf
                    @method('PATCH')

                    <fieldset class="space-y-4">
                        <legend class="sr-only">Email notification preferences</legend>

                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                type="checkbox"
                                name="notify_cart_discounts"
                                value="1"
                                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                {{ auth()->user()?->notify_cart_discounts ? 'checked' : '' }}
                            >
                            <span class="text-sm">
                                <span class="font-medium text-slate-800">Price drops on cart items</span><br>
                                <span class="text-slate-500">Get notified when the price of an item in your cart is reduced.</span>
                            </span>
                        </label>

                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                type="checkbox"
                                name="notify_cart_low_stock"
                                value="1"
                                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                {{ auth()->user()?->notify_cart_low_stock ? 'checked' : '' }}
                            >
                            <span class="text-sm">
                                <span class="font-medium text-slate-800">Low stock alerts</span><br>
                                <span class="text-slate-500">Get notified when an item in your cart is running low on stock.</span>
                            </span>
                        </label>
                    </fieldset>

                    <div class="mt-5">
                        <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            Save preferences
                        </button>
                    </div>
                </form>
            </section>

            <section class="mt-6 rounded border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">Saved Delivery Address</h2>
                <p class="mt-1 text-sm text-slate-500">This address is pre-filled during checkout when you are logged in.</p>

                <form method="POST" action="{{ route('account.delivery-address.update') }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="mb-1 block text-sm font-medium">Phone</label>
                        <input type="text" name="delivery_phone" value="{{ old('delivery_phone', auth()->user()?->delivery_phone) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Country code</label>
                            <input type="text" name="delivery_country" maxlength="2" value="{{ old('delivery_country', auth()->user()?->delivery_country) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="DE">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">State / Region</label>
                            <input type="text" name="delivery_state" value="{{ old('delivery_state', auth()->user()?->delivery_state) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">City</label>
                            <input type="text" name="delivery_city" value="{{ old('delivery_city', auth()->user()?->delivery_city) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Postal code</label>
                            <input type="text" name="delivery_postal_code" value="{{ old('delivery_postal_code', auth()->user()?->delivery_postal_code) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Street name</label>
                            <input type="text" name="delivery_street_name" value="{{ old('delivery_street_name', auth()->user()?->delivery_street_name) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Street number</label>
                            <input type="text" name="delivery_street_number" value="{{ old('delivery_street_number', auth()->user()?->delivery_street_number) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Apartment block</label>
                            <input type="text" name="delivery_apartment_block" value="{{ old('delivery_apartment_block', auth()->user()?->delivery_apartment_block) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Entrance</label>
                            <input type="text" name="delivery_entrance" value="{{ old('delivery_entrance', auth()->user()?->delivery_entrance) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Floor</label>
                            <input type="text" name="delivery_floor" value="{{ old('delivery_floor', auth()->user()?->delivery_floor) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Apartment number</label>
                            <input type="text" name="delivery_apartment_number" value="{{ old('delivery_apartment_number', auth()->user()?->delivery_apartment_number) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save address</button>
                    </div>
                </form>
            </section>

            <section class="mt-6 rounded border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">Size Profiles</h2>
                <p class="mt-1 text-sm text-slate-500">Save your sizes and sizes for people you buy for. Use these in the shop to filter products by size.</p>

                @if ($sizeProfiles->isNotEmpty())
                    <div class="mt-4 space-y-3">
                        @foreach ($sizeProfiles as $profile)
                            <div class="rounded border border-slate-200 p-4">
                                <form method="POST" action="{{ route('account.size-profiles.update', $profile) }}">
                                    @csrf
                                    @method('PATCH')

                                    <div class="mb-3 flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <input
                                                type="text"
                                                name="name"
                                                value="{{ old('name', $profile->name) }}"
                                                class="rounded border border-slate-300 px-3 py-1.5 text-sm font-medium"
                                                required
                                            >
                                            @if ($profile->is_self)
                                                <span class="rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">You</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-500">Top size</label>
                                            <select name="top_size" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                                <option value="">—</option>
                                                @foreach (['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '2XL', '3XL', '4XL', '5XL'] as $size)
                                                    <option value="{{ $size }}" @selected(old('top_size', $profile->top_size) === $size)>{{ $size }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-500">Bottom size</label>
                                            <select name="bottom_size" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                                <option value="">—</option>
                                                @foreach (['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '2XL', '3XL', '4XL', '5XL'] as $size)
                                                    <option value="{{ $size }}" @selected(old('bottom_size', $profile->bottom_size) === $size)>{{ $size }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-500">Shoe size</label>
                                            <input type="text" name="shoe_size" value="{{ old('shoe_size', $profile->shoe_size) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. 42">
                                        </div>
                                    </div>

                                    <div class="mt-3 flex items-center gap-2">
                                        <button type="submit" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Save</button>
                                    </div>
                                </form>

                                @unless ($profile->is_self)
                                    <form method="POST" action="{{ route('account.size-profiles.destroy', $profile) }}" class="mt-2">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-600 hover:underline">Remove this person</button>
                                    </form>
                                @endunless
                            </div>
                        @endforeach
                    </div>
                @endif

                @if (!$sizeProfiles->where('is_self', true)->count())
                    <div class="mt-4 rounded border border-dashed border-slate-300 p-4">
                        <h3 class="text-sm font-medium text-slate-700">Add your sizes</h3>
                        <form method="POST" action="{{ route('account.size-profiles.store') }}" class="mt-3">
                            @csrf
                            <input type="hidden" name="is_self" value="1">
                            <input type="hidden" name="name" value="{{ auth()->user()?->name ?? 'Me' }}">

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-500">Top size</label>
                                    <select name="top_size" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                        <option value="">—</option>
                                        @foreach (['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '2XL', '3XL', '4XL', '5XL'] as $size)
                                            <option value="{{ $size }}">{{ $size }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-500">Bottom size</label>
                                    <select name="bottom_size" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                        <option value="">—</option>
                                        @foreach (['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '2XL', '3XL', '4XL', '5XL'] as $size)
                                            <option value="{{ $size }}">{{ $size }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-500">Shoe size</label>
                                    <input type="text" name="shoe_size" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. 42">
                                </div>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Save my sizes</button>
                            </div>
                        </form>
                    </div>
                @endif

                <div class="mt-4 rounded border border-dashed border-slate-300 p-4">
                    <h3 class="text-sm font-medium text-slate-700">Add another person</h3>
                    <form method="POST" action="{{ route('account.size-profiles.store') }}" class="mt-3">
                        @csrf
                        <div class="mb-3">
                            <label class="mb-1 block text-xs font-medium text-slate-500">Name</label>
                            <input type="text" name="name" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. John" required>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500">Top size</label>
                                <select name="top_size" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">—</option>
                                    @foreach (['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '2XL', '3XL', '4XL', '5XL'] as $size)
                                        <option value="{{ $size }}">{{ $size }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500">Bottom size</label>
                                <select name="bottom_size" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">—</option>
                                    @foreach (['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '2XL', '3XL', '4XL', '5XL'] as $size)
                                        <option value="{{ $size }}">{{ $size }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500">Shoe size</label>
                                <input type="text" name="shoe_size" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. 42">
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="rounded bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">Add person</button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="mt-6 rounded border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">My Orders</h2>
                <p class="mt-1 text-sm text-slate-500">Recent orders placed with your account email.</p>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full border border-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="border border-slate-200 px-3 py-2 text-left">Order</th>
                                <th class="border border-slate-200 px-3 py-2 text-left">Date</th>
                                <th class="border border-slate-200 px-3 py-2 text-left">Status</th>
                                <th class="border border-slate-200 px-3 py-2 text-left">Items</th>
                                <th class="border border-slate-200 px-3 py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentOrders as $order)
                                <tr>
                                    <td class="border border-slate-200 px-3 py-2 font-medium">{{ $order->order_number }}</td>
                                    <td class="border border-slate-200 px-3 py-2">{{ optional($order->placed_at)->format('M d, Y H:i') ?? '-' }}</td>
                                    <td class="border border-slate-200 px-3 py-2">{{ ucfirst($order->status) }} / {{ ucfirst($order->payment_status) }}</td>
                                    <td class="border border-slate-200 px-3 py-2">{{ $order->items_count }}</td>
                                    <td class="border border-slate-200 px-3 py-2 text-right">{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="border border-slate-200 px-3 py-5 text-center text-slate-500">No orders yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mt-6 rounded border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">My Discount Codes</h2>
                <p class="mt-1 text-sm text-slate-500">These are the active coupons currently assigned to your account.</p>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full border border-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="border border-slate-200 px-3 py-2 text-left">Code</th>
                                <th class="border border-slate-200 px-3 py-2 text-left">Value</th>
                                <th class="border border-slate-200 px-3 py-2 text-left">Rules</th>
                                <th class="border border-slate-200 px-3 py-2 text-left">Usage Left</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($availableCoupons as $coupon)
                                <tr>
                                    <td class="border border-slate-200 px-3 py-2 font-medium">{{ $coupon->code }}</td>
                                    <td class="border border-slate-200 px-3 py-2">
                                        @if ($coupon->type === \App\Models\Coupon::TYPE_PERCENT)
                                            {{ rtrim(rtrim(number_format((float) $coupon->value, 2), '0'), '.') }}%
                                        @else
                                            €{{ number_format((float) $coupon->value, 2) }}
                                        @endif
                                    </td>
                                    <td class="border border-slate-200 px-3 py-2 text-xs text-slate-700">
                                        <p>{{ $coupon->is_stackable ? 'Can be combined' : 'Cannot be combined' }}</p>
                                        <p>Group: {{ $coupon->stack_group ?: '-' }}</p>
                                        <p>Scope: {{ ucfirst($coupon->scope_type ?: \App\Models\Coupon::SCOPE_ALL) }}</p>
                                    </td>
                                    <td class="border border-slate-200 px-3 py-2">
                                        @if ($coupon->pivot->usage_limit !== null)
                                            {{ max(0, (int) $coupon->pivot->usage_limit - (int) $coupon->pivot->used_count) }}
                                        @else
                                            Unlimited
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="border border-slate-200 px-3 py-5 text-center text-slate-500">No active discount codes are assigned to your account.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mt-6 rounded border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">Favorite Artists & Brands</h2>
                <p class="mt-1 text-sm text-slate-500">Follow artists and brands you care about and control drop or discount emails per favorite.</p>

                @if ($availableTags->isNotEmpty())
                    <form method="POST" action="{{ route('account.tag-follows.store') }}" class="mt-5 rounded border border-slate-200 p-4">
                        @csrf

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium">Add artist/brand</label>
                                <select name="tag_id" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" required>
                                    <option value="">Select one...</option>
                                    @foreach ($availableTags as $tag)
                                        <option value="{{ $tag->id }}">{{ ucfirst($tag->type) }}: {{ $tag->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="space-y-2">
                                <label class="flex cursor-pointer items-center gap-2 text-sm">
                                    <input type="checkbox" name="notify_new_drops" value="1" checked class="rounded border-slate-300 text-blue-600">
                                    <span>Notify me when a new product drops</span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 text-sm">
                                    <input type="checkbox" name="notify_discounts" value="1" checked class="rounded border-slate-300 text-blue-600">
                                    <span>Notify me when products get discounted</span>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="mt-4 rounded bg-slate-700 px-4 py-2 text-sm text-white hover:bg-slate-800">Follow</button>
                    </form>
                @else
                    <p class="mt-5 rounded border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">No more active artists/brands are available to follow right now.</p>
                @endif

                <div class="mt-4 space-y-3">
                    @forelse ($tagFollows as $tagFollow)
                        <article class="rounded border border-slate-200 p-4">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-slate-800">
                                    {{ ucfirst($tagFollow->tag->type) }}: {{ $tagFollow->tag->name }}
                                </h3>
                                @if (! $tagFollow->tag->is_active)
                                    <span class="rounded bg-amber-100 px-2 py-1 text-xs text-amber-700">Inactive</span>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('account.tag-follows.update', $tagFollow) }}" class="grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                                @csrf
                                @method('PATCH')

                                <div class="space-y-2">
                                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                                        <input type="checkbox" name="notify_new_drops" value="1" @checked($tagFollow->notify_new_drops) class="rounded border-slate-300 text-blue-600">
                                        <span>Email me for new drops</span>
                                    </label>

                                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                                        <input type="checkbox" name="notify_discounts" value="1" @checked($tagFollow->notify_discounts) class="rounded border-slate-300 text-blue-600">
                                        <span>Email me for discounts</span>
                                    </label>
                                </div>

                                <div class="flex items-center gap-3">
                                    <button type="submit" class="rounded bg-blue-600 px-3 py-2 text-xs font-medium text-white hover:bg-blue-700">Save</button>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('account.tag-follows.destroy', $tagFollow) }}" class="mt-3">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-700 hover:underline" onclick="return confirm('Remove this favorite?')">Unfollow</button>
                            </form>
                        </article>
                    @empty
                        <p class="rounded border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">You are not following any artists or brands yet.</p>
                    @endforelse
                </div>

                @if ($tagFollows->hasPages())
                    <div class="mt-4">
                        {{ $tagFollows->links() }}
                    </div>
                @endif
            </section>
        </main>
    </body>
</html>
