<?php

namespace App\Events;

use App\Models\GroupMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public GroupMessage $message) {}

    public function broadcastAs(): string
    {
        return 'GroupMessageSent';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('group.'.$this->message->group_id),
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
            'group_id' => $this->message->group_id,
            'content' => $this->message->content,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
