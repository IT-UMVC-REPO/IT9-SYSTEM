<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentReturnController extends Controller
{
    public function success(Request $request): View|RedirectResponse
    {
        $orderId = $request->session()->pull('pending_payment_order_id');
        $order = $orderId === null
            ? null
            : Order::query()->with(['vendor:id,store_name'])->find($orderId);

        if ($order === null) {
            return redirect()->route('shop.home');
        }

        return view('pages.shop.checkout-success', [
            'order' => $order,
        ]);
    }

    public function failed(Request $request): View
    {
        return view('pages.shop.checkout-failed', [
            'orderId' => $request->session()->pull('pending_payment_order_id'),
        ]);
    }
}
