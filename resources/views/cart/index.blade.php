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

            @if (empty($items))
                <p class="rounded border border-slate-200 bg-white p-4 text-sm text-slate-600">Your cart is empty.</p>
            @else
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
                                                <div class="h-16 w-16 rounded bg-slate-100"></div>
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
                                            <input
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

                <div class="mt-4 flex items-center justify-between rounded border border-slate-200 bg-white p-4">
                    <p class="text-sm text-slate-600">Subtotal</p>
                    <div class="flex items-center gap-4">
                        <p class="text-lg font-semibold">${{ number_format((float) $subtotal, 2) }}</p>
                        <a href="{{ route('checkout.index') }}" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Proceed to checkout</a>
                    </div>
                </div>
            @endif
        </div>
    </body>
</html>
