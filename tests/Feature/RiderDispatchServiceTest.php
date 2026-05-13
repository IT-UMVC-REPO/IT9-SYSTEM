<?php

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RiderOfferStatus;
use App\Events\NotificationCreated;
use App\Events\RiderOfferCreated;
use App\Jobs\ExpireRiderOfferJob;
use App\Jobs\RecordRiderEarningJob;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RiderDeliveryOffer;
use App\Models\RiderEarning;
use App\Models\RiderProfile;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\RiderDispatchService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

function createDispatchReadyOrder(array $overrides = []): Order
{
    $customer = User::factory()->create([
        'name' => 'Customer Mira',
        'address' => 'Customer Home, Tagum City',
        'lat' => 7.449000,
        'lng' => 125.810000,
    ]);
    $vendorUser = User::factory()->vendor()->create();
    $vendor = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create([
        'store_name' => 'Ate Mila Produce',
        'vendor_address' => 'Public Market Stall 12, Tagum City',
        'lat' => 7.447900,
        'lng' => 125.809000,
    ]);

    $order = Order::factory()
        ->for($customer, 'customer')
        ->for($vendor, 'vendor')
        ->create([
            'total_amount' => 450,
            'payment_status' => PaymentStatus::Pending,
            'order_status' => OrderStatus::Ready,
            'delivery_address' => $customer->address,
            'delivery_lat' => $customer->lat,
            'delivery_lng' => $customer->lng,
            ...$overrides,
        ]);

    Payment::factory()->for($order)->create([
        'amount' => $order->total_amount,
        'status' => $order->payment_status,
    ]);

    return $order;
}

test('dispatch service creates a timed delivery offer for the best eligible rider', function () {
    Event::fake([NotificationCreated::class, RiderOfferCreated::class]);
    Queue::fake([ExpireRiderOfferJob::class]);

    $rider = User::factory()->rider()->create([
        'lat' => 7.448000,
        'lng' => 125.809500,
    ]);
    RiderProfile::factory()->for($rider, 'user')->approved()->create([
        'rating' => 4.80,
    ]);
    $order = createDispatchReadyOrder();

    expect((new RiderDispatchService)->dispatchOrder($order))->toBeTrue();

    $offer = RiderDeliveryOffer::query()->whereBelongsTo($order)->first();

    expect($offer)->not->toBeNull()
        ->and($offer->rider_id)->toBe($rider->getKey())
        ->and($offer->status)->toBe(RiderOfferStatus::Pending)
        ->and($offer->expires_at)->not->toBeNull();

    expect(Notification::query()
        ->where('user_id', $rider->getKey())
        ->where('type', NotificationType::System)
        ->where('title', 'New delivery offer')
        ->exists())->toBeTrue();

    Event::assertDispatched(RiderOfferCreated::class, fn (RiderOfferCreated $event) => $event->offer->is($offer));
    Queue::assertPushed(ExpireRiderOfferJob::class);
});

test('rider can accept an offer through the api endpoint', function () {
    Event::fake([NotificationCreated::class, RiderOfferCreated::class]);
    Queue::fake([ExpireRiderOfferJob::class, SendOrderNotificationJob::class]);

    $rider = User::factory()->rider()->create();
    $profile = RiderProfile::factory()->for($rider, 'user')->approved()->create();
    $order = createDispatchReadyOrder();

    (new RiderDispatchService)->dispatchOrder($order);
    $offer = RiderDeliveryOffer::query()->firstOrFail();

    $this->actingAs($rider)
        ->postJson(route('rider.offers.accept', $offer))
        ->assertOk()
        ->assertJson(['success' => true]);

    $order->refresh();
    $profile->refresh();

    expect($order->rider_id)->toBe($rider->getKey())
        ->and($order->order_status)->toBe(OrderStatus::PickedUp)
        ->and($offer->fresh()->status)->toBe(RiderOfferStatus::Accepted)
        ->and($profile->total_offers_accepted)->toBe(1)
        ->and((float) $profile->acceptance_rate)->toBe(100.0);

    Queue::assertPushed(SendOrderNotificationJob::class);
});

test('record rider earning job is idempotent for a delivered order', function () {
    $rider = User::factory()->rider()->create();
    $profile = RiderProfile::factory()->for($rider, 'user')->approved()->create();
    $order = createDispatchReadyOrder([
        'rider_id' => $rider->getKey(),
        'order_status' => OrderStatus::Delivered,
        'payment_status' => PaymentStatus::Paid,
        'picked_up_at' => now()->subMinutes(35),
    ]);

    (new RecordRiderEarningJob($order->getKey()))->handle();
    (new RecordRiderEarningJob($order->getKey()))->handle();

    $profile->refresh();

    expect(RiderEarning::query()->whereBelongsTo($order)->count())->toBe(1)
        ->and((float) $profile->total_earnings)->toBeGreaterThan(0.0)
        ->and($profile->average_delivery_minutes)->not->toBeNull();
});
