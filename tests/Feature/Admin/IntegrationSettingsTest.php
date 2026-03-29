<?php

namespace Tests\Feature\Admin;

use App\Models\IntegrationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    public function test_admin_can_open_integration_settings_page(): void
    {
        $response = $this->get(route('admin.maintenance.integrations.edit'));

        $response->assertOk();
        $response->assertSee('Integration Settings');
        $response->assertSee('reCAPTCHA');
    }

    public function test_admin_can_update_encrypted_integration_settings(): void
    {
        $response = $this->post(route('admin.maintenance.integrations.update'), [
            'recaptcha_enabled' => '1',
            'recaptcha_provider' => 'google',
            'recaptcha_site_key' => 'site-key',
            'recaptcha_secret_key' => 'super-secret-key',
            'recaptcha_min_score' => '0.7',
            'paypal_client_id' => 'paypal-client-id',
            'paypal_client_secret' => 'paypal-client-secret',
            'paypal_base_url' => 'https://api-m.sandbox.paypal.com',
            'dhl_tracking_url' => 'https://example.test/track/%s',
            'brevo_api_key' => 'brevo-api-key',
        ]);

        $response->assertRedirect(route('admin.maintenance.integrations.edit'));
        $response->assertSessionHas('status');

        $this->assertSame('site-key', IntegrationSetting::value('recaptcha_site_key'));
        $this->assertSame('paypal-client-secret', IntegrationSetting::value('paypal_client_secret'));

        $rawSecret = (string) DB::table('integration_settings')
            ->where('name', 'paypal_client_secret')
            ->value('value');

        $this->assertNotSame('paypal-client-secret', $rawSecret);
        $this->assertNotSame('', $rawSecret);
    }
}
