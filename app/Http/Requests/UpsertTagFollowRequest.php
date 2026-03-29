<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertTagFollowRequest extends FormRequest
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
            'tag_id' => ['required', 'integer', Rule::exists('tags', 'id')->where('is_active', true)],
            'notify_new_drops' => ['sometimes', 'boolean'],
            'notify_discounts' => ['sometimes', 'boolean'],
        ];
    }
}
