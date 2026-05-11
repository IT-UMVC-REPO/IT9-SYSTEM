<?php

namespace App\Events;

use App\Models\VideoCall;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VideoCallSignal implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $signalData
     */
    public function __construct(
        public VideoCall $videoCall,
        public int $senderId,
        public array $signalData,
    ) {
        $this->broadcastVia('pusher');
    }

    public function broadcastAs(): string
    {
        return 'VideoCallSignal';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('messaging.'.$this->videoCall->conversation_key),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->videoCall->getKey(),
            'sender_id' => $this->senderId,
            'signal_data' => $this->signalData,
        ];
    }
}
