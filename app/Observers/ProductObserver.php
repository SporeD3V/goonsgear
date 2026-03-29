<?php

namespace App\Observers;

use App\Mail\TagDropNotification;
use App\Models\Product;
use App\Models\TagFollow;
use App\Models\TagNotificationDispatch;
use Illuminate\Support\Facades\Mail;

class ProductObserver
{
    public function created(Product $product): void
    {
        if ($product->status === 'active') {
            $this->notifyFollowersForDrop($product);
        }
    }

    public function updated(Product $product): void
    {
        if ($product->status !== 'active') {
            return;
        }

        $this->notifyFollowersForDrop($product);
    }

    public function notifyFollowersForDrop(Product $product): void
    {
        $product->loadMissing('tags:id,name,type,is_active');
        $activeTagIds = $product->tags
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if ($activeTagIds === []) {
            return;
        }

        TagFollow::query()
            ->whereIn('tag_id', $activeTagIds)
            ->where('notify_new_drops', true)
            ->with([
                'user:id,name,email',
                'tag:id,name,type,is_active',
            ])
            ->get()
            ->each(function (TagFollow $tagFollow) use ($product): void {
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
                        'product_variant_id' => null,
                        'notification_type' => 'drop',
                        'reference' => 'product-active',
                    ],
                    [
                        'dispatched_at' => now(),
                    ]
                );

                if ($dispatch->wasRecentlyCreated) {
                    Mail::to($user)->queue(new TagDropNotification($user, $tag, $product));
                }
            });
    }
}
