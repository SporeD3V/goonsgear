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
        $response->assertSee(route('cart.index'));
    }

    public function test_shop_show_displays_active_product_by_slug(): void
    {
        $product = Product::factory()->create([
            'name' => 'Black Hoodie',
            'slug' => 'black-hoodie',
            'status' => 'active',
            'meta_title' => 'Black Hoodie Product Page',
            'meta_description' => 'Official Black Hoodie by GoonsGear.',
        ]);

        ProductMedia::factory()->create([
            'product_id' => $product->id,
            'path' => 'products/black-hoodie/gallery/main.webp',
            'mime_type' => 'image/webp',
            'is_primary' => true,
        ]);

        $activeVariant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Medium',
            'sku' => 'GG-HOODIE-M',
            'is_active' => true,
            'price' => 59.99,
            'stock_quantity' => 12,
        ]);

        ProductMedia::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => $activeVariant->id,
            'path' => 'products/black-hoodie/gallery/medium.webp',
            'mime_type' => 'image/webp',
            'is_primary' => false,
            'position' => 1,
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
        $response->assertSee('data-media-variant-filter', false);
        $response->assertSee('data-media-variant-id="'.$activeVariant->id.'"', false);
        $response->assertSee('data-product-variant-picker', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('<title>Black Hoodie Product Page</title>', false);
        $response->assertSee('meta name="description" content="Official Black Hoodie by GoonsGear."', false);
        $response->assertSee('property="og:type" content="product"', false);
        $response->assertSee('property="og:title" content="Black Hoodie Product Page"', false);
        $response->assertSee('rel="canonical" href="'.route('shop.show', $product).'"', false);
        $response->assertSee('action="'.route('cart.items.store').'"', false);
        $response->assertSee(route('cart.index'));
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

    public function test_shop_category_route_filters_products_and_uses_category_metadata(): void
    {
        $featuredCategory = Category::factory()->create([
            'name' => 'Featured',
            'slug' => 'featured',
            'description' => 'Featured drops and limited runs.',
            'meta_title' => 'Featured Collection | GoonsGear',
            'meta_description' => 'Shop featured drops from GoonsGear.',
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

        $response = $this->get(route('shop.category', $featuredCategory));

        $response->assertOk();
        $response->assertSee('Featured Hoodie');
        $response->assertDontSee('Other Hoodie');
        $response->assertSee('<title>Featured Collection | GoonsGear</title>', false);
        $response->assertSee('Shop featured drops from GoonsGear.', false);
    }

    public function test_shop_category_route_returns_not_found_for_inactive_category(): void
    {
        $inactiveCategory = Category::factory()->create([
            'slug' => 'inactive-category',
            'is_active' => false,
        ]);

        $response = $this->get(route('shop.category', $inactiveCategory));

        $response->assertNotFound();
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

    public function test_shop_index_sorts_by_variant_price_when_requested(): void
    {
        $higherPriceProduct = Product::factory()->create([
            'name' => 'Expensive Hoodie',
            'slug' => 'expensive-hoodie',
            'status' => 'active',
        ]);

        $lowerPriceProduct = Product::factory()->create([
            'name' => 'Budget Hoodie',
            'slug' => 'budget-hoodie',
            'status' => 'active',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $higherPriceProduct->id,
            'is_active' => true,
            'price' => 99.99,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $lowerPriceProduct->id,
            'is_active' => true,
            'price' => 49.99,
        ]);

        $ascendingResponse = $this->get(route('shop.index', [
            'sort' => 'price_asc',
        ]));

        $ascendingResponse->assertOk();
        $ascendingResponse->assertSeeInOrder(['Budget Hoodie', 'Expensive Hoodie']);

        $descendingResponse = $this->get(route('shop.index', [
            'sort' => 'price_desc',
        ]));

        $descendingResponse->assertOk();
        $descendingResponse->assertSeeInOrder(['Expensive Hoodie', 'Budget Hoodie']);
    }

    public function test_shop_index_filters_by_variant_price_range(): void
    {
        $lowPriceProduct = Product::factory()->create([
            'name' => 'Low Price Tee',
            'slug' => 'low-price-tee',
            'status' => 'active',
        ]);

        $midPriceProduct = Product::factory()->create([
            'name' => 'Mid Price Hoodie',
            'slug' => 'mid-price-hoodie',
            'status' => 'active',
        ]);

        $highPriceProduct = Product::factory()->create([
            'name' => 'High Price Jacket',
            'slug' => 'high-price-jacket',
            'status' => 'active',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $lowPriceProduct->id,
            'is_active' => true,
            'price' => 25.00,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $midPriceProduct->id,
            'is_active' => true,
            'price' => 75.00,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $highPriceProduct->id,
            'is_active' => true,
            'price' => 140.00,
        ]);

        $rangeResponse = $this->get(route('shop.index', [
            'min_price' => 50,
            'max_price' => 100,
        ]));

        $rangeResponse->assertOk();
        $rangeResponse->assertSee('Mid Price Hoodie');
        $rangeResponse->assertDontSee('Low Price Tee');
        $rangeResponse->assertDontSee('High Price Jacket');

        $minOnlyResponse = $this->get(route('shop.index', [
            'min_price' => 100,
        ]));

        $minOnlyResponse->assertOk();
        $minOnlyResponse->assertSee('High Price Jacket');
        $minOnlyResponse->assertDontSee('Low Price Tee');
        $minOnlyResponse->assertDontSee('Mid Price Hoodie');
    }

    public function test_api_shop_search_returns_matching_products(): void
    {
        $category = Category::factory()->create(['name' => 'Jackets', 'slug' => 'jackets']);

        $matchingProduct = Product::factory()->create([
            'primary_category_id' => $category->id,
            'name' => 'Premium Jacket',
            'slug' => 'premium-jacket',
            'status' => 'active',
            'excerpt' => 'A high-quality jacket.',
        ]);

        $nonMatchingProduct = Product::factory()->create([
            'name' => 'Shadow Pants',
            'slug' => 'shadow-pants',
            'status' => 'active',
            'excerpt' => 'Dark pants for style.',
        ]);

        $draftProduct = Product::factory()->create([
            'name' => 'Premium Hoodie',
            'slug' => 'premium-hoodie',
            'status' => 'draft',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $matchingProduct->id,
            'is_active' => true,
            'price' => 79.99,
        ]);

        ProductMedia::factory()->create([
            'product_id' => $matchingProduct->id,
            'is_primary' => true,
            'path' => 'products/premium-jacket/main.webp',
        ]);

        $response = $this->getJson(route('api.shop.search', [
            'q' => 'premium',
        ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'results' => [
                '*' => ['id', 'name', 'slug', 'excerpt', 'category', 'price', 'image', 'url'],
            ],
        ]);

        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertEquals('Premium Jacket', $results[0]['name']);
        $this->assertEquals('Jackets', $results[0]['category']);
        $this->assertEquals(79.99, $results[0]['price']);
    }

    public function test_api_shop_search_returns_empty_when_query_too_short(): void
    {
        Product::factory()->create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'status' => 'active',
        ]);

        $response = $this->getJson(route('api.shop.search', [
            'q' => 'a',
        ]));

        $response->assertOk();
        $response->assertJson(['results' => []]);
    }

    public function test_api_shop_search_limits_results_to_eight(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Product::factory()->create([
                'name' => "Premium Product {$i}",
                'slug' => "premium-product-{$i}",
                'status' => 'active',
            ]);
        }

        $response = $this->getJson(route('api.shop.search', [
            'q' => 'premium',
        ]));

        $response->assertOk();
        $results = $response->json('results');
        $this->assertCount(8, $results);
    }

    public function test_api_shop_search_searches_by_name_and_excerpt(): void
    {
        $productByName = Product::factory()->create([
            'name' => 'Vintage Jacket',
            'slug' => 'vintage-jacket',
            'status' => 'active',
            'excerpt' => 'Regular excerpt.',
        ]);

        $productByExcerpt = Product::factory()->create([
            'name' => 'Cool Hoodie',
            'slug' => 'cool-hoodie',
            'status' => 'active',
            'excerpt' => 'Vintage-inspired design.',
        ]);

        $responseByVintage = $this->getJson(route('api.shop.search', [
            'q' => 'vintage',
        ]));

        $responseByVintage->assertOk();
        $results = $responseByVintage->json('results');
        $this->assertCount(2, $results);

        $names = array_map(fn ($r) => $r['name'], $results);
        $this->assertContains('Vintage Jacket', $names);
        $this->assertContains('Cool Hoodie', $names);
    }

    public function test_api_shop_search_does_not_return_inactive_products(): void
    {
        Product::factory()->create([
            'name' => 'Premium Active',
            'slug' => 'premium-active',
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Premium Draft',
            'slug' => 'premium-draft',
            'status' => 'draft',
        ]);

        Product::factory()->create([
            'name' => 'Premium Archived',
            'slug' => 'premium-archived',
            'status' => 'archived',
        ]);

        $response = $this->getJson(route('api.shop.search', [
            'q' => 'premium',
        ]));

        $response->assertOk();
        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertEquals('Premium Active', $results[0]['name']);
    }
}
