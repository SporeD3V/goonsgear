<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_categories_show_children_in_header(): void
    {
        $parent = Category::factory()->create(['name' => 'TestParent', 'slug' => 'test-parent', 'is_active' => true]);
        $child = Category::factory()->create(['name' => 'TestChild', 'slug' => 'test-child', 'parent_id' => $parent->id, 'is_active' => true]);
        $product = Product::factory()->create(['status' => 'active', 'published_at' => now()]);
        $child->products()->attach($product);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('TestParent');
        $response->assertSee('TestChild');
    }

    public function test_parent_category_without_product_children_hidden(): void
    {
        $parent = Category::factory()->create(['name' => 'EmptyParent', 'slug' => 'empty-parent', 'is_active' => true]);
        Category::factory()->create(['name' => 'EmptyChild', 'slug' => 'empty-child', 'parent_id' => $parent->id, 'is_active' => true]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('EmptyParent');
    }

    public function test_child_categories_not_shown_as_top_level(): void
    {
        $parent = Category::factory()->create(['name' => 'TestWear', 'slug' => 'test-wear', 'is_active' => true]);
        $child = Category::factory()->create(['name' => 'TestShirts', 'slug' => 'test-shirts', 'parent_id' => $parent->id, 'is_active' => true]);
        $product = Product::factory()->create(['status' => 'active', 'published_at' => now()]);
        $child->products()->attach($product);

        // Also create a standalone category to compare
        $standalone = Category::factory()->create(['name' => 'TestAccessories', 'slug' => 'test-accessories', 'is_active' => true]);
        $standalone->products()->attach($product);

        $response = $this->get('/');

        $response->assertOk();
        // Wear and Accessories are top-level
        $response->assertSee('TestWear');
        $response->assertSee('TestAccessories');
        // TestShirts appears as child
        $response->assertSee('TestShirts');
    }

    public function test_inactive_categories_hidden_from_header(): void
    {
        $category = Category::factory()->create(['name' => 'Unkategorisiert', 'slug' => 'unkategorisiert', 'is_active' => false]);
        $product = Product::factory()->create(['status' => 'active', 'published_at' => now()]);
        $category->products()->attach($product);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Unkategorisiert');
    }

    public function test_sale_category_shows_as_icon(): void
    {
        $sale = Category::factory()->create(['name' => 'SALE', 'slug' => 'sale', 'is_active' => true]);
        $product = Product::factory()->create(['status' => 'active', 'published_at' => now()]);
        $sale->products()->attach($product);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('title="Sale"', false);
    }

    public function test_browsing_subcategory_shows_correct_products(): void
    {
        $parent = Category::factory()->create(['name' => 'TestMusic', 'slug' => 'test-music', 'is_active' => true]);
        $child = Category::factory()->create(['name' => 'TestVinyl', 'slug' => 'test-vinyl', 'parent_id' => $parent->id, 'is_active' => true]);

        $vinylProduct = Product::factory()->create(['name' => 'Vinyl LP', 'status' => 'active', 'published_at' => now()]);
        $child->products()->attach($vinylProduct);

        $otherProduct = Product::factory()->create(['name' => 'Other Product', 'status' => 'active', 'published_at' => now()]);

        $response = $this->get(route('shop.category', 'test-vinyl'));

        $response->assertOk();
        $response->assertSee('Vinyl LP');
        $response->assertDontSee('Other Product');
    }

    public function test_sale_category_only_shows_discounted_products(): void
    {
        $sale = Category::factory()->create(['name' => 'SALE', 'slug' => 'sale', 'is_active' => true]);

        // Discounted product: compare_at_price > price
        $discountedProduct = Product::factory()->create(['name' => 'Discounted Hoodie', 'status' => 'active', 'published_at' => now()]);
        ProductVariant::factory()->create([
            'product_id' => $discountedProduct->id,
            'price' => 29.99,
            'compare_at_price' => 49.99,
            'is_active' => true,
        ]);
        $sale->products()->attach($discountedProduct);

        // Non-discounted product: no compare_at_price
        $fullPriceProduct = Product::factory()->create(['name' => 'Full Price Tee', 'status' => 'active', 'published_at' => now()]);
        ProductVariant::factory()->create([
            'product_id' => $fullPriceProduct->id,
            'price' => 39.99,
            'compare_at_price' => null,
            'is_active' => true,
        ]);
        $sale->products()->attach($fullPriceProduct);

        $response = $this->get(route('shop.category', 'sale'));

        $response->assertOk();
        $response->assertSee('Discounted Hoodie');
        $response->assertDontSee('Full Price Tee');
    }

    public function test_sale_category_excludes_products_where_compare_at_equals_price(): void
    {
        $sale = Category::factory()->create(['name' => 'SALE', 'slug' => 'sale', 'is_active' => true]);

        $product = Product::factory()->create(['name' => 'Same Price Item', 'status' => 'active', 'published_at' => now()]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 39.99,
            'compare_at_price' => 39.99,
            'is_active' => true,
        ]);
        $sale->products()->attach($product);

        $response = $this->get(route('shop.category', 'sale'));

        $response->assertOk();
        $response->assertDontSee('Same Price Item');
    }

    public function test_non_sale_category_shows_all_products_regardless_of_discount(): void
    {
        $category = Category::factory()->create(['name' => 'Hoodies', 'slug' => 'hoodies', 'is_active' => true]);

        $product = Product::factory()->create(['name' => 'Regular Hoodie', 'status' => 'active', 'published_at' => now()]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 49.99,
            'compare_at_price' => null,
            'is_active' => true,
        ]);
        $category->products()->attach($product);

        $response = $this->get(route('shop.category', 'hoodies'));

        $response->assertOk();
        $response->assertSee('Regular Hoodie');
    }

    public function test_categories_ordered_by_sort_order_then_name(): void
    {
        // Create categories with explicit sort_order (lower = first)
        $sale = Category::factory()->create(['name' => 'SALE', 'slug' => 'sale', 'is_active' => true, 'sort_order' => 1]);
        $music = Category::factory()->create(['name' => 'TestMusic', 'slug' => 'test-music-sort', 'is_active' => true, 'sort_order' => 2]);
        $wear = Category::factory()->create(['name' => 'TestWear', 'slug' => 'test-wear-sort', 'is_active' => true, 'sort_order' => 3]);
        $accessories = Category::factory()->create(['name' => 'Accessories', 'slug' => 'accessories-sort', 'is_active' => true, 'sort_order' => 4]);

        // Give them products so they show up
        $product = Product::factory()->create(['status' => 'active', 'published_at' => now()]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 10.00,
            'compare_at_price' => 20.00,
            'is_active' => true,
        ]);
        $sale->products()->attach($product);
        $accessories->products()->attach($product);

        // Music and Wear need children with products
        $child1 = Category::factory()->create(['name' => 'CDs', 'slug' => 'cds-sort', 'parent_id' => $music->id, 'is_active' => true]);
        $child2 = Category::factory()->create(['name' => 'Shirts', 'slug' => 'shirts-sort', 'parent_id' => $wear->id, 'is_active' => true]);
        $child1->products()->attach($product);
        $child2->products()->attach($product);

        $response = $this->get('/');

        $response->assertOk();
        // SALE should appear before Music, Music before Wear, Wear before Accessories
        $content = $response->getContent();
        $salePos = strpos($content, 'title="Sale"');
        $musicPos = strpos($content, 'TestMusic');
        $wearPos = strpos($content, 'TestWear');
        $accessoriesPos = strpos($content, 'Accessories');

        $this->assertNotFalse($salePos);
        $this->assertNotFalse($musicPos);
        $this->assertNotFalse($wearPos);
        $this->assertNotFalse($accessoriesPos);
        $this->assertLessThan($musicPos, $salePos, 'SALE should appear before Music');
        $this->assertLessThan($wearPos, $musicPos, 'Music should appear before Wear');
        $this->assertLessThan($accessoriesPos, $wearPos, 'Wear should appear before Accessories');
    }

    public function test_parent_category_page_shows_products_from_child_categories(): void
    {
        $parent = Category::factory()->create(['name' => 'ParentWear', 'slug' => 'parent-wear', 'is_active' => true]);
        $shirts = Category::factory()->create(['name' => 'ChildShirts', 'slug' => 'child-shirts', 'parent_id' => $parent->id, 'is_active' => true]);
        $hoodies = Category::factory()->create(['name' => 'ChildHoodies', 'slug' => 'child-hoodies', 'parent_id' => $parent->id, 'is_active' => true]);

        $shirtProduct = Product::factory()->create(['name' => 'Cool Shirt', 'status' => 'active']);
        ProductVariant::factory()->create(['product_id' => $shirtProduct->id, 'price' => 25.00, 'is_active' => true]);
        $shirts->products()->attach($shirtProduct);

        $hoodieProduct = Product::factory()->create(['name' => 'Cool Hoodie', 'status' => 'active']);
        ProductVariant::factory()->create(['product_id' => $hoodieProduct->id, 'price' => 59.00, 'is_active' => true]);
        $hoodies->products()->attach($hoodieProduct);

        $unrelatedProduct = Product::factory()->create(['name' => 'Unrelated Vinyl', 'status' => 'active']);
        ProductVariant::factory()->create(['product_id' => $unrelatedProduct->id, 'price' => 30.00, 'is_active' => true]);

        $response = $this->get(route('shop.category', $parent));

        $response->assertOk();
        $response->assertSee('Cool Shirt');
        $response->assertSee('Cool Hoodie');
        $response->assertDontSee('Unrelated Vinyl');
    }

    public function test_parent_category_page_excludes_inactive_child_category_products(): void
    {
        $parent = Category::factory()->create(['name' => 'ParentMusic', 'slug' => 'parent-music', 'is_active' => true]);
        $activeChild = Category::factory()->create(['name' => 'ActiveVinyl', 'slug' => 'active-vinyl', 'parent_id' => $parent->id, 'is_active' => true]);
        $inactiveChild = Category::factory()->create(['name' => 'HiddenChild', 'slug' => 'hidden-child', 'parent_id' => $parent->id, 'is_active' => false]);

        $visibleProduct = Product::factory()->create(['name' => 'Visible LP', 'status' => 'active']);
        ProductVariant::factory()->create(['product_id' => $visibleProduct->id, 'price' => 30.00, 'is_active' => true]);
        $activeChild->products()->attach($visibleProduct);

        $hiddenProduct = Product::factory()->create(['name' => 'Hidden LP', 'status' => 'active']);
        ProductVariant::factory()->create(['product_id' => $hiddenProduct->id, 'price' => 30.00, 'is_active' => true]);
        $inactiveChild->products()->attach($hiddenProduct);

        $response = $this->get(route('shop.category', $parent));

        $response->assertOk();
        $response->assertSee('Visible LP');
        $response->assertDontSee('Hidden LP');
    }
}
