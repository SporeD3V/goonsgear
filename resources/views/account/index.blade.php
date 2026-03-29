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
            </section>
        </main>
    </body>
</html>
