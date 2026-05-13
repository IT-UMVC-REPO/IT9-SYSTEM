<?php

namespace App\Events;

use App\Models\VideoCall;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupCallStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(public VideoCall $videoCall)
    {
        $this->broadcastVia('pusher');
    }

    public function broadcastAs(): string
    {
        return 'GroupCallStatusChanged';
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
        $participants = $this->videoCall->participants()
            ->whereNull('left_at')
            ->orderBy('user_id')
            ->pluck('user_id')
            ->values()
            ->all();

        return [
            'call_id' => $this->videoCall->getKey(),
            'group_id' => $this->videoCall->group_id,
            'status' => $this->videoCall->status->value,
            'participants' => $participants,
        ];
    }
}
