<x-mail::message>
# New {{ ucfirst($tag->type) }} Drop

Hey {{ $user->name }},

A new product from **{{ $tag->name }}** just dropped on GoonsGear.

**Product:** {{ $product->name }}

@if ($product->excerpt)
{{ $product->excerpt }}
@endif

<x-mail::button :url="route('shop.show', $product)">
View Product
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
