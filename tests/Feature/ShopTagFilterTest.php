<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopTagFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Shop products can be filtered by artist/brand slug.
     */
    public function test_shop_index_filters_products_by_tag_slug(): void
    {
        $artistTag = Tag::factory()->create([
            'name' => 'Underground Kings',
            'slug' => 'underground-kings',
            'type' => 'artist',
            'is_active' => true,
        ]);

        $brandTag = Tag::factory()->create([
            'name' => 'Street Works',
            'slug' => 'street-works',
            'type' => 'brand',
            'is_active' => true,
        ]);

        $artistProduct = Product::factory()->create([
            'name' => 'Underground Vinyl Vol. 1',
            'slug' => 'underground-vinyl-vol-1',
            'status' => 'active',
        ]);

        $brandProduct = Product::factory()->create([
            'name' => 'Street Works Hoodie',
            'slug' => 'street-works-hoodie',
            'status' => 'active',
        ]);

        $artistProduct->tags()->sync([$artistTag->id]);
        $brandProduct->tags()->sync([$brandTag->id]);

        $response = $this->get(route('shop.artist', $artistTag));

        $response->assertOk();
        $response->assertSee('Underground Vinyl Vol. 1');
        $response->assertDontSee('Street Works Hoodie');
    }

    public function test_inactive_tags_return_not_found_on_dedicated_route(): void
    {
        $inactiveTag = Tag::factory()->create([
            'slug' => 'inactive-artist',
            'type' => 'artist',
            'is_active' => false,
        ]);

        $product = Product::factory()->create([
            'name' => 'Ghost Drop',
            'status' => 'active',
        ]);

        $product->tags()->sync([$inactiveTag->id]);

        $response = $this->get(route('shop.artist', $inactiveTag));

        $response->assertNotFound();
    }
}
