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

    public function test_shop_show_renders_attribute_boxes_from_option_values(): void
    {
        $product = Product::factory()->create();

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Red M',
            'sku' => 'RED-M',
            'price' => 49.99,
            'option_values' => ['size' => 'M', 'color' => 'Red'],
            'variant_type' => 'custom',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Red L',
            'sku' => 'RED-L',
            'price' => 49.99,
            'option_values' => ['size' => 'L', 'color' => 'Red'],
            'variant_type' => 'custom',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Black M',
            'sku' => 'BLK-M',
            'price' => 49.99,
            'option_values' => ['size' => 'M', 'color' => 'Black'],
            'variant_type' => 'custom',
        ]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertSeeText('Size');
        $response->assertSeeText('Color');
        $response->assertSeeText('M');
        $response->assertSeeText('L');
        $response->assertSeeText('Red');
        $response->assertSeeText('Black');
        $response->assertSee('data-variant-attribute="size"', false);
        $response->assertSee('data-variant-attribute="color"', false);
    }

    public function test_shop_show_renders_attribute_boxes_from_variant_name_fallback(): void
    {
        $product = Product::factory()->create();

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Red / M',
            'sku' => 'RED-M',
            'price' => 49.99,
            'option_values' => null,
            'variant_type' => 'custom',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Red / L',
            'sku' => 'RED-L',
            'price' => 49.99,
            'option_values' => null,
            'variant_type' => 'custom',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Black / M',
            'sku' => 'BLK-M',
            'price' => 49.99,
            'option_values' => null,
            'variant_type' => 'custom',
        ]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertSeeText('Size');
        $response->assertSeeText('Color');
        $response->assertSeeText('M');
        $response->assertSeeText('L');
        $response->assertSeeText('Red');
        $response->assertSeeText('Black');
        $response->assertSee('data-variant-attribute="size"', false);
        $response->assertSee('data-variant-attribute="color"', false);
    }

    public function test_shop_show_does_not_preselect_variant_options_or_render_variant_filter_selects(): void
    {
        $product = Product::factory()->create();

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Red M',
            'sku' => 'RED-M',
            'price' => 49.99,
            'option_values' => ['size' => 'M', 'color' => 'Red'],
            'variant_type' => 'custom',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Black L',
            'sku' => 'BLK-L',
            'price' => 49.99,
            'option_values' => ['size' => 'L', 'color' => 'Black'],
            'variant_type' => 'custom',
        ]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertDontSee('id="shop-variant-select"', false);
        $response->assertDontSee('data-media-variant-filter', false);
        $response->assertSeeText('Select variant options to view details.');
        $response->assertSee('data-cart-variant-input', false);
        $response->assertSee('data-add-to-cart-button', false);
        $response->assertSee('disabled', false);
    }

    public function test_shop_show_displays_single_price_before_selection_when_all_variant_prices_match(): void
    {
        $product = Product::factory()->create();

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Red M',
            'sku' => 'RED-M',
            'price' => 49.99,
            'option_values' => ['size' => 'M', 'color' => 'Red'],
            'variant_type' => 'custom',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Black M',
            'sku' => 'BLK-M',
            'price' => 49.99,
            'option_values' => ['size' => 'M', 'color' => 'Black'],
            'variant_type' => 'custom',
        ]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertSee('$<span data-variant-price>49.99</span>', false);
    }

    public function test_shop_show_displays_price_range_before_selection_when_variant_prices_differ(): void
    {
        $product = Product::factory()->create();

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Red M',
            'sku' => 'RED-M',
            'price' => 39.99,
            'option_values' => ['size' => 'M', 'color' => 'Red'],
            'variant_type' => 'custom',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Black M',
            'sku' => 'BLK-M',
            'price' => 59.99,
            'option_values' => ['size' => 'M', 'color' => 'Black'],
            'variant_type' => 'custom',
        ]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertSee('$<span data-variant-price>39.99 - 59.99</span>', false);
    }

    public function test_shop_show_extracts_color_from_prefixed_color_variant_names(): void
    {
        $product = Product::factory()->create([
            'name' => 'Sean P! Socks',
            'slug' => 'sean-p-socks-test',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Sean P! Socks - Black',
            'sku' => 'SEAN-BLK',
            'price' => 19.99,
            'option_values' => null,
            'variant_type' => 'color',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Sean P! Socks - White',
            'sku' => 'SEAN-WHT',
            'price' => 19.99,
            'option_values' => null,
            'variant_type' => 'color',
        ]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertSee('data-variant-attribute="color"', false);
        $response->assertSee('data-variant-attribute-value="Black"', false);
        $response->assertSee('data-variant-attribute-value="White"', false);
        $response->assertDontSee('data-variant-attribute="color_2"', false);
    }
}
