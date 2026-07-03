<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Order {{ $order->order_number }} | GoonsGear</title>
        @include('partials.favicons')
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-white text-black">
        @include('partials.header')

        @php
            $statusBadge = fn (string $status): string => match ($status) {
                'paid', 'completed', 'delivered' => 'bg-emerald-100 text-emerald-800',
                'processing', 'shipped', 'pre-ordered' => 'bg-blue-100 text-blue-800',
                'pending', 'on-hold' => 'bg-amber-100 text-amber-800',
                'cancelled', 'refunded', 'failed' => 'bg-red-100 text-red-700',
                default => 'bg-black/10 text-black/70',
            };

            $currencySymbol = $order->currency === 'EUR' ? '€' : $order->currency.' ';
            $discounts = (float) $order->discount_total
                + (float) $order->regional_discount_total
                + (float) $order->bundle_discount_total;
        @endphp

        <main class="mx-auto max-w-4xl px-6 py-8">

            <a href="{{ route('account.index') }}#orders" class="inline-flex items-center gap-1.5 text-sm font-medium text-black/60 transition hover:text-black">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                Back to my orders
            </a>

            {{-- Order header --}}
            <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-black/40">Order</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight">{{ $order->order_number }}</h1>
                    <p class="mt-1 text-sm text-black/50">Placed {{ optional($order->placed_at)->format('F j, Y') ?? '—' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $statusBadge($order->status) }}">{{ ucfirst($order->status) }}</span>
                    @if ($order->payment_status !== $order->status)
                        <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $statusBadge($order->payment_status) }}">{{ ucfirst($order->payment_status) }}</span>
                    @endif
                </div>
            </div>

            {{-- Items & totals --}}
            <section class="mt-6 rounded-xl border border-black/10 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold tracking-tight">Items</h2>

                <ul class="mt-4 divide-y divide-black/5">
                    @foreach ($order->items as $item)
                        <li class="flex flex-wrap items-center gap-x-4 gap-y-1 py-3 first:pt-0">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold">{{ $item->product_name }}</p>
                                <p class="mt-0.5 text-xs text-black/50">
                                    @if (filled($item->variant_name))
                                        {{ $item->variant_name }} &middot;
                                    @endif
                                    {{ $item->quantity }} × {{ $currencySymbol }}{{ number_format((float) $item->unit_price, 2) }}
                                </p>
                            </div>
                            <p class="text-sm font-bold">{{ $currencySymbol }}{{ number_format((float) $item->line_total, 2) }}</p>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-4 space-y-2 border-t border-black/10 pt-4">
                    <div class="flex items-center justify-between text-sm text-black/60">
                        <p>Subtotal</p>
                        <p>{{ $currencySymbol }}{{ number_format((float) $order->subtotal, 2) }}</p>
                    </div>
                    @if ($discounts > 0)
                        <div class="flex items-center justify-between text-sm text-emerald-700">
                            <p>Discount{{ filled($order->coupon_code) ? ' ('.$order->coupon_code.')' : '' }}</p>
                            <p>-{{ $currencySymbol }}{{ number_format($discounts, 2) }}</p>
                        </div>
                    @endif
                    @if ((float) ($order->shipping_total ?? 0) > 0)
                        <div class="flex items-center justify-between text-sm text-black/60">
                            <p>Shipping</p>
                            <p>{{ $currencySymbol }}{{ number_format((float) $order->shipping_total, 2) }}</p>
                        </div>
                    @endif
                    @if ((float) ($order->tax_total ?? 0) > 0)
                        <div class="flex items-center justify-between text-sm text-black/60">
                            <p>Included VAT</p>
                            <p>{{ $currencySymbol }}{{ number_format((float) $order->tax_total, 2) }}</p>
                        </div>
                    @endif
                    <div class="flex items-center justify-between border-t border-black/10 pt-3">
                        <p class="text-sm font-bold uppercase tracking-wider">Total</p>
                        <p class="text-lg font-black">{{ $currencySymbol }}{{ number_format((float) $order->total, 2) }}</p>
                    </div>
                    @if ((float) ($order->refund_total ?? 0) > 0)
                        <div class="flex items-center justify-between text-sm font-medium text-red-600">
                            <p>Refunded</p>
                            <p>-{{ $currencySymbol }}{{ number_format((float) $order->refund_total, 2) }}</p>
                        </div>
                    @endif
                </div>
            </section>

            <div class="mt-6 grid gap-6 sm:grid-cols-2">
                {{-- Delivery --}}
                <section class="rounded-xl border border-black/10 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-black/50">Delivery</h2>
                    <p class="mt-3 text-sm font-bold">{{ $order->first_name }} {{ $order->last_name }}</p>
                    <p class="mt-1 text-sm text-black/70">{{ $order->street_name }} {{ $order->street_number }}</p>
                    <p class="text-sm text-black/70">{{ $order->postal_code }} {{ $order->city }}</p>
                    <p class="text-sm text-black/70">{{ $order->country }}</p>

                    @if (filled($order->tracking_number))
                        <div class="mt-4 border-t border-black/5 pt-3">
                            <p class="text-xs text-black/50">Tracking number</p>
                            <p class="mt-0.5 text-sm font-bold">{{ $order->tracking_number }}</p>
                            @if ($dhlTrackingUrl)
                                <a href="{{ $dhlTrackingUrl }}" target="_blank" rel="noreferrer" class="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-black/15 px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-black/70 transition hover:bg-black hover:text-white">
                                    Track shipment
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                </a>
                            @endif
                        </div>
                    @endif
                </section>

                {{-- Payment & invoice --}}
                <section class="rounded-xl border border-black/10 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-black/50">Payment</h2>
                    <p class="mt-3 text-sm text-black/70">Method: <span class="font-bold text-black">{{ ucfirst($order->payment_method) }}</span></p>
                    <p class="mt-1 text-sm text-black/70">Status: <span class="font-bold text-black">{{ ucfirst($order->payment_status) }}</span></p>

                    <div class="mt-4 border-t border-black/5 pt-3">
                        @if ($order->invoice)
                            <p class="text-xs text-black/50">Invoice {{ $order->invoice->invoice_number }}</p>
                            <a href="{{ route('account.orders.invoice', $order) }}"
                               class="mt-2 inline-flex items-center gap-2 rounded-lg bg-black px-4 py-2 text-xs font-bold uppercase tracking-wider text-white transition hover:bg-black/80">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                Download invoice
                            </a>
                        @else
                            <p class="text-xs text-black/40">No invoice is available for this order yet.</p>
                        @endif
                    </div>
                </section>
            </div>
        </main>

        @include('partials.footer')
    </body>
</html>
