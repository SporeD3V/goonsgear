<x-mail::message>
# Great news — your cart item just got cheaper!

Hi {{ $user->name }},

The price of **{{ $variant->product?->name }} ({{ $variant->name }})** in your cart has just been reduced!

| | |
| :--- | ---: |
| Was | €{{ number_format($oldPrice, 2) }} |
| **Now** | **€{{ number_format((float) $variant->price, 2) }}** |

Head over to your cart to take advantage of this new price.

<x-mail::button :url="route('cart.index')">
View My Cart
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

{{ config('app.name') }}
</x-mail::message>
