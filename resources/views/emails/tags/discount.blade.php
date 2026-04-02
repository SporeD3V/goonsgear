<x-mail::message>
# {{ ucfirst($tag->type) }} Discount Alert

Hey {{ $user->name }},

{{ $tag->name }} just got a discount on a product you may want.

**Product:** {{ $variant->product?->name }}

**Variant:** {{ $variant->name }}

**Old price:** €{{ number_format($oldPrice, 2) }}  
**New price:** €{{ number_format($newPrice, 2) }}

<x-mail::button :url="route('shop.show', $variant->product)">
View Product
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
