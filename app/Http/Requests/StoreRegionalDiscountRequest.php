<?php

namespace App\Http\Requests;

use App\Models\RegionalDiscount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRegionalDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'country_code' => ['required', 'string', 'size:2', 'unique:regional_discounts,country_code'],
            'discount_type' => ['required', 'string', 'in:'.implode(',', RegionalDiscount::supportedTypes())],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
