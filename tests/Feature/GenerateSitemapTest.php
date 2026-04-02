<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateSitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $path = public_path('sitemap.xml');
        if (file_exists($path)) {
            unlink($path);
        }

        parent::tearDown();
    }

    public function test_sitemap_is_generated_with_homepage(): void
    {
        $this->artisan('app:generate-sitemap')
            ->assertSuccessful();

        $this->assertFileExists(public_path('sitemap.xml'));

        $xml = file_get_contents(public_path('sitemap.xml'));
        $this->assertStringContainsString(url('/'), $xml);
    }

    public function test_sitemap_includes_active_published_products(): void
    {
        $product = Product::factory()->create([
            'status' => 'active',
            'published_at' => now(),
        ]);

        $this->artisan('app:generate-sitemap')->assertSuccessful();

        $xml = file_get_contents(public_path('sitemap.xml'));
        $this->assertStringContainsString(route('shop.show', $product), $xml);
    }

    public function test_sitemap_excludes_inactive_and_unpublished_products(): void
    {
        $draft = Product::factory()->create([
            'status' => 'draft',
            'published_at' => now(),
        ]);

        $unpublished = Product::factory()->create([
            'status' => 'active',
            'published_at' => null,
        ]);

        $this->artisan('app:generate-sitemap')->assertSuccessful();

        $xml = file_get_contents(public_path('sitemap.xml'));
        $this->assertStringNotContainsString(route('shop.show', $draft), $xml);
        $this->assertStringNotContainsString(route('shop.show', $unpublished), $xml);
    }

    public function test_sitemap_includes_active_categories_with_products(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['status' => 'active', 'published_at' => now()]);
        $category->products()->attach($product);

        $this->artisan('app:generate-sitemap')->assertSuccessful();

        $xml = file_get_contents(public_path('sitemap.xml'));
        $this->assertStringContainsString(route('shop.category', $category), $xml);
    }

    public function test_sitemap_excludes_inactive_and_empty_categories(): void
    {
        $inactive = Category::factory()->create(['is_active' => false]);
        $empty = Category::factory()->create(['is_active' => true]);

        $this->artisan('app:generate-sitemap')->assertSuccessful();

        $xml = file_get_contents(public_path('sitemap.xml'));
        $this->assertStringNotContainsString(route('shop.category', $inactive), $xml);
        $this->assertStringNotContainsString(route('shop.category', $empty), $xml);
    }

    public function test_sitemap_includes_artist_and_brand_tags(): void
    {
        $artist = Tag::factory()->create(['type' => 'artist', 'is_active' => true]);
        $brand = Tag::factory()->create(['type' => 'brand', 'is_active' => true]);
        $genre = Tag::factory()->create(['type' => 'custom', 'is_active' => true]);

        $product = Product::factory()->create(['status' => 'active', 'published_at' => now()]);
        $artist->products()->attach($product);
        $brand->products()->attach($product);
        $genre->products()->attach($product);

        $this->artisan('app:generate-sitemap')->assertSuccessful();

        $xml = file_get_contents(public_path('sitemap.xml'));
        $this->assertStringContainsString(route('shop.artist', $artist), $xml);
        $this->assertStringContainsString(route('shop.brand', $brand), $xml);
        $this->assertStringContainsString(route('shop.tag', $genre), $xml);
    }

    public function test_sitemap_excludes_inactive_tags_and_tags_without_products(): void
    {
        $inactive = Tag::factory()->create(['type' => 'artist', 'is_active' => false]);
        $empty = Tag::factory()->create(['type' => 'artist', 'is_active' => true]);

        $this->artisan('app:generate-sitemap')->assertSuccessful();

        $xml = file_get_contents(public_path('sitemap.xml'));
        $this->assertStringNotContainsString($inactive->slug, $xml);
        $this->assertStringNotContainsString($empty->slug, $xml);
    }
}
