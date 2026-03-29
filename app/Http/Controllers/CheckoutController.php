<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    private const CART_SESSION_KEY = 'cart.items';

    public function index(Request $request): View|RedirectResponse
    {
        $items = $this->getCartItems($request);

        if ($items === []) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Your cart is empty.']);
        }

        $subtotal = collect($items)->sum(fn (array $item): float => (float) $item['price'] * (int) $item['quantity']);

        return view('checkout.index', [
            'items' => $items,
            'subtotal' => $subtotal,
            'formDefaults' => [
                'email' => (string) old('email', ''),
                'first_name' => (string) old('first_name', ''),
                'last_name' => (string) old('last_name', ''),
                'phone' => (string) old('phone', ''),
                'country' => (string) old('country', 'DE'),
                'city' => (string) old('city', ''),
                'postal_code' => (string) old('postal_code', ''),
                'address_line_1' => (string) old('address_line_1', ''),
                'address_line_2' => (string) old('address_line_2', ''),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $items = $this->getCartItems($request);

        if ($items === []) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Your cart is empty.']);
        }

        $payload = $request->validate([
            'email' => ['required', 'email'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['required', 'string', 'size:2'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:20'],
            'address_line_1' => ['required', 'string', 'max:200'],
            'address_line_2' => ['nullable', 'string', 'max:200'],
        ]);

        $variantIds = collect($items)->pluck('variant_id')->filter()->map(fn ($id): int => (int) $id)->values();

        $variants = ProductVariant::query()
            ->with('product:id,status')
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $normalizedItems = [];

        foreach ($items as $item) {
            $variantId = (int) Arr::get($item, 'variant_id');
            $quantity = (int) Arr::get($item, 'quantity', 0);
            $variant = $variants->get($variantId);

            if ($variant === null || ! $variant->is_active || $variant->product?->status !== 'active') {
                return redirect()->route('cart.index')->withErrors(['cart' => 'One or more cart items are no longer available.']);
            }

            if ($quantity < 1) {
                return redirect()->route('cart.index')->withErrors(['cart' => 'Cart contains an invalid quantity.']);
            }

            if ($variant->track_inventory && ! $variant->allow_backorder && ! $variant->is_preorder && $quantity > $variant->stock_quantity) {
                return redirect()->route('cart.index')->withErrors([
                    'cart' => "Insufficient stock for {$variant->name}. Please update your cart quantity.",
                ]);
            }

            $unitPrice = (float) $variant->price;

            $normalizedItems[] = [
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'product_name' => (string) Arr::get($item, 'product_name', ''),
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => $unitPrice * $quantity,
            ];
        }

        $subtotal = collect($normalizedItems)->sum('line_total');

        $order = DB::transaction(function () use ($payload, $normalizedItems, $variants, $subtotal) {
            $order = Order::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'status' => 'pending',
                'email' => $payload['email'],
                'first_name' => $payload['first_name'],
                'last_name' => $payload['last_name'],
                'phone' => $payload['phone'] ?? null,
                'country' => strtoupper($payload['country']),
                'city' => $payload['city'],
                'postal_code' => $payload['postal_code'],
                'address_line_1' => $payload['address_line_1'],
                'address_line_2' => $payload['address_line_2'] ?? null,
                'currency' => 'EUR',
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'placed_at' => now(),
            ]);

            $order->items()->createMany($normalizedItems);

            foreach ($normalizedItems as $item) {
                $variant = $variants->get((int) $item['product_variant_id']);

                if ($variant !== null && $variant->track_inventory && ! $variant->allow_backorder && ! $variant->is_preorder) {
                    $variant->decrement('stock_quantity', (int) $item['quantity']);
                }
            }

            return $order;
        });

        $request->session()->forget(self::CART_SESSION_KEY);

        return redirect()->route('checkout.success', $order)->with('status', 'Order placed successfully.');
    }

    public function success(Order $order): View
    {
        $order->load('items');

        return view('checkout.success', [
            'order' => $order,
        ]);
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function getCartItems(Request $request): array
    {
        $items = $request->session()->get(self::CART_SESSION_KEY, []);

        return is_array($items) ? $items : [];
    }

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'GG-'.strtoupper(Str::random(10));
        } while (Order::query()->where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }
}
