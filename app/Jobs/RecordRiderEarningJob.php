<?php

namespace App\Jobs;

use App\Enums\AuditEvent;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\RiderEarning;
use App\Models\RiderProfile;
use App\Services\AuditLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class RecordRiderEarningJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        try {
            DB::transaction(function (): void {
                $order = Order::query()
                    ->with(['vendor:id,lat,lng', 'customer:id,lat,lng'])
                    ->lockForUpdate()
                    ->find($this->orderId);

                if ($order === null || $order->rider_id === null || $order->order_status !== OrderStatus::Delivered) {
                    return;
                }

                $distanceKm = $this->deliveryDistanceKm($order);
                $amount = min(
                    (float) config('rider.max_earning_per_delivery', 200.00),
                    (float) config('rider.delivery_fee', 50.00) + ($distanceKm * (float) config('rider.per_km_bonus', 5.00)),
                );

                $earning = RiderEarning::query()->create([
                    'rider_id' => $order->rider_id,
                    'order_id' => $order->getKey(),
                    'amount' => round($amount, 2),
                    'earned_at' => now(),
                ]);

                $profile = RiderProfile::query()
                    ->where('user_id', $order->rider_id)
                    ->lockForUpdate()
                    ->first();

                if ($profile !== null) {
                    $profile->increment('total_earnings', (float) $earning->amount);
                    $this->updateAverageDeliveryMinutes($profile);
                }

                AuditLogger::log(
                    AuditEvent::RiderEarningRecorded,
                    "Recorded rider earning for order #{$order->id}.",
                    $earning,
                    $order->rider_id,
                    [
                        'amount' => (float) $earning->amount,
                        'distance_km' => round($distanceKm, 2),
                    ],
                );
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateEntry($exception)) {
                return;
            }

            throw $exception;
        }
    }

    private function deliveryDistanceKm(Order $order): float
    {
        $vendorLat = $order->vendor?->lat;
        $vendorLng = $order->vendor?->lng;
        $customerLat = $order->delivery_lat ?? $order->customer?->lat;
        $customerLng = $order->delivery_lng ?? $order->customer?->lng;

        if ($vendorLat === null || $vendorLng === null || $customerLat === null || $customerLng === null) {
            return 0.0;
        }

        return $this->haversineKm((float) $vendorLat, (float) $vendorLng, (float) $customerLat, (float) $customerLng);
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

    private function updateAverageDeliveryMinutes(RiderProfile $profile): void
    {
        $durations = Order::query()
            ->where('rider_id', $profile->user_id)
            ->where('order_status', OrderStatus::Delivered)
            ->whereNotNull('picked_up_at')
            ->get(['picked_up_at', 'updated_at'])
            ->map(fn (Order $order): int => (int) $order->picked_up_at->diffInMinutes($order->updated_at));

        $profile->forceFill([
            'average_delivery_minutes' => $durations->isEmpty() ? null : (int) round($durations->avg()),
        ])->save();
    }

    private function isDuplicateEntry(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23000'
            && (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }
}
