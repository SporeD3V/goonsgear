<?php

namespace Database\Factories;

use App\Models\AdminNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminNote>
 */
class AdminNoteFactory extends Factory
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
            'content' => fake()->sentence(),
            'is_pinned' => false,
            'color' => fake()->randomElement(['warm', 'sky', 'sage', 'rose', 'lavender']),
        ];
    }

    public function pinned(): static
    {
        return $this->state(['is_pinned' => true]);
    }
}
