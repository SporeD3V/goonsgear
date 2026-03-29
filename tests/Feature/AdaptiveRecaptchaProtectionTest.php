<?php

namespace Tests\Feature;

use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdaptiveRecaptchaProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_recaptcha_after_failed_attempts(): void
    {
        IntegrationSetting::putMany([
            'recaptcha_enabled' => '1',
            'recaptcha_trigger_after_attempts' => '1',
        ]);

        User::factory()->create([
            'email' => 'member@example.com',
            'password' => Hash::make('password1234'),
        ]);

        $firstAttempt = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'member@example.com',
            'password' => 'wrong-password',
        ]);

        $firstAttempt->assertRedirect(route('login'));
        $firstAttempt->assertSessionHasErrors('email');

        $secondAttempt = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'member@example.com',
            'password' => 'password1234',
        ]);

        $secondAttempt->assertRedirect(route('login'));
        $secondAttempt->assertSessionHasErrors('recaptcha_token');
        $this->assertGuest();
    }

    public function test_register_requires_recaptcha_when_threshold_is_zero(): void
    {
        IntegrationSetting::putMany([
            'recaptcha_enabled' => '1',
            'recaptcha_trigger_after_attempts' => '0',
        ]);

        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Jane Shopper',
            'email' => 'jane@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('recaptcha_token');
        $this->assertGuest();
    }
}
