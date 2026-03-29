<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['required', 'string', 'size:2'],
            'state' => ['nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:20'],
            'street_name' => ['required', 'string', 'max:200'],
            'street_number' => ['required', 'string', 'max:20'],
            'apartment_block' => ['nullable', 'string', 'max:50'],
            'entrance' => ['nullable', 'string', 'max:50'],
            'floor' => ['nullable', 'string', 'max:20'],
            'apartment_number' => ['nullable', 'string', 'max:20'],
            'recaptcha_token' => ['nullable', 'string', 'max:4096'],
        ];
    }
}
