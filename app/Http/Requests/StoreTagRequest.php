<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTagRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['artist', 'brand', 'custom'])],
            'name' => ['required', 'string', 'max:255', Rule::unique('tags', 'name')->where('type', $this->input('type'))],
            'slug' => ['required', 'string', 'max:255', Rule::unique('tags', 'slug')->where('type', $this->input('type'))],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
