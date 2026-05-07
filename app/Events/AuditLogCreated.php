<?php

namespace App\Events;

use App\Models\AuditLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuditLogCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AuditLog $log) {}

    public function broadcastAs(): string
    {
        return 'AuditLogCreated';
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('admin.audit')];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->log->loadMissing('user:id,name');

        return [
            'id' => $this->log->getKey(),
            'event' => $this->log->event->value,
            'label' => $this->log->event->label(),
            'icon' => $this->log->event->icon(),
            'color' => $this->log->event->color(),
            'description' => $this->log->description,
            'user_id' => $this->log->user_id,
            'user_name' => $this->log->user?->name ?? 'System',
            'created_at' => $this->log->created_at?->toIso8601String(),
            'ip_address' => $this->log->ip_address,
        ];
    }
}
