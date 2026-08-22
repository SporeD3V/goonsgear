<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIntegrationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'brevo_api_key' => ['nullable', 'string', 'max:4096'],
            'paypal_client_id' => ['nullable', 'string', 'max:500'],
            'paypal_client_secret' => ['nullable', 'string', 'max:4096'],
            'paypal_base_url' => ['nullable', 'url:http,https', 'max:500'],
            'dhl_tracking_url' => [
                'nullable',
                'string',
                'max:500',
                // The stored URL carries a literal %s where the tracking number
                // is substituted, which is not valid percent-encoding, so the
                // built-in url rule rejects it. Validate the filled-in form.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $probe = str_replace('%s', 'TRACKING123', (string) $value);

                    if (preg_match('#^https?://#i', $probe) !== 1 || filter_var($probe, FILTER_VALIDATE_URL) === false) {
                        $fail('The DHL tracking URL must be a valid http or https URL, using %s where the tracking number goes.');
                    }
                },
            ],
            'recaptcha_enabled' => ['sometimes', 'boolean'],
            'recaptcha_site_key' => ['nullable', 'string', 'max:1000'],
            'recaptcha_secret_key' => ['nullable', 'string', 'max:4096'],
            'recaptcha_min_score' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'recaptcha_trigger_after_attempts' => ['nullable', 'integer', 'min:0', 'max:100'],
            'recaptcha_provider' => ['nullable', 'string', Rule::in(['google'])],
        ];
    }
}
