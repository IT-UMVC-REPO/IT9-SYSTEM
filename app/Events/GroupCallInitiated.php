<?php

namespace App\Events;

use App\Models\VideoCall;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupCallInitiated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public VideoCall $videoCall)
    {
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
        return [
            new PrivateChannel('group.'.$this->videoCall->group_id),
        ];
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
}
