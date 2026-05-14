<?php

namespace App\Events;

use App\Models\VideoCall;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupCallSignal implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $signalData
     */
    public function __construct(
        public VideoCall $videoCall,
        public int $senderId,
        public ?int $recipientId,
        public array $signalData,
    ) {
        $this->broadcastVia('pusher');
    }

    public function broadcastAs(): string
    {
        return 'GroupCallSignal';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('call.'.$this->videoCall->getKey()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->videoCall->getKey(),
            'group_id' => $this->videoCall->group_id,
            'sender_id' => $this->senderId,
            'recipient_id' => $this->recipientId,
            'signal_data' => $this->signalData,
        ];
    }
}
