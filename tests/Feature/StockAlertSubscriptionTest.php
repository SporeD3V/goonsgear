<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAlertSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAlertSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function outOfStockVariant(): ProductVariant
    {
        return ProductVariant::factory()->for(
            Product::factory()->create(['status' => 'active']),
        )->create([
            'is_active' => true,
            'track_inventory' => true,
            'allow_backorder' => false,
            'is_preorder' => false,
            'stock_quantity' => 0,
        ]);
    }

    public function test_guest_cannot_create_stock_alert_subscription(): void
    {
        $variant = $this->outOfStockVariant();

        $response = $this->post(route('stock-alert-subscriptions.store'), [
            'variant_id' => $variant->id,
            'subscribe_stock_alert' => '1',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_subscribe_to_out_of_stock_variant_alert(): void
    {
        $user = User::factory()->create();
        $variant = $this->outOfStockVariant();

        $response = $this->actingAs($user)->from(route('shop.show', $variant->product))->post(route('stock-alert-subscriptions.store'), [
            'variant_id' => $variant->id,
            'subscribe_stock_alert' => '1',
        ]);

        $response->assertRedirect(route('shop.show', $variant->product));
        $response->assertSessionHas('status');
        $this->assertDatabaseHas('stock_alert_subscriptions', [
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'is_active' => true,
        ]);
    }

    public function test_user_cannot_subscribe_to_in_stock_variant_alert(): void
    {
        $user = User::factory()->create();

        $variant = ProductVariant::factory()->for(
            Product::factory()->create(['status' => 'active']),
        )->create([
            'is_active' => true,
            'track_inventory' => true,
            'allow_backorder' => false,
            'is_preorder' => false,
            'stock_quantity' => 6,
        ]);

        $response = $this->actingAs($user)->from(route('shop.show', $variant->product))->post(route('stock-alert-subscriptions.store'), [
            'variant_id' => $variant->id,
            'subscribe_stock_alert' => '1',
        ]);

        $response->assertRedirect(route('shop.show', $variant->product));
        $response->assertSessionHasErrors('stock_alert');

        $this->assertDatabaseMissing('stock_alert_subscriptions', [
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
        ]);
    }

    public function test_existing_subscription_is_reactivated_when_user_subscribes_again(): void
    {
        $user = User::factory()->create();
        $variant = $this->outOfStockVariant();

        StockAlertSubscription::factory()->create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'is_active' => false,
            'notified_at' => now(),
        ]);

        $this->actingAs($user)->post(route('stock-alert-subscriptions.store'), [
            'variant_id' => $variant->id,
            'subscribe_stock_alert' => '1',
        ]);

        $this->assertDatabaseHas('stock_alert_subscriptions', [
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'is_active' => true,
            'notified_at' => null,
        ]);
    }
}
