<?php

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\Rules\Unique;

class UpdateProductVariantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, Unique|string|RequiredIf>>
     */
    public function rules(): array
    {
        $variantId = $this->route('variant')?->id;
        $productId = $this->route('product')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_variants', 'name')
                    ->where(fn (Builder $query) => $query->where('product_id', $productId))
                    ->ignore($variantId),
            ],
            'sku' => ['required', 'string', 'max:255', Rule::unique('product_variants', 'sku')->ignore($variantId)],
            'option_values_json' => ['nullable', 'string', 'json'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'gte:price'],
            'track_inventory' => ['sometimes', 'boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'allow_backorder' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_preorder' => ['sometimes', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
            'preorder_available_from' => ['nullable', 'date'],
            'expected_ship_at' => ['nullable', 'date', Rule::when(
                fn () => $this->filled('preorder_available_from'),
                ['after_or_equal:preorder_available_from']
            )],
        ];
    }
}
