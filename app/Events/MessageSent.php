<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $conversationKey;

    public function __construct(public Message $message)
    {
        $this->conversationKey = min($message->sender_id, $message->receiver_id)
            .'-'
            .max($message->sender_id, $message->receiver_id);
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

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
            'sender_id' => $this->message->sender_id,
            'receiver_id' => $this->message->receiver_id,
            'order_id' => $this->message->order_id,
            'content' => $this->message->content,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
