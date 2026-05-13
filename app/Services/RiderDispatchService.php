<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\RiderOfferStatus;
use App\Enums\UserRole;
use App\Events\NotificationCreated;
use App\Events\RiderOfferCreated;
use App\Jobs\ExpireRiderOfferJob;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Notification;
use App\Models\Order;
use App\Models\RiderDeliveryOffer;
use App\Models\RiderProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RiderDispatchService
{
    public function dispatchOrder(Order $order): bool
    {
        $dispatch = DB::transaction(function () use ($order): ?array {
            $lockedOrder = Order::query()
                ->with(['vendor:id,store_name,lat,lng', 'orderItems:id,order_id'])
                ->lockForUpdate()
                ->find($order->getKey());

            if (
                $lockedOrder === null
                || $lockedOrder->order_status !== OrderStatus::Ready
                || $lockedOrder->is_self_pickup
                || $lockedOrder->rider_id !== null
            ) {
                return null;
            }

            $candidate = $this->eligibleRiders($lockedOrder)->first();

            if ($candidate === null) {
                return null;
            }

            $expiresAt = now()->addSeconds((int) config('rider.offer_timeout_seconds', 45));
            $offer = RiderDeliveryOffer::query()->create([
                'order_id' => $lockedOrder->getKey(),
                'rider_id' => $candidate->getKey(),
                'status' => RiderOfferStatus::Pending,
                'offered_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            $candidate->riderProfile?->increment('total_offers_received');
            $candidate->riderProfile?->recalculateStats();

            $timeoutSeconds = (int) config('rider.offer_timeout_seconds', 45);
            $notification = Notification::query()->create([
                'user_id' => $candidate->getKey(),
                'type' => NotificationType::System,
                'title' => 'New delivery offer',
                'message' => 'Order from '.$lockedOrder->vendor->store_name.' - '.$lockedOrder->orderItems->count().' items. Accept within '.$timeoutSeconds.' seconds.',
                'data' => [
                    'route' => 'rider.dashboard',
                    'order_id' => $lockedOrder->getKey(),
                    'offer_id' => $offer->getKey(),
                ],
            ]);

            event(new NotificationCreated($notification));

            AuditLogger::log(
                AuditEvent::RiderOfferSent,
                "Delivery offer #{$offer->id} sent for order #{$lockedOrder->id}.",
                $offer,
                $candidate->getKey(),
            );

            return [
                'offer' => $offer,
                'distance_km' => $this->distanceFromVendorToRider($lockedOrder, $candidate),
                'expires_at' => $expiresAt,
            ];
        });

        if ($dispatch === null) {
            return false;
        }

        event(new RiderOfferCreated($dispatch['offer'], $dispatch['distance_km']));

        ExpireRiderOfferJob::dispatch($dispatch['offer']->getKey())
            ->delay($dispatch['expires_at']);

        return true;
    }

    public function acceptOffer(RiderDeliveryOffer $offer, User $rider): Order
    {
        $order = DB::transaction(function () use ($offer, $rider): Order {
            $lockedOffer = RiderDeliveryOffer::query()
                ->lockForUpdate()
                ->findOrFail($offer->getKey());
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->findOrFail($lockedOffer->order_id);

            abort_unless($lockedOffer->rider_id === $rider->getKey(), 403);
            abort_unless($lockedOffer->status === RiderOfferStatus::Pending, 409);
            abort_if($lockedOffer->expires_at->isPast(), 409);
            abort_if($lockedOrder->rider_id !== null, 409);

            $lockedOffer->forceFill([
                'status' => RiderOfferStatus::Accepted,
                'responded_at' => now(),
            ])->save();

            $profile = RiderProfile::query()
                ->where('user_id', $rider->getKey())
                ->lockForUpdate()
                ->first();

            if ($profile !== null) {
                $profile->increment('total_offers_accepted');
                $profile->recalculateStats();
            }

            $lockedOrder->forceFill([
                'rider_id' => $rider->getKey(),
                'order_status' => OrderStatus::PickedUp,
                'picked_up_at' => now(),
            ])->save();

            AuditLogger::log(
                AuditEvent::RiderOfferAccepted,
                "Rider accepted offer #{$lockedOffer->id} for order #{$lockedOrder->id}.",
                $lockedOffer,
                $rider->getKey(),
            );

            return $lockedOrder->fresh(['customer:id,name', 'vendor:id,store_name']);
        });

        SendOrderNotificationJob::dispatch(
            orderId: $order->getKey(),
            userId: $order->customer_id,
            title: 'Rider assigned',
            message: "A rider has picked up your order #{$order->id} and is heading to you.",
            type: NotificationType::OrderUpdate,
            broadcastOrderStatus: true,
        );

        return $order;
    }

    public function declineOffer(RiderDeliveryOffer $offer, User $rider): void
    {
        $order = DB::transaction(function () use ($offer, $rider): Order {
            $lockedOffer = RiderDeliveryOffer::query()
                ->with('order')
                ->lockForUpdate()
                ->findOrFail($offer->getKey());

            abort_unless($lockedOffer->rider_id === $rider->getKey(), 403);
            abort_unless($lockedOffer->status === RiderOfferStatus::Pending, 409);

            $lockedOffer->forceFill([
                'status' => RiderOfferStatus::Declined,
                'responded_at' => now(),
            ])->save();

            $profile = RiderProfile::query()
                ->where('user_id', $rider->getKey())
                ->lockForUpdate()
                ->first();

            if ($profile !== null) {
                $profile->increment('total_offers_received');
                $profile->recalculateStats();
            }

            AuditLogger::log(
                AuditEvent::RiderOfferDeclined,
                "Rider declined offer #{$lockedOffer->id} for order #{$lockedOffer->order_id}.",
                $lockedOffer,
                $rider->getKey(),
            );

            return $lockedOffer->order;
        });

        $this->dispatchOrder($order);
    }

    /**
     * @return Collection<int, User>
     */
    private function eligibleRiders(Order $order): Collection
    {
        $maxConcurrentDeliveries = (int) config('rider.max_concurrent_deliveries', 3);
        $searchRadiusKm = (float) config('rider.search_radius_km', 15);

        return User::query()
            ->where('role', UserRole::Rider->value)
            ->whereHas('riderProfile', function ($query): void {
                $query
                    ->where('status', 'approved')
                    ->where('is_available', true);
            })
            ->whereDoesntHave('riderDeliveryOffers', function ($query) use ($order): void {
                $query->where('order_id', $order->getKey());
            })
            ->with('riderProfile')
            ->withCount([
                'riderOrders as active_deliveries_count' => function ($query): void {
                    $query->whereIn('order_status', [OrderStatus::PickedUp, OrderStatus::OutForDelivery]);
                },
            ])
            ->get()
            ->filter(function (User $rider) use ($order, $maxConcurrentDeliveries, $searchRadiusKm): bool {
                if ((int) $rider->active_deliveries_count >= $maxConcurrentDeliveries) {
                    return false;
                }

                $distance = $this->distanceFromVendorToRider($order, $rider);

                return $distance === null || $distance <= $searchRadiusKm;
            })
            ->sort(function (User $first, User $second): int {
                $activeComparison = (int) $first->active_deliveries_count <=> (int) $second->active_deliveries_count;

                if ($activeComparison !== 0) {
                    return $activeComparison;
                }

                return (float) ($second->riderProfile?->rating ?? 0) <=> (float) ($first->riderProfile?->rating ?? 0);
            })
            ->values();
    }

    private function distanceFromVendorToRider(Order $order, User $rider): ?float
    {
        if ($order->vendor?->lat === null || $order->vendor?->lng === null || $rider->lat === null || $rider->lng === null) {
            return null;
        }

        return $this->haversineKm(
            (float) $order->vendor->lat,
            (float) $order->vendor->lng,
            (float) $rider->lat,
            (float) $rider->lng,
        );
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);
        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLng / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
