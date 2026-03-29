<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    private const CART_SESSION_KEY = 'cart.items';

    public function index(Request $request): View
    {
        $cartItems = $this->getCartItems($request);
        $subtotal = collect($cartItems)->sum(fn (array $item): float => (float) $item['price'] * (int) $item['quantity']);

        return view('cart.index', [
            'items' => $cartItems,
            'subtotal' => $subtotal,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $variant = ProductVariant::query()
            ->with([
                'product:id,name,slug,status',
                'product.media' => fn ($query) => $query
                    ->orderByDesc('is_primary')
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->findOrFail($payload['variant_id']);

        if (! $variant->is_active || $variant->product?->status !== 'active') {
            return back()->withErrors(['cart' => 'This variant is not available for purchase.']);
        }

        $cartItems = $this->getCartItems($request);
        $existingItem = $cartItems[$variant->id] ?? null;
        $requestedQuantity = (int) $payload['quantity'];
        $nextQuantity = (int) ($existingItem['quantity'] ?? 0) + $requestedQuantity;

        $maxQuantity = $this->getMaxAllowedQuantity($variant);

        if ($maxQuantity !== null && $nextQuantity > $maxQuantity) {
            return back()->withErrors([
                'cart' => "Only {$maxQuantity} unit(s) are currently available for {$variant->name}.",
            ]);
        }

        $primaryMedia = $variant->product?->media->first();

        $cartItems[$variant->id] = [
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'product_name' => (string) $variant->product?->name,
            'product_slug' => (string) $variant->product?->slug,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'price' => (float) $variant->price,
            'quantity' => $nextQuantity,
            'max_quantity' => $maxQuantity,
            'image' => $primaryMedia ? route('media.show', ['path' => $primaryMedia->path]) : null,
            'url' => $variant->product ? route('shop.show', $variant->product) : null,
        ];

        $request->session()->put(self::CART_SESSION_KEY, $cartItems);

        return back()->with('status', 'Added item to cart.');
    }

    public function update(Request $request, ProductVariant $variant): RedirectResponse
    {
        $payload = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $cartItems = $this->getCartItems($request);

        if (! isset($cartItems[$variant->id])) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Item not found in your cart.']);
        }

        $maxQuantity = $this->getMaxAllowedQuantity($variant);
        $nextQuantity = (int) $payload['quantity'];

        if ($maxQuantity !== null && $nextQuantity > $maxQuantity) {
            return redirect()->route('cart.index')->withErrors([
                'cart' => "Only {$maxQuantity} unit(s) are currently available for {$variant->name}.",
            ]);
        }

        $cartItems[$variant->id]['quantity'] = $nextQuantity;
        $cartItems[$variant->id]['max_quantity'] = $maxQuantity;

        $request->session()->put(self::CART_SESSION_KEY, $cartItems);

        return redirect()->route('cart.index')->with('status', 'Cart item updated.');
    }

    public function destroy(Request $request, ProductVariant $variant): RedirectResponse
    {
        $cartItems = $this->getCartItems($request);
        unset($cartItems[$variant->id]);

        $request->session()->put(self::CART_SESSION_KEY, $cartItems);

        return redirect()->route('cart.index')->with('status', 'Item removed from cart.');
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function getCartItems(Request $request): array
    {
        $items = $request->session()->get(self::CART_SESSION_KEY, []);

        return is_array($items) ? $items : [];
    }

    private function getMaxAllowedQuantity(ProductVariant $variant): ?int
    {
        if (! $variant->track_inventory || $variant->allow_backorder || $variant->is_preorder) {
            return null;
        }

        return max(0, (int) $variant->stock_quantity);
    }
}
