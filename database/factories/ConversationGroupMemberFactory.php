<?php

namespace Database\Factories;

use App\Models\ConversationGroup;
use App\Models\ConversationGroupMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationGroupMember>
 */
class ConversationGroupMemberFactory extends Factory
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
            'user_id' => User::factory(),
            'role' => 'member',
            'joined_at' => now(),
            'last_read_at' => null,
        ];
    }
}
