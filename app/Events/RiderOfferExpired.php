<?php

namespace App\Events;

use App\Models\RiderDeliveryOffer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiderOfferExpired implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public RiderDeliveryOffer $offer) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('calls.'.$this->offer->rider_id);
    }

    public function broadcastAs(): string
    {
        return '.RiderOfferExpired';
    }

    /**
     * @return array{offer_id: int, order_id: int}
     */
    public function broadcastWith(): array
    {
        return [
            'offer_id' => $this->offer->getKey(),
            'order_id' => $this->offer->order_id,
        ];
    }
}
