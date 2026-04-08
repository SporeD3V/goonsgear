<?php

namespace Tests\Feature;

use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShopByArtistTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_shop_by_artist_component(): void
    {
        $response = $this->get(route('shop.index'));

        $response->assertOk();
        $response->assertSeeLivewire('shop-by-artist');
    }

    public function test_carousel_shows_only_artist_tags_with_logo_and_show_on_homepage(): void
    {
        Tag::factory()->create([
            'name' => 'Featured Artist',
            'slug' => 'featured-artist',
            'type' => 'artist',
            'is_active' => true,
            'logo_path' => 'tags/featured-artist/logo/featured-artist-logo.avif',
            'show_on_homepage' => true,
        ]);

        Tag::factory()->create([
            'name' => 'No Logo Artist',
            'slug' => 'no-logo-artist',
            'type' => 'artist',
            'is_active' => true,
            'logo_path' => null,
            'show_on_homepage' => false,
        ]);

        Tag::factory()->create([
            'name' => 'Featured Brand',
            'slug' => 'featured-brand',
            'type' => 'brand',
            'is_active' => true,
            'logo_path' => 'tags/featured-brand/logo/featured-brand-logo.avif',
            'show_on_homepage' => true,
        ]);

        Livewire::test('shop-by-artist')
            ->assertSee('Featured Artist')
            ->assertDontSee('No Logo Artist')
            ->assertDontSee('Featured Brand');
    }

    public function test_mode_toggle_switches_between_artist_and_category(): void
    {
        Tag::factory()->create([
            'name' => 'Top Artist',
            'slug' => 'top-artist',
            'type' => 'artist',
            'is_active' => true,
            'logo_path' => 'tags/top-artist/logo/top-artist-logo.avif',
            'show_on_homepage' => true,
        ]);

        Livewire::test('shop-by-artist')
            ->assertSee('Top Artist')
            ->set('mode', 'category')
            ->assertSee('By Category');
    }

    public function test_live_search_returns_matching_tags_of_selected_type(): void
    {
        Tag::factory()->create([
            'name' => 'SnowGoons',
            'slug' => 'snowgoons',
            'type' => 'artist',
            'is_active' => true,
        ]);

        Tag::factory()->create([
            'name' => 'SnowBrand',
            'slug' => 'snowbrand',
            'type' => 'brand',
            'is_active' => true,
        ]);

        Tag::factory()->create([
            'name' => 'Other Artist',
            'slug' => 'other-artist',
            'type' => 'artist',
            'is_active' => true,
        ]);

        Livewire::test('shop-by-artist')
            ->set('search', 'Snow')
            ->assertSee('SnowGoons')
            ->assertDontSee('SnowBrand')
            ->assertDontSee('Other Artist');
    }

    public function test_live_search_resets_when_mode_changes(): void
    {
        Livewire::test('shop-by-artist')
            ->set('search', 'something')
            ->set('mode', 'category')
            ->assertSet('search', '');
    }

    public function test_show_on_homepage_without_logo_is_ignored(): void
    {
        Tag::factory()->create([
            'name' => 'Hidden Tag',
            'slug' => 'hidden-tag',
            'type' => 'artist',
            'is_active' => true,
            'logo_path' => null,
            'show_on_homepage' => true,
        ]);

        Livewire::test('shop-by-artist')
            ->assertDontSee('Hidden Tag');
    }
}
