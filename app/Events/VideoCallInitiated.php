<?php

namespace App\Events;

use App\Models\VideoCall;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VideoCallInitiated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public VideoCall $videoCall)
    {
        $this->videoCall->loadMissing('caller:id,name');
    }

    public function broadcastAs(): string
    {
        return 'VideoCallInitiated';
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
            'caller_id' => $this->videoCall->caller_id,
            'caller_name' => $this->videoCall->caller->name,
            'conversation_key' => $this->videoCall->conversation_key,
        ];
    }
}
