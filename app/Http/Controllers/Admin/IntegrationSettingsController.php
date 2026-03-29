<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateIntegrationSettingsRequest;
use App\Models\IntegrationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class IntegrationSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.maintenance.integrations', [
            'values' => [
                'brevo_api_key' => '',
                'paypal_client_id' => IntegrationSetting::value('paypal_client_id', (string) config('services.paypal.client_id', '')),
                'paypal_client_secret' => '',
                'paypal_base_url' => IntegrationSetting::value('paypal_base_url', (string) config('services.paypal.base_url', '')),
                'dhl_tracking_url' => IntegrationSetting::value('dhl_tracking_url', (string) config('services.dhl.tracking_url', '')),
                'recaptcha_enabled' => IntegrationSetting::value('recaptcha_enabled', (bool) config('services.recaptcha.enabled', false) ? '1' : '0'),
                'recaptcha_provider' => IntegrationSetting::value('recaptcha_provider', 'google'),
                'recaptcha_site_key' => IntegrationSetting::value('recaptcha_site_key', (string) config('services.recaptcha.site_key', '')),
                'recaptcha_secret_key' => '',
                'recaptcha_min_score' => IntegrationSetting::value('recaptcha_min_score', (string) config('services.recaptcha.min_score', '0.5')),
                'recaptcha_trigger_after_attempts' => IntegrationSetting::value('recaptcha_trigger_after_attempts', (string) config('services.recaptcha.trigger_after_attempts', '3')),
            ],
        ]);
    }

    public function update(UpdateIntegrationSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        IntegrationSetting::putMany([
            'brevo_api_key' => $this->resolveSecretValue($validated, 'brevo_api_key'),
            'paypal_client_id' => $validated['paypal_client_id'] ?? null,
            'paypal_client_secret' => $this->resolveSecretValue($validated, 'paypal_client_secret'),
            'paypal_base_url' => $validated['paypal_base_url'] ?? null,
            'dhl_tracking_url' => $validated['dhl_tracking_url'] ?? null,
            'recaptcha_enabled' => $request->boolean('recaptcha_enabled') ? '1' : '0',
            'recaptcha_provider' => $validated['recaptcha_provider'] ?? 'google',
            'recaptcha_site_key' => $validated['recaptcha_site_key'] ?? null,
            'recaptcha_secret_key' => $this->resolveSecretValue($validated, 'recaptcha_secret_key'),
            'recaptcha_min_score' => isset($validated['recaptcha_min_score']) ? (string) $validated['recaptcha_min_score'] : null,
            'recaptcha_trigger_after_attempts' => isset($validated['recaptcha_trigger_after_attempts']) ? (string) $validated['recaptcha_trigger_after_attempts'] : null,
        ]);

        return redirect()
            ->route('admin.maintenance.integrations.edit')
            ->with('status', 'Integration settings updated successfully.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveSecretValue(array $validated, string $key): ?string
    {
        if (! array_key_exists($key, $validated)) {
            return IntegrationSetting::value($key);
        }

        $value = trim((string) ($validated[$key] ?? ''));

        if ($value === '') {
            return IntegrationSetting::value($key);
        }

        return $value;
    }
}
