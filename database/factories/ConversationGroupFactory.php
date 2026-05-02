<?php

namespace Database\Factories;

use App\Models\ConversationGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationGroup>
 */
class ConversationGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->optional()->words(2, true),
            'created_by' => User::factory(),
            'avatar_path' => null,
        ];
    }
}
