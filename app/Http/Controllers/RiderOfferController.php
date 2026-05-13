<?php

namespace App\Http\Controllers;

use App\Models\RiderDeliveryOffer;
use App\Services\RiderDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RiderOfferController extends Controller
{
    public function accept(Request $request, RiderDeliveryOffer $offer, RiderDispatchService $dispatchService): JsonResponse|RedirectResponse
    {
        $order = $dispatchService->acceptOffer($offer, $request->user());
        $redirectUrl = route('rider.deliveries.show', ['orderReference' => $order->getKey()]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect()->to($redirectUrl);
    }

    public function decline(Request $request, RiderDeliveryOffer $offer, RiderDispatchService $dispatchService): JsonResponse
    {
        $dispatchService->declineOffer($offer, $request->user());

        return response()->json(['success' => true]);
    }
}
