<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Notification $notification) {}

    public function broadcastAs(): string
    {
        return 'NotificationCreated';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notifications.'.$this->notification->user_id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->getKey(),
            'title' => $this->notification->title,
            'message' => $this->notification->message,
            'type' => $this->notification->type->value,
            'created_at' => $this->notification->created_at?->toIso8601String(),
        ];
    }
}
