<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopProductPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_show_renders_sanitized_product_html(): void
    {
        $product = Product::factory()->create([
            'excerpt' => '<strong>Heavy quality</strong> hoodie',
            'description' => '<p>Line one with <strong>bold</strong>.</p><script>alert("xss")</script><p>Line two</p>',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Default',
            'option_values' => null,
            'price' => 39.99,
        ]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertSee('<strong>Heavy quality</strong> hoodie', false);
        $response->assertSee('<p>Line one with <strong>bold</strong>.</p>', false);
        $response->assertSee('<p>Line two</p>', false);
        $response->assertDontSee('alert("xss")', false);
        $response->assertDontSee('Choose variant');
    }

    public function test_shop_index_displays_plain_excerpt_text(): void
    {
        $product = Product::factory()->create([
            'excerpt' => '<strong>Bold</strong> release from the archive',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 24.99,
        ]);

        $response = $this->get(route('shop.index'));

        $response->assertOk();
        $response->assertSeeText('Bold release from the archive');
        $response->assertSeeText('From $24.99');
    }

    public function test_shop_show_prioritizes_preorder_status_and_displays_availability_date(): void
    {
        $product = Product::factory()->create([
            'preorder_available_from' => now()->addMonth()->startOfDay(),
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Default',
            'price' => 34.99,
            'stock_quantity' => 26,
            'is_preorder' => true,
            'preorder_available_from' => '2026-05-29 00:00:00',
        ]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertSeeText('Preorder');
        $response->assertSeeText('Available on:');
        $response->assertSeeText('29. May 2026');
        $response->assertDontSeeText('Status: In stock');
    }
}
