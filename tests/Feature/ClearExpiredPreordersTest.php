<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClearExpiredPreordersTest extends TestCase
{
    use RefreshDatabase;

    public function test_releases_variants_and_products_with_passed_dates(): void
    {
        $product = Product::factory()->create([
            'is_preorder' => true,
            'preorder_available_from' => now()->subWeek(),
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_preorder' => true,
            'preorder_available_from' => now()->subWeek(),
        ]);

        $this->artisan('app:clear-expired-preorders')->assertSuccessful();

        $this->assertFalse($variant->fresh()->is_preorder);
        $this->assertNull($variant->fresh()->preorder_available_from);
        $this->assertFalse($product->fresh()->is_preorder);
    }

    public function test_keeps_future_dated_preorders(): void
    {
        $product = Product::factory()->create([
            'is_preorder' => true,
            'preorder_available_from' => now()->addMonth(),
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_preorder' => true,
            'preorder_available_from' => now()->addMonth(),
        ]);

        $this->artisan('app:clear-expired-preorders')->assertSuccessful();

        $this->assertTrue($variant->fresh()->is_preorder);
        $this->assertTrue($product->fresh()->is_preorder);
    }

    public function test_keeps_dateless_preorders(): void
    {
        // No date = the admin's deliberate "pre-order until I say otherwise".
        $product = Product::factory()->create([
            'is_preorder' => true,
            'preorder_available_from' => null,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_preorder' => true,
            'preorder_available_from' => null,
        ]);

        $this->artisan('app:clear-expired-preorders')->assertSuccessful();

        $this->assertTrue($variant->fresh()->is_preorder);
        $this->assertTrue($product->fresh()->is_preorder);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $variant = ProductVariant::factory()->create([
            'is_preorder' => true,
            'preorder_available_from' => now()->subWeek(),
        ]);

        $this->artisan('app:clear-expired-preorders', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('[DRY RUN]');

        $this->assertTrue($variant->fresh()->is_preorder);
    }
}
