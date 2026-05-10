<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiderLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $orderId,
        public readonly float $lat,
        public readonly float $lng,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('order.'.$this->orderId);
    }

    public function broadcastAs(): string
    {
        return 'RiderLocationUpdated';
    }

    /**
     * @return array{order_id: int, lat: float, lng: float}
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'lat' => $this->lat,
            'lng' => $this->lng,
        ];
    }
}
