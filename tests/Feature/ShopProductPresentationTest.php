<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

        $response = $this->get(route('shop.index'))->assertOk();

        Livewire::test('shop-catalog')
            ->assertSeeText('Bold release from the archive')
            ->assertSee('&euro;24.99', false);
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
        $response->assertSee('data-variant-sku="RED-M"', false);
        $response->assertSee('data-variant-sku="BLK-L"', false);
        $response->assertSee('data-variant-track-inventory="1"', false);
        $response->assertSee('data-variant-allow-backorder="0"', false);
        $response->assertSee('data-variant-is-preorder="0"', false);
        $response->assertSee('data-cart-quantity-input', false);
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
        $response->assertSee('&euro;<span data-display-price>49.99</span>', false);
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
        $response->assertSee('&euro;<span data-display-price>39.99 - 59.99</span>', false);
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

    public function test_shop_show_uses_single_color_group_for_typed_color_variants_when_product_name_contains_color_words(): void
    {
        $product = Product::factory()->create([
            'name' => 'Onyx All White Madface Shirt',
            'slug' => 'onyx-all-white-madface-shirt-test',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Onyx All White Madface Shirt - Black',
            'sku' => 'ONYX-BLK',
            'price' => 44.99,
            'option_values' => null,
            'variant_type' => 'color',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Onyx All White Madface Shirt - White',
            'sku' => 'ONYX-WHT',
            'price' => 44.99,
            'option_values' => null,
            'variant_type' => 'color',
        ]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertSeeText('Color');
        $response->assertDontSeeText('Color 2');
        $response->assertSee('data-variant-attribute="color"', false);
        $response->assertDontSee('data-variant-attribute="color_2"', false);
        $response->assertSee('data-variant-attribute-value="Black"', false);
        $response->assertSee('data-variant-attribute-value="White"', false);
    }

    public function test_shop_show_supports_typed_size_variants(): void
    {
        $product = Product::factory()->create();

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'S',
            'sku' => 'SIZE-S',
            'price' => 29.99,
            'option_values' => null,
            'variant_type' => 'size',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'M',
            'sku' => 'SIZE-M',
            'price' => 29.99,
            'option_values' => null,
            'variant_type' => 'size',
        ]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertSee('data-variant-attribute="size"', false);
        $response->assertDontSee('data-variant-attribute="color"', false);
    }

    public function test_shop_show_supports_typed_color_variants(): void
    {
        $product = Product::factory()->create([
            'name' => 'Color Hoodie',
            'slug' => 'color-hoodie-test',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Color Hoodie - Black',
            'sku' => 'COLOR-BLK',
            'price' => 39.99,
            'option_values' => null,
            'variant_type' => 'color',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Color Hoodie - White',
            'sku' => 'COLOR-WHT',
            'price' => 39.99,
            'option_values' => null,
            'variant_type' => 'color',
        ]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertSee('data-variant-attribute="color"', false);
        $response->assertDontSee('data-variant-attribute="size"', false);
    }

    public function test_shop_show_supports_combo_variants_with_size_and_color(): void
    {
        $product = Product::factory()->create();

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Black / M',
            'sku' => 'COMBO-BLK-M',
            'price' => 49.99,
            'option_values' => ['size' => 'M', 'color' => 'Black'],
            'variant_type' => 'custom',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Red / L',
            'sku' => 'COMBO-RED-L',
            'price' => 49.99,
            'option_values' => ['size' => 'L', 'color' => 'Red'],
            'variant_type' => 'custom',
        ]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertSee('data-variant-attribute="size"', false);
        $response->assertSee('data-variant-attribute="color"', false);
        $response->assertSee('data-variant-attribute-value="M"', false);
        $response->assertSee('data-variant-attribute-value="Black"', false);
    }

    public function test_shop_show_extracts_size_and_color_from_custom_variants_when_product_name_contains_color_words(): void
    {
        // Real-world case: "Onyx - All White MadFace Shirt" has custom-type variants with null option_values.
        // Variant names follow WooCommerce format: "ProductName - Size, Color".
        // Before the fix, splitting "Onyx - All White MadFace Shirt - M, Black" on delimiters produced
        // parts ["Onyx", "All White MadFace Shirt", "M", "Black"]. "All White MadFace Shirt" was
        // classified as the color value (contains "White"), with "Black"/"Red" ending up as color_2
        // and being discarded in variantAttributesById. Result: selector buttons for "Black"/"Red"
        // never matched any variant.
        $product = Product::factory()->create([
            'name' => 'Onyx - All White MadFace Shirt',
            'slug' => 'onyx-all-white-madface-shirt-custom-test',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Onyx - All White MadFace Shirt - M, Black',
            'sku' => 'ONYX-AW-M-BLK',
            'price' => 44.99,
            'option_values' => null,
            'variant_type' => 'custom',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Onyx - All White MadFace Shirt - M, Red',
            'sku' => 'ONYX-AW-M-RED',
            'price' => 44.99,
            'option_values' => null,
            'variant_type' => 'custom',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Onyx - All White MadFace Shirt - L, Black',
            'sku' => 'ONYX-AW-L-BLK',
            'price' => 44.99,
            'option_values' => null,
            'variant_type' => 'custom',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Onyx - All White MadFace Shirt - L, Red',
            'sku' => 'ONYX-AW-L-RED',
            'price' => 44.99,
            'option_values' => null,
            'variant_type' => 'custom',
        ]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();

        // Size and color groups should be present with real values only.
        $response->assertSee('data-variant-attribute="size"', false);
        $response->assertSee('data-variant-attribute="color"', false);
        $response->assertSee('data-variant-attribute-value="M"', false);
        $response->assertSee('data-variant-attribute-value="L"', false);
        $response->assertSee('data-variant-attribute-value="Black"', false);
        $response->assertSee('data-variant-attribute-value="Red"', false);

        // The product name fragment must not appear as a selector value.
        $response->assertDontSee('data-variant-attribute-value="All White MadFace Shirt"', false);
        $response->assertDontSee('data-variant-attribute-value="Onyx - All White MadFace Shirt"', false);
        $response->assertDontSee('data-variant-attribute-value="Onyx"', false);

        // variantAttributesById JSON must bind each variant to its real size+color combo so JS
        // can resolve the correct variant when the user picks "M" + "Black" etc.
        $response->assertSee('"color":"Black"', false);
        $response->assertSee('"color":"Red"', false);
        $response->assertSee('"size":"M"', false);
        $response->assertSee('"size":"L"', false);
    }

    public function test_shop_show_ignores_product_name_fragments_when_variant_prefix_punctuation_differs(): void
    {
        $product = Product::factory()->create([
            'name' => 'Onyx - All White MadFace Shirt',
            'slug' => 'onyx-all-white-madface-shirt-mismatch-test',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Onyx All White MadFace Shirt - M, Black',
            'sku' => 'ONYX-PUNC-M-BLK',
            'price' => 44.99,
            'option_values' => null,
            'variant_type' => 'custom',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Onyx All White MadFace Shirt - L, Red',
            'sku' => 'ONYX-PUNC-L-RED',
            'price' => 44.99,
            'option_values' => null,
            'variant_type' => 'custom',
        ]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertSee('data-variant-attribute="size"', false);
        $response->assertSee('data-variant-attribute="color"', false);
        $response->assertSee('data-variant-attribute-value="M"', false);
        $response->assertSee('data-variant-attribute-value="L"', false);
        $response->assertSee('data-variant-attribute-value="Black"', false);
        $response->assertSee('data-variant-attribute-value="Red"', false);
        $response->assertDontSee('data-variant-attribute-value="All White MadFace Shirt"', false);
        $response->assertDontSee('data-variant-attribute-value="Onyx"', false);
    }

    public function test_shop_show_exposes_variant_specific_media_metadata_for_gallery_filtering(): void
    {
        $product = Product::factory()->create();

        $redVariant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Red M',
            'sku' => 'MEDIA-RED-M',
            'price' => 44.99,
            'option_values' => ['size' => 'M', 'color' => 'Red'],
            'variant_type' => 'custom',
        ]);

        $blackVariant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Black M',
            'sku' => 'MEDIA-BLK-M',
            'price' => 44.99,
            'option_values' => ['size' => 'M', 'color' => 'Black'],
            'variant_type' => 'custom',
        ]);

        ProductMedia::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => $redVariant->id,
            'path' => 'products/media-product/red.webp',
            'mime_type' => 'image/webp',
            'is_primary' => true,
            'position' => 0,
        ]);

        ProductMedia::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => $blackVariant->id,
            'path' => 'products/media-product/black.webp',
            'mime_type' => 'image/webp',
            'is_primary' => false,
            'position' => 1,
        ]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertSee('data-media-variant-id="'.$redVariant->id.'"', false);
        $response->assertSee('data-media-variant-id="'.$blackVariant->id.'"', false);
        $response->assertSee('data-media-variant-color="Red"', false);
        $response->assertSee('data-media-variant-color="Black"', false);
    }

    public function test_shop_show_merges_mixed_color_and_custom_variants_into_single_color_group(): void
    {
        $product = Product::factory()->create([
            'name' => 'Onyx & Snowgoons - SnowMads Vinyl',
            'slug' => 'onyx-snowgoons-snowmads-vinyl-test',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Onyx & Snowgoons - SnowMads Vinyl - Black',
            'sku' => 'SNOWMADS-BLK',
            'price' => 29.99,
            'option_values' => null,
            'variant_type' => 'color',
            'stock_quantity' => 5,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Onyx & Snowgoons - SnowMads Vinyl - Red',
            'sku' => 'SNOWMADS-RED',
            'price' => 29.99,
            'option_values' => null,
            'variant_type' => 'color',
            'stock_quantity' => 3,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Onyx & Snowgoons - SnowMads Vinyl - Splatter',
            'sku' => 'SNOWMADS-SPL',
            'price' => 29.99,
            'option_values' => null,
            'variant_type' => 'custom',
            'stock_quantity' => 2,
        ]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertSee('data-variant-attribute="color"', false);
        $response->assertSee('data-variant-attribute-value="Black"', false);
        $response->assertSee('data-variant-attribute-value="Red"', false);
        $response->assertSee('data-variant-attribute-value="Splatter"', false);
    }

    public function test_delisted_product_shows_discontinued_page_with_noindex(): void
    {
        $category = Category::factory()->create(['name' => 'Hoodies']);
        $product = Product::factory()->create([
            'name' => 'Retired Hoodie',
            'status' => 'delisted',
            'primary_category_id' => $category->id,
        ]);

        // Create a suggested product in same category
        $suggested = Product::factory()->create([
            'status' => 'active',
            'primary_category_id' => $category->id,
        ]);
        ProductVariant::factory()->create(['product_id' => $suggested->id, 'price' => 29.99]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertSee('noindex', false);
        $response->assertSeeText('This product has been discontinued');
        $response->assertSeeText('Retired Hoodie');
        $response->assertSeeText('You Might Also Like');
        $response->assertDontSee('Add to Cart', false);
    }

    public function test_draft_product_returns_404(): void
    {
        $product = Product::factory()->create(['status' => 'draft']);

        $response = $this->get(route('shop.show', $product));

        $response->assertNotFound();
    }
}
