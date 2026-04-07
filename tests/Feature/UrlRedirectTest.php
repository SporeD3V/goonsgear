<?php

namespace Tests\Feature;

use App\Models\UrlRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UrlRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    public function test_admin_can_create_url_redirect(): void
    {
        Livewire::test('admin.url-redirect-manager')
            ->call('openCreate')
            ->set('from_path', 'old-page')
            ->set('to_url', '/shop')
            ->set('status_code', 301)
            ->set('is_active', true)
            ->call('save')
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('url_redirects', [
            'from_path' => '/old-page',
            'to_url' => '/shop',
            'status_code' => 301,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_url_redirect(): void
    {
        $redirect = UrlRedirect::factory()->create([
            'from_path' => '/legacy',
            'to_url' => '/shop',
            'status_code' => 301,
        ]);

        Livewire::test('admin.url-redirect-manager')
            ->call('openEdit', $redirect->id)
            ->set('from_path', '/legacy-product')
            ->set('status_code', 302)
            ->set('is_active', false)
            ->call('save')
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('url_redirects', [
            'id' => $redirect->id,
            'from_path' => '/legacy-product',
            'status_code' => 302,
            'is_active' => false,
        ]);
    }

    public function test_legacy_url_is_redirected_with_301_status(): void
    {
        UrlRedirect::factory()->create([
            'from_path' => '/old-page',
            'to_url' => '/shop',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/old-page');

        $response->assertMovedPermanently();
        $response->assertRedirect('/shop');
    }

    public function test_inactive_redirect_rule_is_ignored(): void
    {
        UrlRedirect::factory()->create([
            'from_path' => '/old-page',
            'to_url' => '/shop',
            'status_code' => 301,
            'is_active' => false,
        ]);

        $response = $this->get('/old-page');

        $response->assertNotFound();
    }

    public function test_redirect_does_not_apply_to_admin_routes(): void
    {
        UrlRedirect::factory()->create([
            'from_path' => '/admin/coupons',
            'to_url' => '/shop',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/admin/coupons');

        $response->assertOk();
    }
}
