<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $conversationKey;

    public function __construct(public Message $message, public string $action)
    {
        $this->conversationKey = min($message->sender_id, $message->receiver_id)
            .'-'
            .max($message->sender_id, $message->receiver_id);
    }

    public function broadcastAs(): string
    {
        return 'MessageUpdated';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('messaging.'.$this->conversationKey),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->getKey(),
            'action' => $this->action,
            'content' => $this->message->content,
            'edited_at' => $this->message->edited_at?->toIso8601String(),
            'deleted_at' => $this->message->deleted_at?->toIso8601String(),
            'deleted_for_everyone_at' => $this->message->deleted_for_everyone_at?->toIso8601String(),
        ];
    }
}
