<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAlertSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductStockAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_stock_alerts_count_on_products_list(): void
    {
        $this->actingAsAdmin();

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $user = User::factory()->create();

        StockAlertSubscription::factory()->create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'is_active' => true,
        ]);

        $response = $this->get(route('admin.products.index'));

        $response->assertOk();
        $response->assertSeeText('1');
    }

    public function test_admin_can_view_stock_alerts_for_product(): void
    {
        $this->actingAsAdmin();

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $user = User::factory()
            ->create(['name' => 'John Doe', 'email' => 'john@example.com']);

        StockAlertSubscription::factory()->create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'is_active' => true,
        ]);

        $response = $this->get(route('admin.products.stock-alerts', $product));

        $response->assertOk();
        $response->assertSeeText('Customers Waiting for');
        $response->assertSeeText('John Doe');
        $response->assertSeeText('john@example.com');
    }

    public function test_only_active_subscriptions_are_shown(): void
    {
        $this->actingAsAdmin();

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $activeUser = User::factory()->create(['name' => 'Active User']);
        $notifiedUser = User::factory()->create(['name' => 'Notified User']);

        StockAlertSubscription::factory()->create([
            'user_id' => $activeUser->id,
            'product_variant_id' => $variant->id,
            'is_active' => true,
        ]);

        StockAlertSubscription::factory()->create([
            'user_id' => $notifiedUser->id,
            'product_variant_id' => $variant->id,
            'is_active' => false,
            'notified_at' => now(),
        ]);

        $response = $this->get(route('admin.products.stock-alerts', $product));

        $response->assertSeeText('Active User');
        $response->assertDontSeeText('Notified User');
    }

    public function test_non_admin_cannot_access_stock_alerts(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user);
        $response = $this->get(route('admin.products.stock-alerts', $product));

        $response->assertForbidden();
    }
}
