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
                                <th class="border-b border-slate-200 px-3 py-2 text-left">Thumb</th>
                                <th class="border-b border-slate-200 px-3 py-2 text-left">Item</th>
                                <th class="border-b border-slate-200 px-3 py-2 text-left">Qty</th>
                                <th class="border-b border-slate-200 px-3 py-2 text-left">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->items as $item)
                                <tr>
                                    <td class="border-b border-slate-200 px-3 py-2">
                                        @php
                                            $primaryMedia = $item->product?->media->first();
                                            $thumbnailPath = $primaryMedia?->thumbnail_path ?? $primaryMedia?->path;
                                        @endphp
                                        @if ($thumbnailPath)
                                            <img src="{{ route('media.show', ['path' => $thumbnailPath]) }}" alt="{{ $item->product_name }}" class="h-12 w-12 rounded object-cover">
                                        @else
                                            <img src="{{ asset('images/placeholder-product.svg') }}" alt="No image available" class="h-12 w-12 rounded object-cover">
                                        @endif
                                    </td>
                                    <td class="border-b border-slate-200 px-3 py-2">{{ $item->product_name }} @if($item->variant_name)({{ $item->variant_name }})@endif</td>
                                    <td class="border-b border-slate-200 px-3 py-2">{{ $item->quantity }}</td>
                                    <td class="border-b border-slate-200 px-3 py-2">${{ number_format((float) $item->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 space-y-2">
                    <div class="flex items-center justify-between text-sm text-slate-600">
                        <p>Subtotal</p>
                        <p>${{ number_format((float) $order->subtotal, 2) }}</p>
                    </div>
                    @if ((float) $order->discount_total > 0)
                        <div class="flex items-center justify-between text-sm text-emerald-700">
                            <p>Discount @if ($order->coupon_code)( {{ $order->coupon_code }} )@endif</p>
                            <p>- ${{ number_format((float) $order->discount_total, 2) }}</p>
                        </div>
                    @endif
                    <div class="flex items-center justify-between border-t border-slate-200 pt-3">
                        <p class="text-sm text-slate-600">Grand total</p>
                        <p class="text-lg font-semibold">${{ number_format((float) $order->total, 2) }}</p>
                    </div>
                </div>

                <div class="mt-6">
                    <a href="{{ route('shop.index') }}" class="text-sm text-blue-700 hover:underline">Continue shopping</a>
                </div>

                @if ($sizePromptData)
                    <section class="mt-6 rounded border border-blue-200 bg-blue-50 p-4">
                        <p class="text-sm text-slate-700">
                            You ordered sizes: <strong>{{ implode(', ', $sizePromptData['orderedSizes']) }}</strong>
                        </p>

                        @if ($sizePromptData['type'] === 'create')
                            {{-- User has no self-profile → offer to save their sizes --}}
                            <p class="mt-2 text-sm text-slate-600">Save your sizes so you can filter the catalog next time.</p>

                            <form method="POST" action="{{ route('account.size-profiles.store') }}" class="mt-3 space-y-3">
                                @csrf
                                <input type="hidden" name="is_self" value="1">
                                <input type="hidden" name="name" value="{{ auth()->user()->name ?? 'Me' }}">
                                <input type="hidden" name="_redirect" value="{{ request()->getPathInfo() }}">

                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-700">Top size</label>
                                        <select name="top_size" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                                            <option value="">—</option>
                                            @foreach (['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '2XL', '3XL', '4XL', '5XL'] as $size)
                                                <option value="{{ $size }}" @selected(in_array($size, $sizePromptData['orderedSizes'], true))>{{ $size }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-700">Bottom size</label>
                                        <select name="bottom_size" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                                            <option value="">—</option>
                                            @foreach (['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '2XL', '3XL', '4XL', '5XL'] as $size)
                                                <option value="{{ $size }}">{{ $size }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-700">Shoe size</label>
                                        <input type="text" name="shoe_size" maxlength="20" placeholder="e.g. 42" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                                    </div>
                                </div>

                                <button type="submit" class="rounded bg-blue-700 px-3 py-1.5 text-sm text-white hover:bg-blue-800">Save my sizes</button>
                            </form>

                        @elseif ($sizePromptData['type'] === 'mismatch')
                            {{-- User has a self-profile but ordered sizes differ --}}
                            <p class="mt-2 text-sm text-slate-600">These sizes differ from your saved profile. Would you like to update your profile or add another person?</p>

                            <div class="mt-3 space-y-4">
                                {{-- Option 1: Update existing self-profile --}}
                                <form method="POST" action="{{ route('account.size-profiles.update', $sizePromptData['selfProfile']) }}" class="rounded border border-slate-200 bg-white p-3">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="name" value="{{ $sizePromptData['selfProfile']->name }}">
                                    <input type="hidden" name="_redirect" value="{{ request()->getPathInfo() }}">

                                    <p class="mb-2 text-sm font-medium text-slate-700">Update my sizes</p>

                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-700">Top size</label>
                                            <select name="top_size" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                                                <option value="">—</option>
                                                @foreach (['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '2XL', '3XL', '4XL', '5XL'] as $size)
                                                    <option value="{{ $size }}" @selected($sizePromptData['selfProfile']->top_size === $size)>{{ $size }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-700">Bottom size</label>
                                            <select name="bottom_size" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                                                <option value="">—</option>
                                                @foreach (['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '2XL', '3XL', '4XL', '5XL'] as $size)
                                                    <option value="{{ $size }}" @selected($sizePromptData['selfProfile']->bottom_size === $size)>{{ $size }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-700">Shoe size</label>
                                            <input type="text" name="shoe_size" maxlength="20" value="{{ $sizePromptData['selfProfile']->shoe_size }}" placeholder="e.g. 42" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                                        </div>
                                    </div>

                                    <button type="submit" class="mt-2 rounded bg-blue-700 px-3 py-1.5 text-sm text-white hover:bg-blue-800">Update my sizes</button>
                                </form>

                                {{-- Option 2: Add another person --}}
                                <form method="POST" action="{{ route('account.size-profiles.store') }}" class="rounded border border-slate-200 bg-white p-3">
                                    @csrf
                                    <input type="hidden" name="is_self" value="0">
                                    <input type="hidden" name="_redirect" value="{{ request()->getPathInfo() }}">

                                    <p class="mb-2 text-sm font-medium text-slate-700">Add another person</p>

                                    <div class="grid gap-3 sm:grid-cols-4">
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-700">Name</label>
                                            <input type="text" name="name" required maxlength="100" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm" placeholder="Their name">
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-700">Top size</label>
                                            <select name="top_size" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                                                <option value="">—</option>
                                                @foreach (['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '2XL', '3XL', '4XL', '5XL'] as $size)
                                                    <option value="{{ $size }}" @selected(in_array($size, $sizePromptData['orderedSizes'], true))>{{ $size }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-700">Bottom size</label>
                                            <select name="bottom_size" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                                                <option value="">—</option>
                                                @foreach (['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '2XL', '3XL', '4XL', '5XL'] as $size)
                                                    <option value="{{ $size }}">{{ $size }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-700">Shoe size</label>
                                            <input type="text" name="shoe_size" maxlength="20" placeholder="e.g. 42" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                                        </div>
                                    </div>

                                    <button type="submit" class="mt-2 rounded bg-slate-700 px-3 py-1.5 text-sm text-white hover:bg-slate-800">Add person</button>
                                </form>
                            </div>
                        @endif
                    </section>
                @endif
            </section>
        </div>
    </body>
</html>
