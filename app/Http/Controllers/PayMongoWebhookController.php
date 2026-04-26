<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Payment;
use App\Services\PayMongoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PayMongoWebhookController extends Controller
{
    public function __construct(private readonly PayMongoService $payMongo) {}

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Paymongo-Signature');

        try {
            $event = $this->payMongo->constructWebhookEvent($payload, $signature);
        } catch (RuntimeException $exception) {
            return response('Invalid signature', 400);
        }

        if (($event['data']['attributes']['type'] ?? null) !== 'source.chargeable') {
            return response('OK', 200);
        }

        $referenceNumber = (string) ($event['data']['id'] ?? '');
        $payment = Payment::query()->where('reference_number', $referenceNumber)->first();

        if ($payment === null) {
            return response('OK', 200);
        }

        if ($payment->status === PaymentStatus::Paid) {
            return response('OK', 200);
        }

        $order = $payment->order()->with('customer')->first();

        if ($order === null) {
            return response('OK', 200);
        }

        try {
            $this->payMongo->createPaymentFromSource(
                $referenceNumber,
                (int) round((float) $payment->amount * 100),
                'Payment for order #'.$order->getKey(),
            );
        } catch (RuntimeException $exception) {
            Log::error('Failed to convert PayMongo source into a payment.', [
                'payment_id' => $payment->getKey(),
                'reference_number' => $referenceNumber,
                'exception' => $exception->getMessage(),
            ]);

            return response('Unable to process payment', 500);
        }

        $payment->forceFill([
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ])->save();

        $order->forceFill([
            'payment_status' => PaymentStatus::Paid,
        ])->save();

        SendOrderNotificationJob::dispatch(
            orderId: $order->getKey(),
            userId: $order->customer_id,
            title: 'Payment confirmed',
            message: 'Payment confirmed for order #'.$order->getKey().'.',
            type: NotificationType::OrderUpdate,
            broadcastOrderStatus: true,
        );

        return response('OK', 200);
    }
}
