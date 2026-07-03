<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'invoice_company_name' => ['required', 'string', 'max:255'],
            'invoice_address_line1' => ['required', 'string', 'max:255'],
            'invoice_address_line2' => ['nullable', 'string', 'max:255'],
            'invoice_postal_code' => ['required', 'string', 'max:20'],
            'invoice_city' => ['required', 'string', 'max:120'],
            'invoice_country' => ['required', 'string', 'max:120'],
            'invoice_email' => ['nullable', 'email', 'max:255'],
            'invoice_website' => ['nullable', 'string', 'max:255'],
            'invoice_tax_identifier' => ['required', 'string', 'max:60'],
            'invoice_zero_tax_note' => ['required', 'string', 'max:500'],
            'invoice_footer_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
