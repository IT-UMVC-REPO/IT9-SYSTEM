<?php

namespace App\Events;

use App\Models\VideoCall;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VideoCallStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(public VideoCall $videoCall)
    {
        $this->broadcastVia('pusher');
    }

    public function broadcastAs(): string
    {
        return 'VideoCallStatusChanged';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('messaging.'.$this->videoCall->conversation_key),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->videoCall->getKey(),
            'status' => $this->videoCall->status->value,
            'conversation_key' => $this->videoCall->conversation_key,
        ];
    }
}
