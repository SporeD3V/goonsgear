<?php

namespace App\Observers;

use App\Mail\BackInStockAlert;
use App\Mail\CartItemDiscounted;
use App\Mail\CartItemLowStock;
use App\Mail\TagDiscountNotification;
use App\Models\ProductVariant;
use App\Models\StockAlertSubscription;
use App\Models\TagFollow;
use App\Models\TagNotificationDispatch;
use App\Models\UserCartItem;
use Illuminate\Support\Facades\Mail;

class ProductVariantObserver
{
    private const LOW_STOCK_THRESHOLD = 5;

    public function updated(ProductVariant $variant): void
    {
        $this->checkPriceDiscount($variant);
        $this->checkLowStock($variant);
        $this->checkBackInStock($variant);
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

        $this->notifyTagFollowersAboutDiscount($variant, $oldPrice, $newPrice);
    }

    private function notifyTagFollowersAboutDiscount(ProductVariant $variant, float $oldPrice, float $newPrice): void
    {
        $product = $variant->product;

        if ($product === null || $product->status !== 'active') {
            return;
        }

        $product->loadMissing('tags:id,name,type,is_active');
        $activeTagIds = $product->tags
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if ($activeTagIds === []) {
            return;
        }

        $priceReference = number_format($newPrice, 2, '.', '');

        TagFollow::query()
            ->whereIn('tag_id', $activeTagIds)
            ->where('notify_discounts', true)
            ->with([
                'user:id,name,email',
                'tag:id,name,type,is_active',
            ])
            ->get()
            ->each(function (TagFollow $tagFollow) use ($variant, $product, $oldPrice, $newPrice, $priceReference): void {
                $user = $tagFollow->user;
                $tag = $tagFollow->tag;

                if ($user === null || $tag === null || ! $tag->is_active) {
                    return;
                }

                $dispatch = TagNotificationDispatch::query()->firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'tag_id' => $tag->id,
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                        'notification_type' => 'discount',
                        'reference' => $priceReference,
                    ],
                    [
                        'dispatched_at' => now(),
                    ]
                );

                if ($dispatch->wasRecentlyCreated) {
                    Mail::to($user)->queue(new TagDiscountNotification($user, $tag, $variant, $oldPrice, $newPrice));
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

    private function checkBackInStock(ProductVariant $variant): void
    {
        if (! $variant->wasChanged('stock_quantity') || ! $variant->track_inventory) {
            return;
        }

        $oldStock = (int) $variant->getOriginal('stock_quantity');
        $newStock = (int) $variant->stock_quantity;

        if ($oldStock > 0 || $newStock <= 0) {
            return;
        }

        $subscriptions = StockAlertSubscription::query()
            ->where('product_variant_id', $variant->id)
            ->where('is_active', true)
            ->with('user')
            ->get();

        $subscriptions->each(function (StockAlertSubscription $subscription) use ($variant): void {
            if ($subscription->user !== null) {
                Mail::to($subscription->user)->queue(new BackInStockAlert($subscription->user, $variant));
            }
        });

        StockAlertSubscription::query()
            ->whereIn('id', $subscriptions->pluck('id'))
            ->update([
                'is_active' => false,
                'notified_at' => now(),
            ]);
    }
}
