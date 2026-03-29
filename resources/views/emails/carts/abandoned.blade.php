<x-mail::message>
# You left something behind!

Hi there,

You added items to your cart but didn't complete your order. Here's what you left:

<x-mail::table>
| Item | Qty | Price |
| :--- | :---: | ---: |
@foreach ($abandonment->cart_data as $item)
| {{ $item['product_name'] }}{{ $item['variant_name'] ? ' ('.$item['variant_name'].')' : '' }} | {{ $item['quantity'] }} | €{{ number_format((float) ($item['price'] * $item['quantity']), 2) }} |
@endforeach
</x-mail::table>

Your cart will be waiting for you — just click the button below to pick up where you left off.

<x-mail::button :url="route('cart.recover', $abandonment->token)">
Complete My Order
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
