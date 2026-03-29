<?php

namespace App\Http\Requests;

use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(Coupon::supportedTypes())],
            'value' => ['required', 'numeric', 'min:0.01'],
            'minimum_subtotal' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'used_count' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
            'is_stackable' => ['sometimes', 'boolean'],
            'stack_group' => ['nullable', 'string', 'max:50'],
            'scope_type' => ['nullable', 'string', Rule::in(Coupon::supportedScopes())],
            'scope_product_id' => [
                'nullable',
                'integer',
                'exists:products,id',
                Rule::requiredIf(fn (): bool => $this->input('scope_type') === Coupon::SCOPE_PRODUCT),
                Rule::prohibitedIf(fn (): bool => in_array($this->input('scope_type'), [Coupon::SCOPE_CATEGORY, Coupon::SCOPE_TAG], true)),
            ],
            'scope_category_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                Rule::requiredIf(fn (): bool => $this->input('scope_type') === Coupon::SCOPE_CATEGORY),
                Rule::prohibitedIf(fn (): bool => in_array($this->input('scope_type'), [Coupon::SCOPE_PRODUCT, Coupon::SCOPE_TAG], true)),
            ],
            'scope_tag_id' => [
                'nullable',
                'integer',
                'exists:tags,id',
                Rule::requiredIf(fn (): bool => $this->input('scope_type') === Coupon::SCOPE_TAG),
                Rule::prohibitedIf(fn (): bool => in_array($this->input('scope_type'), [Coupon::SCOPE_PRODUCT, Coupon::SCOPE_CATEGORY], true)),
            ],
            'is_personal' => ['sometimes', 'boolean'],
            'assigned_user_ids' => [
                'nullable',
                'array',
                'min:1',
                Rule::requiredIf(fn (): bool => $this->boolean('is_personal')),
            ],
            'assigned_user_ids.*' => ['integer', 'exists:users,id'],
            'user_usage_limit' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
