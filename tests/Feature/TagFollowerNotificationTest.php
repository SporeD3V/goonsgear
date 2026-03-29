<?php

namespace Tests\Feature;

use App\Mail\TagDiscountNotification;
use App\Mail\TagDropNotification;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Models\TagFollow;
use App\Models\TagNotificationDispatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TagFollowerNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Followers receive drop emails when a tagged product becomes active.
     */
    public function test_drop_notification_sent_to_followers_who_opted_in(): void
    {
        Mail::fake();

        $tag = Tag::factory()->create([
            'name' => 'Street Poets',
            'type' => 'artist',
            'is_active' => true,
        ]);

        $optedInUser = User::factory()->create();
        $optedOutUser = User::factory()->create();

        TagFollow::factory()->create([
            'user_id' => $optedInUser->id,
            'tag_id' => $tag->id,
            'notify_new_drops' => true,
        ]);

        TagFollow::factory()->create([
            'user_id' => $optedOutUser->id,
            'tag_id' => $tag->id,
            'notify_new_drops' => false,
        ]);

        $product = Product::factory()->create([
            'status' => 'draft',
        ]);
        $product->tags()->sync([$tag->id]);

        $product->update(['status' => 'active']);

        Mail::assertQueued(TagDropNotification::class, function (TagDropNotification $mail) use ($optedInUser, $tag, $product) {
            return $mail->user->id === $optedInUser->id
                && $mail->tag->id === $tag->id
                && $mail->product->id === $product->id;
        });

        Mail::assertNotQueued(TagDropNotification::class, function (TagDropNotification $mail) use ($optedOutUser) {
            return $mail->user->id === $optedOutUser->id;
        });

        $this->assertDatabaseHas('tag_notification_dispatches', [
            'user_id' => $optedInUser->id,
            'tag_id' => $tag->id,
            'product_id' => $product->id,
            'notification_type' => 'drop',
        ]);
    }

    public function test_discount_notification_sent_when_variant_price_drops(): void
    {
        Mail::fake();

        $tag = Tag::factory()->create([
            'type' => 'brand',
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        TagFollow::factory()->create([
            'user_id' => $user->id,
            'tag_id' => $tag->id,
            'notify_discounts' => true,
        ]);

        $product = Product::factory()->create(['status' => 'active']);
        $product->tags()->sync([$tag->id]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 100.00,
            'is_active' => true,
        ]);

        $variant->update(['price' => 79.99]);

        Mail::assertQueued(TagDiscountNotification::class, function (TagDiscountNotification $mail) use ($user, $tag, $variant) {
            return $mail->user->id === $user->id
                && $mail->tag->id === $tag->id
                && $mail->variant->id === $variant->id;
        });

        $this->assertDatabaseHas('tag_notification_dispatches', [
            'user_id' => $user->id,
            'tag_id' => $tag->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'notification_type' => 'discount',
            'reference' => '79.99',
        ]);

        $this->assertGreaterThan(0, TagNotificationDispatch::query()->count());
    }

    public function test_discount_notification_not_sent_when_user_opted_out(): void
    {
        Mail::fake();

        $tag = Tag::factory()->create(['is_active' => true]);
        $user = User::factory()->create();

        TagFollow::factory()->create([
            'user_id' => $user->id,
            'tag_id' => $tag->id,
            'notify_discounts' => false,
        ]);

        $product = Product::factory()->create(['status' => 'active']);
        $product->tags()->sync([$tag->id]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 120.00,
        ]);

        $variant->update(['price' => 100.00]);

        Mail::assertNotQueued(TagDiscountNotification::class);
    }

    public function test_drop_dispatch_is_not_duplicated_for_same_user_tag_product(): void
    {
        Mail::fake();

        $tag = Tag::factory()->create(['is_active' => true]);
        $user = User::factory()->create();

        TagFollow::factory()->create([
            'user_id' => $user->id,
            'tag_id' => $tag->id,
            'notify_new_drops' => true,
        ]);

        $product = Product::factory()->create(['status' => 'active']);
        $product->tags()->sync([$tag->id]);

        $product->update(['name' => 'Renamed Active Product']);

        $dispatches = TagNotificationDispatch::query()
            ->where('notification_type', 'drop')
            ->where('user_id', $user->id)
            ->where('tag_id', $tag->id)
            ->where('product_id', $product->id)
            ->count();

        $this->assertSame(1, $dispatches);

        Mail::assertQueued(TagDropNotification::class, 1);
    }
}
