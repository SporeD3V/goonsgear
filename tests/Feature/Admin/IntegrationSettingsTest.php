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

    public function test_dhl_tracking_url_must_be_a_valid_http_url(): void
    {
        $response = $this->post(route('admin.maintenance.integrations.update'), [
            'dhl_tracking_url' => 'javascript:alert(1)',
        ]);

        $response->assertSessionHasErrors('dhl_tracking_url');
    }

    public function test_dhl_tracking_url_accepts_valid_https_url(): void
    {
        $response = $this->post(route('admin.maintenance.integrations.update'), [
            'dhl_tracking_url' => 'https://www.dhl.com/track?id=%s',
        ]);

        $response->assertRedirect(route('admin.maintenance.integrations.edit'));
        $response->assertSessionMissing('errors');
    }

    public function test_dhl_tracking_url_accepts_the_default_url_with_a_trailing_query_parameter(): void
    {
        // The shipped default puts %s mid-query, which the built-in url rule
        // rejected as malformed percent-encoding. That blocked every save of
        // the whole integrations form, not just this field.
        $response = $this->post(route('admin.maintenance.integrations.update'), [
            'dhl_tracking_url' => 'https://www.dhl.com/global-en/home/tracking.html?tracking-id=%s&submit=1',
        ]);

        $response->assertRedirect(route('admin.maintenance.integrations.edit'));
        $response->assertSessionMissing('errors');

        $this->assertSame(
            'https://www.dhl.com/global-en/home/tracking.html?tracking-id=%s&submit=1',
            IntegrationSetting::value('dhl_tracking_url'),
        );
    }

    public function test_paypal_credentials_save_even_when_the_dhl_url_is_present(): void
    {
        // Regression: a rejected dhl_tracking_url discarded the entire form,
        // so PayPal keys silently never persisted.
        $response = $this->post(route('admin.maintenance.integrations.update'), [
            'dhl_tracking_url' => 'https://www.dhl.com/global-en/home/tracking.html?tracking-id=%s&submit=1',
            'paypal_client_id' => 'sandbox-client-id',
            'paypal_client_secret' => 'sandbox-client-secret',
            'paypal_base_url' => 'https://api-m.sandbox.paypal.com',
        ]);

        $response->assertSessionMissing('errors');
        $this->assertSame('sandbox-client-id', IntegrationSetting::value('paypal_client_id'));
        $this->assertSame('sandbox-client-secret', IntegrationSetting::value('paypal_client_secret'));
    }
}
