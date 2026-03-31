<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_page_does_not_show_next_button(): void
    {
        $category = Category::factory()->create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 25; $i++) {
            $product = Product::factory()->create([
                'name' => "Product {$i}",
                'slug' => "product-{$i}",
                'status' => 'active',
                'primary_category_id' => $category->id,
            ]);
            $product->categories()->sync([$category->id]);
        }

        $response = $this->get(route('shop.index', ['category' => 'test-category', 'page' => 3]));

        $response->assertOk();
        $response->assertDontSee('rel="next"', false);
        $response->assertSee('cursor-not-allowed', false);
    }

    public function test_middle_page_shows_next_button(): void
    {
        $category = Category::factory()->create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 25; $i++) {
            $product = Product::factory()->create([
                'name' => "Product {$i}",
                'slug' => "product-{$i}",
                'status' => 'active',
                'primary_category_id' => $category->id,
            ]);
            $product->categories()->sync([$category->id]);
        }

        $response = $this->get(route('shop.index', ['category' => 'test-category', 'page' => 1]));

        $response->assertOk();
        $response->assertSee('rel="next"', false);
    }
}
