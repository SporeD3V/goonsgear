<x-mail::message>
# Thank you for your order!

Hi {{ $order->first_name }},

Your order **#{{ $order->order_number }}** has been received and is being processed.

<x-mail::table>
| Item | Qty | Total |
| :--- | :---: | ---: |
@foreach ($order->items as $item)
| {{ $item->product_name }}{{ $item->variant_name ? ' ('.$item->variant_name.')' : '' }} | {{ $item->quantity }} | €{{ number_format((float) $item->line_total, 2) }} |
@endforeach
</x-mail::table>

---

**Subtotal:** €{{ number_format((float) $order->subtotal, 2) }}

@if ((float) $order->discount_total > 0)
**Discount{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}:** − €{{ number_format((float) $order->discount_total, 2) }}

@endif
@if ((float) $order->bundle_discount_total > 0)
**Bundle Discount{{ $order->bundle_sku ? ' ('.$order->bundle_sku.')' : '' }}:** − €{{ number_format((float) $order->bundle_discount_total, 2) }}

@endif
**Grand Total:** €{{ number_format((float) $order->total, 2) }}

---

**Shipping to:**

{{ $order->first_name }} {{ $order->last_name }}
{{ $order->street_name }} {{ $order->street_number }}{{ $order->apartment_block ? ', '.$order->apartment_block : '' }}

{{ $order->postal_code }} {{ $order->city }}{{ $order->state ? ', '.$order->state : '' }}

{{ $order->country }}

<x-mail::button :url="route('shop.index')">
Continue Shopping
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
