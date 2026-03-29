<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Order Confirmed | GoonsGear</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css'])
        @endif
    </head>
    <body class="bg-slate-100 text-slate-900">
        <div class="mx-auto max-w-3xl p-6">
            <section class="rounded border border-slate-200 bg-white p-6">
                <h1 class="text-2xl font-semibold">Thank you for your order</h1>
                <p class="mt-2 text-sm text-slate-600">Order number: <span class="font-medium text-slate-900">{{ $order->order_number }}</span></p>

                @if (session('status'))
                    <div class="mt-4 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('status') }}</div>
                @endif

                <div class="mt-5 rounded border border-slate-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="border-b border-slate-200 px-3 py-2 text-left">Item</th>
                                <th class="border-b border-slate-200 px-3 py-2 text-left">Qty</th>
                                <th class="border-b border-slate-200 px-3 py-2 text-left">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->items as $item)
                                <tr>
                                    <td class="border-b border-slate-200 px-3 py-2">{{ $item->product_name }} @if($item->variant_name)({{ $item->variant_name }})@endif</td>
                                    <td class="border-b border-slate-200 px-3 py-2">{{ $item->quantity }}</td>
                                    <td class="border-b border-slate-200 px-3 py-2">${{ number_format((float) $item->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <p class="text-sm text-slate-600">Grand total</p>
                    <p class="text-lg font-semibold">${{ number_format((float) $order->total, 2) }}</p>
                </div>

                <div class="mt-6">
                    <a href="{{ route('shop.index') }}" class="text-sm text-blue-700 hover:underline">Continue shopping</a>
                </div>
            </section>
        </div>
    </body>
</html>
