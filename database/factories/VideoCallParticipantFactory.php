<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VideoCall;
use App\Models\VideoCallParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoCallParticipant>
 */
class VideoCallParticipantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'video_call_id' => VideoCall::factory(),
            'user_id' => User::factory(),
            'joined_at' => now(),
            'left_at' => null,
        ];
    }
}
