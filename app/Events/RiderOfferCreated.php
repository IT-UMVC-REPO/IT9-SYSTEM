<?php

namespace App\Events;

use App\Models\RiderDeliveryOffer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiderOfferCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public RiderDeliveryOffer $offer,
        public ?float $distanceKm = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('calls.'.$this->offer->rider_id);
    }

    public function broadcastAs(): string
    {
        return '.RiderOfferCreated';
    }

    /**
     * @return array{order_id: int, offer_id: int, store_name: string, item_count: int, distance_km: float|null, expires_at: string|null, order_number: string}
     */
    public function broadcastWith(): array
    {
        $this->offer->loadMissing('order.vendor');
        $order = $this->offer->order;

        return [
            'order_id' => $this->offer->order_id,
            'offer_id' => $this->offer->getKey(),
            'store_name' => $order?->vendor?->store_name ?? 'Vendor',
            'item_count' => $order?->orderItems()->count() ?? 0,
            'distance_km' => $this->distanceKm === null ? null : round($this->distanceKm, 1),
            'expires_at' => $this->offer->expires_at?->toIso8601String(),
            'order_number' => str_pad((string) $this->offer->order_id, 6, '0', STR_PAD_LEFT),
        ];
    }
}
