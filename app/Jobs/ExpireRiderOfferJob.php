<?php

namespace App\Jobs;

use App\Enums\AuditEvent;
use App\Enums\NotificationType;
use App\Enums\RiderOfferStatus;
use App\Events\NotificationCreated;
use App\Events\RiderOfferExpired;
use App\Models\Notification;
use App\Models\RiderDeliveryOffer;
use App\Services\AuditLogger;
use App\Services\RiderDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ExpireRiderOfferJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $offerId) {}

    public function handle(RiderDispatchService $dispatchService): void
    {
        $offer = DB::transaction(function (): ?RiderDeliveryOffer {
            $offer = RiderDeliveryOffer::query()
                ->with(['order.vendor', 'rider.riderProfile'])
                ->lockForUpdate()
                ->find($this->offerId);

            if ($offer === null || $offer->status !== RiderOfferStatus::Pending) {
                return null;
            }

            $notification = Notification::query()->create([
                'user_id' => $offer->rider_id,
                'type' => NotificationType::System,
                'title' => 'Delivery offer expired',
                'message' => 'An offer for order #'.str_pad((string) $offer->order_id, 6, '0', STR_PAD_LEFT).' was not responded to in time.',
                'data' => [
                    'route' => 'rider.dashboard',
                    'order_id' => $offer->order_id,
                    'offer_id' => $offer->getKey(),
                ],
            ]);

            event(new NotificationCreated($notification));

            $offer->forceFill([
                'status' => RiderOfferStatus::Expired,
                'responded_at' => now(),
            ])->save();

            $offer->rider?->riderProfile?->recalculateStats();

            AuditLogger::log(
                AuditEvent::RiderOfferExpired,
                "Delivery offer #{$offer->id} expired for order #{$offer->order_id}.",
                $offer,
                $offer->rider_id,
            );

            return $offer->fresh(['order']);
        });

        if ($offer === null || $offer->order === null) {
            return;
        }

        event(new RiderOfferExpired($offer));

        $dispatchService->dispatchOrder($offer->order);
    }
}
