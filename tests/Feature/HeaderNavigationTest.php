<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_shows_logo(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('goonsgear-shop-by-snowgoons-logo', false);
    }

    public function test_header_shows_active_categories_with_products(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['status' => 'active', 'published_at' => now()]);
        $category->products()->attach($product);

        $empty = Category::factory()->create(['is_active' => true, 'name' => 'EmptyCategory']);
        $inactive = Category::factory()->create(['is_active' => false, 'name' => 'InactiveCategory']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee($category->name);
        $response->assertDontSee('EmptyCategory');
        $response->assertDontSee('InactiveCategory');
    }

    public function test_header_shows_cart_icon(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('aria-label="Cart"', false);
    }

    public function test_header_shows_cart_badge_when_items_in_cart(): void
    {
        $response = $this->withSession(['cart.items' => [
            ['variant_id' => 1, 'quantity' => 2],
            ['variant_id' => 2, 'quantity' => 1],
        ]])->get('/');

        $response->assertOk();
        $response->assertSee('>3</span>', false);
    }

    public function test_header_shows_login_and_register_for_guests(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Login');
        $response->assertSee('Register');
        $response->assertDontSee('aria-label="My account"', false);
        $response->assertDontSee('aria-label="Log out"', false);
    }

    public function test_header_shows_account_and_logout_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('aria-label="My account"', false);
        $response->assertSee('aria-label="Log out"', false);
        $response->assertDontSee('>Login</a>', false);
    }

    public function test_header_shows_admin_link_for_admin_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();
        $response->assertSee('aria-label="Admin panel"', false);
    }

    public function test_header_hides_admin_link_for_regular_users(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertDontSee('aria-label="Admin panel"', false);
    }

    public function test_header_is_present_on_product_detail_page(): void
    {
        $product = Product::factory()->create(['status' => 'active', 'published_at' => now()]);

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertSee('goonsgear-shop-by-snowgoons-logo', false);
    }

    public function test_header_is_present_on_cart_page(): void
    {
        $response = $this->get(route('cart.index'));

        $response->assertOk();
        $response->assertSee('goonsgear-shop-by-snowgoons-logo', false);
    }

    public function test_header_is_present_on_login_page(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('goonsgear-shop-by-snowgoons-logo', false);
    }
}
