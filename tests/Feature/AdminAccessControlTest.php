<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_for_admin_routes(): void
    {
        $response = $this->get(route('admin.products.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_user_cannot_access_admin_routes(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('admin.products.index'));

        $response->assertForbidden();
    }

    public function test_admin_user_can_access_admin_routes(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.products.index'));

        $response->assertOk();
    }

    public function test_admin_response_includes_noindex_header(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.products.index'));

        $response->assertOk();
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
