<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateTagRequest extends FormRequest
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
        $tagId = $this->route('tag')?->id;

        return [
            'type' => ['required', 'string', Rule::in(['artist', 'brand', 'custom'])],
            'name' => ['required', 'string', 'max:255', Rule::unique('tags', 'name')->where('type', $this->input('type'))->ignore($tagId)],
            'slug' => ['required', 'string', 'max:255', Rule::unique('tags', 'slug')->where('type', $this->input('type'))->ignore($tagId)],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'max:5120', Rule::when(
                $this->input('logo') !== null,
                [Rule::when(! in_array($this->input('type'), ['artist', 'brand'], true), ['prohibited'])]
            )],
            'remove_logo' => ['sometimes', 'boolean'],
            'show_on_homepage' => ['sometimes', 'boolean'],
        ];
    }
}
