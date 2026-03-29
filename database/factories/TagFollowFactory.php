<?php

namespace Database\Factories;

use App\Models\Tag;
use App\Models\TagFollow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TagFollow>
 */
class TagFollowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tag_id' => Tag::factory(),
            'notify_new_drops' => true,
            'notify_discounts' => true,
        ];
    }
}
