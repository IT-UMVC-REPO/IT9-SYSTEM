<?php

namespace App\Http\Controllers;

use App\Models\RiderEarning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiderEarningsController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $riderId = (int) $request->user()->getKey();
        $baseQuery = RiderEarning::query()->forRider($riderId);

        $recent = (clone $baseQuery)
            ->with('order.vendor:id,store_name')
            ->latest('earned_at')
            ->limit(10)
            ->get()
            ->map(fn (RiderEarning $earning): array => [
                'order_number' => str_pad((string) $earning->order_id, 6, '0', STR_PAD_LEFT),
                'amount' => (float) $earning->amount,
                'earned_at' => $earning->earned_at?->toIso8601String(),
                'store_name' => $earning->order?->vendor?->store_name ?? 'Vendor',
            ])
            ->values();
        $daily = collect(range(6, 0))
            ->map(function (int $daysAgo) use ($baseQuery): array {
                $day = now()->subDays($daysAgo);

                return [
                    'label' => $day->format('M j'),
                    'amount' => (float) (clone $baseQuery)
                        ->whereBetween('earned_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                        ->sum('amount'),
                ];
            })
            ->values();

        return response()->json([
            'today' => (float) (clone $baseQuery)
                ->whereBetween('earned_at', [now()->startOfDay(), now()->endOfDay()])
                ->sum('amount'),
            'this_week' => (float) (clone $baseQuery)
                ->whereBetween('earned_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->sum('amount'),
            'this_month' => (float) (clone $baseQuery)
                ->thisMonth()
                ->sum('amount'),
            'all_time' => (float) (clone $baseQuery)->sum('amount'),
            'last_7_days' => $daily,
            'recent' => $recent,
        ]);
    }
}
