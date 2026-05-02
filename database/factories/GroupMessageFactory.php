<?php

namespace Database\Factories;

use App\Models\ConversationGroup;
use App\Models\GroupMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupMessage>
 */
class GroupMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => ConversationGroup::factory(),
            'sender_id' => User::factory(),
            'content' => fake()->sentence(),
            'created_at' => now(),
        ];
    }
}
