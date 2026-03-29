<x-mail::message>
# Good news, {{ $user->name }}!

**{{ $variant->product?->name }} ({{ $variant->name }})** is back in stock.

<x-mail::button :url="route('shop.show', $variant->product)">
Shop Now
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
