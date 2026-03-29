<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIntegrationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'dhl_tracking_url' => ['nullable', 'string', 'max:500'],
            'recaptcha_enabled' => ['sometimes', 'boolean'],
            'recaptcha_site_key' => ['nullable', 'string', 'max:1000'],
            'recaptcha_secret_key' => ['nullable', 'string', 'max:4096'],
            'recaptcha_min_score' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'recaptcha_trigger_after_attempts' => ['nullable', 'integer', 'min:0', 'max:100'],
            'recaptcha_provider' => ['nullable', 'string', Rule::in(['google'])],
        ];
    }
}
