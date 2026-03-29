<?php

namespace App\Http\Requests;

use App\Models\TagFollow;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTagFollowPreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        /** @var TagFollow|null $tagFollow */
        $tagFollow = $this->route('tagFollow');

        return $tagFollow !== null && (int) $tagFollow->user_id === (int) $user->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'notify_new_drops' => ['sometimes', 'boolean'],
            'notify_discounts' => ['sometimes', 'boolean'],
        ];
    }
}
