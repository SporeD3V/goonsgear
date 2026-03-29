<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopBrowseTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_index_only_lists_active_products(): void
    {
        $activeProduct = Product::factory()->create([
            'name' => 'Active Hoodie',
            'slug' => 'active-hoodie',
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Draft Hoodie',
            'slug' => 'draft-hoodie',
            'status' => 'draft',
        ]);

        ProductMedia::factory()->create([
            'product_id' => $activeProduct->id,
            'path' => 'products/active-hoodie/gallery/main.webp',
            'mime_type' => 'image/webp',
            'is_primary' => true,
        ]);

        $response = $this->get(route('shop.index'));

        $response->assertOk();
        $response->assertSee('Active Hoodie');
        $response->assertDontSee('Draft Hoodie');
        $response->assertSee(route('shop.show', $activeProduct));
    }

    public function test_shop_show_displays_active_product_by_slug(): void
    {
        $product = Product::factory()->create([
            'name' => 'Black Hoodie',
            'slug' => 'black-hoodie',
            'status' => 'active',
        ]);

        ProductMedia::factory()->create([
            'product_id' => $product->id,
            'path' => 'products/black-hoodie/gallery/main.webp',
            'mime_type' => 'image/webp',
            'is_primary' => true,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Medium',
            'sku' => 'GG-HOODIE-M',
            'is_active' => true,
            'price' => 59.99,
            'stock_quantity' => 12,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Hidden Variant',
            'sku' => 'GG-HIDDEN',
            'is_active' => false,
            'price' => 59.99,
            'stock_quantity' => 0,
        ]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertSee('Black Hoodie');
        $response->assertSee('GG-HOODIE-M');
        $response->assertDontSee('GG-HIDDEN');
    }

    public function test_shop_show_returns_not_found_for_non_active_product(): void
    {
        $draftProduct = Product::factory()->create([
            'slug' => 'draft-product',
            'status' => 'draft',
        ]);

        $response = $this->get(route('shop.show', $draftProduct));

        $response->assertNotFound();
    }

    public function test_shop_index_filters_by_primary_category_slug(): void
    {
        $featuredCategory = Category::factory()->create([
            'name' => 'Featured',
            'slug' => 'featured',
            'is_active' => true,
        ]);

        $otherCategory = Category::factory()->create([
            'name' => 'Other',
            'slug' => 'other',
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Featured Hoodie',
            'slug' => 'featured-hoodie',
            'status' => 'active',
            'primary_category_id' => $featuredCategory->id,
        ]);

        Product::factory()->create([
            'name' => 'Other Hoodie',
            'slug' => 'other-hoodie',
            'status' => 'active',
            'primary_category_id' => $otherCategory->id,
        ]);

        $response = $this->get(route('shop.index', [
            'category' => 'featured',
        ]));

        $response->assertOk();
        $response->assertSee('Featured Hoodie');
        $response->assertDontSee('Other Hoodie');
    }

    public function test_shop_index_filters_by_keyword_search(): void
    {
        Product::factory()->create([
            'name' => 'Lightning Jacket',
            'slug' => 'lightning-jacket',
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Shadow Pants',
            'slug' => 'shadow-pants',
            'status' => 'active',
        ]);

        $response = $this->get(route('shop.index', [
            'q' => 'lightning',
        ]));

        $response->assertOk();
        $response->assertSee('Lightning Jacket');
        $response->assertDontSee('Shadow Pants');
    }

    public function test_shop_index_sorts_by_name_when_requested(): void
    {
        Product::factory()->create([
            'name' => 'Zulu Hoodie',
            'slug' => 'zulu-hoodie',
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Alpha Hoodie',
            'slug' => 'alpha-hoodie',
            'status' => 'active',
        ]);

        $response = $this->get(route('shop.index', [
            'sort' => 'name_asc',
        ]));

        $response->assertOk();
        $response->assertSeeInOrder(['Alpha Hoodie', 'Zulu Hoodie']);
    }
}
