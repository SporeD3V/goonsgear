<?php

namespace App\Http\Requests;

use App\Models\BundleDiscount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBundleDiscountRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120', Rule::unique('bundle_discounts', 'name')],
            'description' => ['nullable', 'string', 'max:500'],
            'discount_type' => ['required', 'string', Rule::in(BundleDiscount::supportedTypes())],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'is_active' => ['sometimes', 'boolean'],
            'variant_ids' => ['required', 'array', 'min:1'],
            'variant_ids.*' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }
}
