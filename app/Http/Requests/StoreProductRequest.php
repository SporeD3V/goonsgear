<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'primary_category_id' => ['nullable', 'exists:categories,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('products', 'name')],
            'slug' => ['required', 'string', 'max:255', Rule::unique('products', 'slug')],
            'status' => ['required', 'string', Rule::in(['draft', 'active', 'archived'])],
            'excerpt' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_preorder' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'preorder_available_from' => ['nullable', 'date'],
            'expected_ship_at' => ['nullable', 'date'],
        ];
    }
}
