<?php

namespace App\Observers;

use App\Mail\CartItemDiscounted;
use App\Mail\CartItemLowStock;
use App\Models\ProductVariant;
use App\Models\UserCartItem;
use Illuminate\Support\Facades\Mail;

class ProductVariantObserver
{
    private const LOW_STOCK_THRESHOLD = 5;

    public function updated(ProductVariant $variant): void
    {
        $this->checkPriceDiscount($variant);
        $this->checkLowStock($variant);
    }

    private function checkPriceDiscount(ProductVariant $variant): void
    {
        if (! $variant->wasChanged('price')) {
            return;
        }

        $oldPrice = (float) $variant->getOriginal('price');
        $newPrice = (float) $variant->price;

        if ($newPrice >= $oldPrice) {
            return;
        }

        UserCartItem::query()
            ->where('product_variant_id', $variant->id)
            ->with('user')
            ->get()
            ->each(function (UserCartItem $cartItem) use ($variant, $oldPrice): void {
                $user = $cartItem->user;

                if ($user !== null && $user->notify_cart_discounts) {
                    Mail::to($user)->queue(new CartItemDiscounted($user, $variant, $oldPrice));
                }
            });
    }

    private function checkLowStock(ProductVariant $variant): void
    {
        if (! $variant->wasChanged('stock_quantity') || ! $variant->track_inventory) {
            return;
        }

        $oldStock = (int) $variant->getOriginal('stock_quantity');
        $newStock = (int) $variant->stock_quantity;

        if ($oldStock <= self::LOW_STOCK_THRESHOLD || $newStock > self::LOW_STOCK_THRESHOLD || $newStock <= 0) {
            return;
        }

        UserCartItem::query()
            ->where('product_variant_id', $variant->id)
            ->with('user')
            ->get()
            ->each(function (UserCartItem $cartItem) use ($variant): void {
                $user = $cartItem->user;

                if ($user !== null && $user->notify_cart_low_stock) {
                    Mail::to($user)->queue(new CartItemLowStock($user, $variant));
                }
            });
    }
}
