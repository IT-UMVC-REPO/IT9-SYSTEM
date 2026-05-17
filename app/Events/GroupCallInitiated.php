<?php

namespace App\Events;

use App\Models\VideoCall;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class GroupCallInitiated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(public VideoCall $videoCall)
    {
        $this->broadcastVia('pusher');
        $this->videoCall->loadMissing(['caller:id,name', 'group:id,name']);
    }

    public function broadcastAs(): string
    {
        return 'GroupCallInitiated';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('group.'.$this->videoCall->group_id),
        ];

        return $this->groupMemberIds()
            ->map(fn (int $userId): PrivateChannel => new PrivateChannel('calls.'.$userId))
            ->prepend($channels[0])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->videoCall->getKey(),
            'caller_id' => $this->videoCall->caller_id,
            'caller_name' => $this->videoCall->caller->name,
            'group_id' => $this->videoCall->group_id,
            'group_name' => $this->videoCall->group?->name,
            'is_group_call' => true,
        ];
    }

    /**
     * @return Collection<int, int>
     */
    private function groupMemberIds(): Collection
    {
        if ($this->videoCall->group_id === null) {
            return collect();
        }

        return $this->videoCall->group
            ? $this->videoCall->group->members()->pluck('user_id')->map(fn ($userId): int => (int) $userId)
            : collect();
    }
}
