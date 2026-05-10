<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Events\RiderLocationUpdated;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiderLocationController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'order_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $profile = $user?->riderProfile;

        if ($profile !== null) {
            $profile->update([
                'current_lat' => $validated['lat'],
                'current_lng' => $validated['lng'],
            ]);
        }

        if (isset($validated['order_id'])) {
            $updated = Order::query()
                ->where('rider_id', $user?->getKey())
                ->whereIn('order_status', [OrderStatus::PickedUp, OrderStatus::OutForDelivery])
                ->whereKey((int) $validated['order_id'])
                ->update([
                    'rider_lat' => $validated['lat'],
                    'rider_lng' => $validated['lng'],
                ]);

            if ($updated > 0) {
                broadcast(new RiderLocationUpdated(
                    orderId: (int) $validated['order_id'],
                    lat: (float) $validated['lat'],
                    lng: (float) $validated['lng'],
                ))->toOthers();
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
