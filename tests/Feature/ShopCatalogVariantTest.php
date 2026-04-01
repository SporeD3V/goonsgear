<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCatalogVariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_card_renders_variant_attribute_buttons_for_multi_variant_product(): void
    {
        $product = Product::factory()->create([
            'name' => 'Test Shirt',
            'slug' => 'test-shirt',
            'status' => 'active',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Test Shirt - M, Black',
            'sku' => 'TS-M-BLK',
            'variant_type' => 'custom',
            'option_values' => null,
            'price' => 29.99,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Test Shirt - L, Red',
            'sku' => 'TS-L-RED',
            'variant_type' => 'custom',
            'option_values' => null,
            'price' => 29.99,
            'stock_quantity' => 3,
            'is_active' => true,
        ]);

        $response = $this->get(route('shop.index'));

        $response->assertOk();
        $response->assertSee('data-catalog-attribute="size"', false);
        $response->assertSee('data-catalog-attribute-value="M"', false);
        $response->assertSee('data-catalog-attribute-value="L"', false);
        $response->assertSee('data-catalog-attribute="color"', false);
        $response->assertSee('data-catalog-attribute-value="Black"', false);
        $response->assertSee('data-catalog-attribute-value="Red"', false);
        $response->assertSee('data-catalog-variant-select', false);
        $response->assertSee('data-catalog-add-to-cart', false);
    }

    public function test_catalog_card_hides_out_of_stock_variant_options(): void
    {
        $product = Product::factory()->create([
            'name' => 'Stock Shirt',
            'slug' => 'stock-shirt',
            'status' => 'active',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Stock Shirt - M, Black',
            'sku' => 'SS-M-BLK',
            'variant_type' => 'custom',
            'option_values' => null,
            'price' => 19.99,
            'stock_quantity' => 5,
            'track_inventory' => true,
            'is_active' => true,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Stock Shirt - L, Black',
            'sku' => 'SS-L-BLK',
            'variant_type' => 'custom',
            'option_values' => null,
            'price' => 19.99,
            'stock_quantity' => 0,
            'track_inventory' => true,
            'allow_backorder' => false,
            'is_preorder' => false,
            'is_active' => true,
        ]);

        $response = $this->get(route('shop.index'));

        $response->assertOk();
        // M is in stock and should be shown
        $response->assertSee('data-catalog-attribute-value="M"', false);
        // L is out of stock (tracked, 0 qty, no backorder/preorder) and should be hidden
        $response->assertDontSee('data-catalog-attribute-value="L"', false);
    }

    public function test_catalog_card_renders_add_to_cart_for_single_variant_product(): void
    {
        $product = Product::factory()->create([
            'name' => 'Simple Shirt',
            'slug' => 'simple-shirt',
            'status' => 'active',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Default',
            'sku' => 'SIMPLE-1',
            'variant_type' => 'custom',
            'option_values' => null,
            'price' => 14.99,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $response = $this->get(route('shop.index'));

        $response->assertOk();
        $response->assertSee('Simple Shirt');
        // No variant selector for single-variant products
        $response->assertDontSee('data-catalog-variant-select', false);
        // But should have a direct add-to-cart button
        $response->assertSee('data-catalog-single-variant', false);
        $response->assertSee('Add to cart', false);
    }

    public function test_catalog_card_contains_variant_attributes_json_in_hidden_select(): void
    {
        $product = Product::factory()->create([
            'name' => 'JSON Shirt',
            'slug' => 'json-shirt',
            'status' => 'active',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'JSON Shirt - S, Blue',
            'sku' => 'JS-S-BLU',
            'variant_type' => 'custom',
            'option_values' => null,
            'price' => 24.99,
            'stock_quantity' => 2,
            'is_active' => true,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'JSON Shirt - M, Red',
            'sku' => 'JS-M-RED',
            'variant_type' => 'custom',
            'option_values' => null,
            'price' => 24.99,
            'stock_quantity' => 4,
            'is_active' => true,
        ]);

        $response = $this->get(route('shop.index'));

        $response->assertOk();
        $response->assertSee('{"size":"S","color":"Blue"}', false);
        $response->assertSee('{"size":"M","color":"Red"}', false);
    }
}
