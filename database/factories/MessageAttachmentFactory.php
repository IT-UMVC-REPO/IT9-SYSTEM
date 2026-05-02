<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageAttachment>
 */
class MessageAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'path' => 'message-attachments/'.fake()->uuid().'.pdf',
            'name' => 'attachment.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
            'created_at' => now(),
        ];
    }
}
