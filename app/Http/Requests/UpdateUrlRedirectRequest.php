<?php

namespace App\Http\Requests;

use App\Models\UrlRedirect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUrlRedirectRequest extends FormRequest
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
        /** @var UrlRedirect $urlRedirect */
        $urlRedirect = $this->route('url_redirect');

        return [
            'from_path' => ['required', 'string', 'max:255', Rule::unique('url_redirects', 'from_path')->ignore($urlRedirect)],
            'to_url' => ['required', 'string', 'max:2048'],
            'status_code' => ['required', 'integer', Rule::in([301, 302])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
