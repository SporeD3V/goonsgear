<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, Unique|string>>
     */
    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'primary_category_id' => ['nullable', 'exists:categories,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('products', 'name')->ignore($productId)],
            'slug' => ['required', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId)],
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
            'media_files' => ['nullable', 'array'],
            'media_files.*' => ['file', 'mimes:jpg,jpeg,png,webp,avif,mp4,webm,mov', 'max:51200'],
            'media_alt_text' => ['nullable', 'string', 'max:255'],
            'media_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
        ];
    }
}
