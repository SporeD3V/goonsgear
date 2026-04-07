<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NewArrivalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_new_arrivals_component(): void
    {
        $response = $this->get(route('shop.index'));

        $response->assertOk();
        $response->assertSeeLivewire('new-arrivals');
    }

    public function test_component_shows_active_products(): void
    {
        $product = Product::factory()->create([
            'name' => 'Fresh Drop Tee',
            'status' => 'active',
        ]);

        Livewire::test('new-arrivals')
            ->assertSee('Fresh Drop Tee');
    }

    public function test_component_hides_inactive_products(): void
    {
        Product::factory()->create([
            'name' => 'Draft Product',
            'status' => 'draft',
        ]);

        Livewire::test('new-arrivals')
            ->assertDontSee('Draft Product');
    }

    public function test_component_shows_at_most_ten_products(): void
    {
        Product::factory()->count(15)->create(['status' => 'active']);

        $html = Livewire::test('new-arrivals')->html();

        $this->assertLessThanOrEqual(10, substr_count($html, 'data-catalog-card'));
    }

    public function test_component_orders_products_by_newest_first(): void
    {
        $older = Product::factory()->create(['status' => 'active', 'name' => 'Older Product']);
        $newer = Product::factory()->create(['status' => 'active', 'name' => 'Newer Product']);

        $html = Livewire::test('new-arrivals')->html();

        $this->assertLessThan(
            strpos($html, 'Older Product'),
            strpos($html, 'Newer Product'),
            'Newer product should appear before older product in the carousel'
        );
    }

    public function test_component_shows_empty_state_when_no_products(): void
    {
        Livewire::test('new-arrivals')
            ->assertSee('No products yet.');
    }

    public function test_component_links_to_product_detail_page(): void
    {
        $product = Product::factory()->create([
            'name' => 'Linked Product',
            'slug' => 'linked-product',
            'status' => 'active',
        ]);

        Livewire::test('new-arrivals')
            ->assertSee(route('shop.show', $product));
    }
}
