<?php

namespace Database\Factories;

use App\Enums\VideoCallStatus;
use App\Models\User;
use App\Models\VideoCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoCall>
 */
class VideoCallFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (VideoCall $videoCall): void {
            $videoCall->forceFill([
                'conversation_key' => VideoCall::conversationKeyFor($videoCall->caller_id, $videoCall->receiver_id),
            ])->saveQuietly();
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'caller_id' => User::factory(),
            'receiver_id' => User::factory(),
            'group_id' => null,
            'is_group_call' => false,
            'conversation_key' => '0-0',
            'status' => VideoCallStatus::Pending,
            'started_at' => null,
            'ended_at' => null,
            'created_at' => now(),
        ];
    }
}
