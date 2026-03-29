<?php

namespace Tests\Feature;

use App\Mail\CartItemDiscounted;
use App\Mail\CartItemLowStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\UserCartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CartNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function variantWithStock(int $stock, bool $trackInventory = true): ProductVariant
    {
        return ProductVariant::factory()->for(
            Product::factory()->create(['status' => 'active']),
        )->create([
            'is_active' => true,
            'track_inventory' => $trackInventory,
            'stock_quantity' => $stock,
            'price' => 50.00,
        ]);
    }

    public function test_discount_email_sent_when_price_drops(): void
    {
        Mail::fake();

        $user = User::factory()->create(['notify_cart_discounts' => true]);
        $variant = $this->variantWithStock(20);

        UserCartItem::factory()->create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
        ]);

        $variant->update(['price' => 35.00]);

        Mail::assertQueued(CartItemDiscounted::class, function (CartItemDiscounted $mail) use ($user, $variant) {
            return $mail->user->id === $user->id
                && $mail->variant->id === $variant->id
                && $mail->oldPrice === 50.00;
        });
    }

    public function test_discount_email_not_sent_when_price_increases(): void
    {
        Mail::fake();

        $user = User::factory()->create(['notify_cart_discounts' => true]);
        $variant = $this->variantWithStock(20);

        UserCartItem::factory()->create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
        ]);

        $variant->update(['price' => 75.00]);

        Mail::assertNotQueued(CartItemDiscounted::class);
    }

    public function test_discount_email_not_sent_when_user_has_opted_out(): void
    {
        Mail::fake();

        $user = User::factory()->create(['notify_cart_discounts' => false]);
        $variant = $this->variantWithStock(20);

        UserCartItem::factory()->create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
        ]);

        $variant->update(['price' => 30.00]);

        Mail::assertNotQueued(CartItemDiscounted::class);
    }

    public function test_low_stock_email_sent_when_stock_crosses_threshold(): void
    {
        Mail::fake();

        $user = User::factory()->create(['notify_cart_low_stock' => true]);
        $variant = $this->variantWithStock(10);

        UserCartItem::factory()->create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
        ]);

        $variant->update(['stock_quantity' => 3]);

        Mail::assertQueued(CartItemLowStock::class, function (CartItemLowStock $mail) use ($user, $variant) {
            return $mail->user->id === $user->id && $mail->variant->id === $variant->id;
        });
    }

    public function test_low_stock_email_not_sent_when_stock_was_already_low(): void
    {
        Mail::fake();

        $user = User::factory()->create(['notify_cart_low_stock' => true]);
        $variant = $this->variantWithStock(3);

        UserCartItem::factory()->create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
        ]);

        $variant->update(['stock_quantity' => 2]);

        Mail::assertNotQueued(CartItemLowStock::class);
    }

    public function test_low_stock_email_not_sent_when_stock_remains_above_threshold(): void
    {
        Mail::fake();

        $user = User::factory()->create(['notify_cart_low_stock' => true]);
        $variant = $this->variantWithStock(20);

        UserCartItem::factory()->create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
        ]);

        $variant->update(['stock_quantity' => 8]);

        Mail::assertNotQueued(CartItemLowStock::class);
    }

    public function test_low_stock_email_not_sent_when_user_has_opted_out(): void
    {
        Mail::fake();

        $user = User::factory()->create(['notify_cart_low_stock' => false]);
        $variant = $this->variantWithStock(10);

        UserCartItem::factory()->create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
        ]);

        $variant->update(['stock_quantity' => 2]);

        Mail::assertNotQueued(CartItemLowStock::class);
    }

    public function test_low_stock_email_not_sent_when_inventory_not_tracked(): void
    {
        Mail::fake();

        $user = User::factory()->create(['notify_cart_low_stock' => true]);
        $variant = $this->variantWithStock(20, trackInventory: false);

        UserCartItem::factory()->create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
        ]);

        $variant->update(['stock_quantity' => 2]);

        Mail::assertNotQueued(CartItemLowStock::class);
    }

    public function test_only_users_with_item_in_cart_are_notified(): void
    {
        Mail::fake();

        $userWithItem = User::factory()->create(['notify_cart_discounts' => true]);
        $userWithoutItem = User::factory()->create(['notify_cart_discounts' => true]);
        $variant = $this->variantWithStock(20);

        UserCartItem::factory()->create([
            'user_id' => $userWithItem->id,
            'product_variant_id' => $variant->id,
        ]);

        $variant->update(['price' => 20.00]);

        Mail::assertQueued(CartItemDiscounted::class, 1);
        Mail::assertQueued(CartItemDiscounted::class, function (CartItemDiscounted $mail) use ($userWithItem) {
            return $mail->user->id === $userWithItem->id;
        });
    }
}
