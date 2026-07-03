<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function customer(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'password' => 'old-password-123',
        ], $attributes));
    }

    public function test_guest_cannot_update_profile_or_password(): void
    {
        $this->patch(route('account.profile.update'), ['name' => 'X'])->assertRedirect(route('login'));
        $this->put(route('account.password.update'), [])->assertRedirect(route('login'));
    }

    public function test_user_can_update_their_name(): void
    {
        $user = $this->customer(['name' => 'Old Name', 'email' => 'user@example.com']);

        $this->actingAs($user)
            ->patch(route('account.profile.update'), ['name' => 'New Name'])
            ->assertRedirect(route('account.index'))
            ->assertSessionHas('status', 'Profile updated.');

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        // Email is intentionally immutable — orders are keyed by it.
        $this->assertSame('user@example.com', $user->email);
    }

    public function test_profile_name_is_required(): void
    {
        $user = $this->customer(['name' => 'Keep Me']);

        $this->actingAs($user)
            ->patch(route('account.profile.update'), ['name' => ''])
            ->assertSessionHasErrorsIn('updateProfile', ['name']);

        $this->assertSame('Keep Me', $user->fresh()->name);
    }

    public function test_user_can_change_password_with_correct_current_password(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'old-password-123',
                'password' => 'brand-new-password-456',
                'password_confirmation' => 'brand-new-password-456',
            ])
            ->assertRedirect(route('account.index'))
            ->assertSessionHas('status', 'Password updated.');

        $this->assertTrue(Hash::check('brand-new-password-456', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'not-my-password',
                'password' => 'brand-new-password-456',
                'password_confirmation' => 'brand-new-password-456',
            ])
            ->assertSessionHasErrorsIn('updatePassword', ['current_password']);

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_password_change_requires_confirmation_to_match(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'old-password-123',
                'password' => 'brand-new-password-456',
                'password_confirmation' => 'different-password',
            ])
            ->assertSessionHasErrorsIn('updatePassword', ['password']);

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_password_change_enforces_minimum_strength(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'old-password-123',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrorsIn('updatePassword', ['password']);

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_password_change_is_rate_limited(): void
    {
        $user = $this->customer();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->put(route('account.password.update'), [
                'current_password' => 'not-my-password',
                'password' => 'brand-new-password-456',
                'password_confirmation' => 'brand-new-password-456',
            ]);
        }

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'old-password-123',
                'password' => 'brand-new-password-456',
                'password_confirmation' => 'brand-new-password-456',
            ])
            ->assertStatus(429);
    }

    public function test_account_page_shows_profile_and_security_section(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->get(route('account.index'))
            ->assertOk()
            ->assertSee('Profile & Security')
            ->assertSee('Change password')
            ->assertSee('Current password')
            ->assertSee('Delete account');
    }

    public function test_user_can_delete_account_with_correct_password(): void
    {
        $user = $this->customer(['email' => 'leaving@example.com']);
        $user->sizeProfiles()->create(['name' => 'Me', 'is_self' => true, 'top_size' => 'M']);
        NewsletterSubscriber::factory()->create(['email' => 'leaving@example.com']);
        $order = Order::factory()->create(['email' => 'leaving@example.com', 'payment_status' => 'paid']);

        $response = $this->actingAs($user)
            ->delete(route('account.destroy'), ['current_password' => 'old-password-123']);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('size_profiles', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('newsletter_subscribers', ['email' => 'leaving@example.com']);
        // Orders and their invoices are retained for statutory record-keeping.
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'email' => 'leaving@example.com']);
    }

    public function test_account_deletion_rejects_wrong_password(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->delete(route('account.destroy'), ['current_password' => 'not-my-password'])
            ->assertSessionHasErrorsIn('deleteAccount', ['current_password']);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_cannot_self_delete_via_storefront(): void
    {
        $admin = $this->customer(['is_admin' => true]);

        $this->actingAs($admin)
            ->delete(route('account.destroy'), ['current_password' => 'old-password-123'])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
