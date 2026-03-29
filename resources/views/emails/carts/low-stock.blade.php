<x-mail::message>
# Stock is running low on your cart item!

Hi {{ $user->name }},

Just a heads up — **{{ $variant->product?->name }} ({{ $variant->name }})** in your cart is almost sold out. Only **{{ $variant->stock_quantity }}** left in stock!

Don't miss out — grab yours before it's gone.

<x-mail::button :url="route('shop.show', $variant->product)">
Shop Now
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

{{ config('app.name') }}
</x-mail::message>
