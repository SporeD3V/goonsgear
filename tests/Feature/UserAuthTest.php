<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_account_page(): void
    {
        $response = $this->get(route('account.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_register_and_is_authenticated(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Jane Shopper',
            'email' => 'jane@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);

        $response->assertRedirect(route('account.index'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'name' => 'Jane Shopper',
        ]);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => Hash::make('password1234'),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password1234',
        ]);

        $response->assertRedirect(route('account.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_user_is_redirected_to_admin_area_after_login(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password1234'),
            'is_admin' => true,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password1234',
        ]);

        $response->assertRedirect(route('admin.orders.index'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'member@example.com',
            'password' => Hash::make('password1234'),
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'member@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('shop.index'));
        $this->assertGuest();
    }

    public function test_authenticated_user_can_view_account_page(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Shopper',
            'email' => 'jane@example.com',
        ]);

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertOk();
        $response->assertSee('My Account');
        $response->assertSee('Jane Shopper');
        $response->assertSee('jane@example.com');
    }
}
