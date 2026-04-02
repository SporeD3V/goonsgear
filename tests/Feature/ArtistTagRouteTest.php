<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtistTagRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_artist_route_shows_tagged_products(): void
    {
        $artistTag = Tag::factory()->create([
            'name' => 'Snowgoons',
            'slug' => 'snowgoons',
            'type' => 'artist',
            'is_active' => true,
        ]);

        $taggedProduct = Product::factory()->create([
            'name' => 'Snowgoons Hoodie',
            'slug' => 'snowgoons-hoodie',
            'status' => 'active',
        ]);

        $untaggedProduct = Product::factory()->create([
            'name' => 'Generic Tee',
            'slug' => 'generic-tee',
            'status' => 'active',
        ]);

        $taggedProduct->tags()->sync([$artistTag->id]);

        $response = $this->get(route('shop.artist', ['tag' => 'snowgoons']));

        $response->assertOk();
        $response->assertSee('Snowgoons Hoodie');
        $response->assertDontSee('Generic Tee');
    }

    public function test_brand_route_shows_tagged_products(): void
    {
        $brandTag = Tag::factory()->create([
            'name' => 'GoonsGear',
            'slug' => 'goonsgear',
            'type' => 'brand',
            'is_active' => true,
        ]);

        $taggedProduct = Product::factory()->create([
            'name' => 'GG Snapback',
            'slug' => 'gg-snapback',
            'status' => 'active',
        ]);

        $taggedProduct->tags()->sync([$brandTag->id]);

        $response = $this->get(route('shop.brand', ['tag' => 'goonsgear']));

        $response->assertOk();
        $response->assertSee('GG Snapback');
    }

    public function test_artist_route_returns_404_for_inactive_tag(): void
    {
        Tag::factory()->create([
            'slug' => 'inactive-artist',
            'type' => 'artist',
            'is_active' => false,
        ]);

        $response = $this->get(route('shop.artist', ['tag' => 'inactive-artist']));

        $response->assertNotFound();
    }

    public function test_artist_route_returns_404_for_brand_slug(): void
    {
        Tag::factory()->create([
            'slug' => 'some-brand',
            'type' => 'brand',
            'is_active' => true,
        ]);

        $response = $this->get(route('shop.artist', ['tag' => 'some-brand']));

        $response->assertNotFound();
    }

    public function test_brand_route_returns_404_for_artist_slug(): void
    {
        Tag::factory()->create([
            'slug' => 'some-artist',
            'type' => 'artist',
            'is_active' => true,
        ]);

        $response = $this->get(route('shop.brand', ['tag' => 'some-artist']));

        $response->assertNotFound();
    }

    public function test_artist_route_returns_404_for_nonexistent_slug(): void
    {
        $response = $this->get('/artist/does-not-exist');

        $response->assertNotFound();
    }

    public function test_artist_page_has_seo_title(): void
    {
        $artistTag = Tag::factory()->create([
            'name' => 'ONYX',
            'slug' => 'onyx',
            'type' => 'artist',
            'is_active' => true,
        ]);

        $response = $this->get(route('shop.artist', ['tag' => 'onyx']));

        $response->assertOk();
        $response->assertSee('ONYX | Shop | GoonsGear');
    }

    public function test_custom_tag_route_shows_tagged_products(): void
    {
        $customTag = Tag::factory()->create([
            'name' => 'German Hip Hop',
            'slug' => 'germanhiphop',
            'type' => 'custom',
            'is_active' => true,
        ]);

        $taggedProduct = Product::factory()->create([
            'name' => 'German Rap Vinyl',
            'slug' => 'german-rap-vinyl',
            'status' => 'active',
        ]);

        $taggedProduct->tags()->sync([$customTag->id]);

        $response = $this->get(route('shop.tag', ['tag' => 'germanhiphop']));

        $response->assertOk();
        $response->assertSee('German Rap Vinyl');
    }

    public function test_custom_tag_route_returns_404_for_artist_slug(): void
    {
        Tag::factory()->create([
            'slug' => 'some-artist-custom-test',
            'type' => 'artist',
            'is_active' => true,
        ]);

        $response = $this->get(route('shop.tag', ['tag' => 'some-artist-custom-test']));

        $response->assertNotFound();
    }

    public function test_custom_tag_route_returns_404_for_inactive_custom_tag(): void
    {
        Tag::factory()->create([
            'slug' => 'inactive-custom',
            'type' => 'custom',
            'is_active' => false,
        ]);

        $response = $this->get(route('shop.tag', ['tag' => 'inactive-custom']));

        $response->assertNotFound();
    }
}
