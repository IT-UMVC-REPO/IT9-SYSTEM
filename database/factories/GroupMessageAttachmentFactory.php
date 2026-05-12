<?php

namespace Database\Factories;

use App\Models\GroupMessage;
use App\Models\GroupMessageAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupMessageAttachment>
 */
class GroupMessageAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_message_id' => GroupMessage::factory(),
            'path' => 'group-message-attachments/'.fake()->uuid().'.pdf',
            'name' => 'attachment.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
            'created_at' => now(),
        ];
    }
}
