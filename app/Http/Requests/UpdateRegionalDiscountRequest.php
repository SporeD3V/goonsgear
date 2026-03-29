<?php

namespace App\Http\Requests;

use App\Models\RegionalDiscount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRegionalDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var RegionalDiscount $discount */
        $discount = $this->route('regional_discount');

        return [
            'country_code' => ['required', 'string', 'size:2', Rule::unique('regional_discounts', 'country_code')->ignore($discount->id)],
            'discount_type' => ['required', 'string', 'in:'.implode(',', RegionalDiscount::supportedTypes())],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
