<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\UserCartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function makeActiveVariant(): ProductVariant
    {
        return ProductVariant::factory()->for(
            Product::factory()->create(['status' => 'active']),
        )->create([
            'is_active' => true,
            'track_inventory' => false,
        ]);
    }

    public function test_adding_item_persists_to_db_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeActiveVariant();

        $this->actingAs($user)->post(route('cart.items.store'), [
            'variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('user_cart_items', [
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);
    }

    public function test_adding_item_does_not_persist_for_guest(): void
    {
        $variant = $this->makeActiveVariant();

        $this->post(route('cart.items.store'), [
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $this->assertDatabaseEmpty('user_cart_items');
    }

    public function test_updating_item_syncs_quantity_to_db(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeActiveVariant();

        UserCartItem::factory()->create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $this->actingAs($user)
            ->withSession(['cart.items' => [$variant->id => ['variant_id' => $variant->id, 'quantity' => 1]]])
            ->patch(route('cart.items.update', $variant), ['quantity' => 3]);

        $this->assertDatabaseHas('user_cart_items', [
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ]);
    }

    public function test_removing_item_deletes_from_db(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeActiveVariant();

        UserCartItem::factory()->create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $this->actingAs($user)
            ->withSession(['cart.items' => [$variant->id => ['variant_id' => $variant->id, 'quantity' => 2]]])
            ->delete(route('cart.items.destroy', $variant));

        $this->assertDatabaseMissing('user_cart_items', [
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
        ]);
    }

    public function test_cart_is_restored_from_db_when_session_is_empty(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeActiveVariant();

        UserCartItem::factory()->create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ]);

        $response = $this->actingAs($user)->get(route('cart.index'));

        $response->assertOk();
        $response->assertSessionHas('cart.items', function (array $items) use ($variant) {
            return isset($items[$variant->id]) && $items[$variant->id]['quantity'] === 3;
        });
    }

    public function test_cart_is_not_restored_when_session_already_has_items(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeActiveVariant();

        UserCartItem::factory()->create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5,
        ]);

        $this->actingAs($user)
            ->withSession(['cart.items' => [$variant->id => ['variant_id' => $variant->id, 'quantity' => 2]]])
            ->get(route('cart.index'));

        $this->assertEquals(2, session('cart.items')[$variant->id]['quantity'] ?? null);
    }

    public function test_email_preferences_default_to_true_for_new_users(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertTrue($user->notify_cart_discounts);
        $this->assertTrue($user->notify_cart_low_stock);
    }

    public function test_email_preferences_can_be_updated(): void
    {
        $user = User::factory()->create([
            'notify_cart_discounts' => true,
            'notify_cart_low_stock' => true,
        ]);

        $this->actingAs($user)
            ->patch(route('account.email-preferences.update'), [
                'notify_cart_discounts' => '1',
                // notify_cart_low_stock intentionally omitted (unchecked checkbox)
            ])
            ->assertRedirect(route('account.index'));

        $user->refresh();
        $this->assertTrue($user->notify_cart_discounts);
        $this->assertFalse($user->notify_cart_low_stock);
    }

    public function test_email_preferences_can_be_disabled_entirely(): void
    {
        $user = User::factory()->create([
            'notify_cart_discounts' => true,
            'notify_cart_low_stock' => true,
        ]);

        $this->actingAs($user)
            ->patch(route('account.email-preferences.update'), [])
            ->assertRedirect(route('account.index'));

        $user->refresh();
        $this->assertFalse($user->notify_cart_discounts);
        $this->assertFalse($user->notify_cart_low_stock);
    }
}
