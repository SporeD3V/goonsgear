<?php

use App\Models\UserCartItem;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    /**
     * @return array{items: Collection, count: int, subtotal: float}
     */
    public function getCartData(): array
    {
        $items = collect(session('cart.items', []));

        return [
            'items' => $items,
            'count' => $items->sum('quantity'),
            'subtotal' => $items->sum(fn ($i) => (float) ($i['price'] ?? 0) * (int) $i['quantity']),
        ];
    }

    public function removeItem(int $variantId): void
    {
        $cartItems = session('cart.items', []);

        if (! is_array($cartItems)) {
            $cartItems = [];
        }

        unset($cartItems[$variantId]);

        session()->put('cart.items', $cartItems);

        $user = auth()->user();

        if ($user) {
            UserCartItem::query()
                ->where('user_id', $user->id)
                ->where('product_variant_id', $variantId)
                ->delete();
        }
    }

    #[On('cart-updated')]
    public function refresh(): void
    {
        // Re-renders the component with fresh session data.
    }
};
