<?php

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Events\NotificationCreated;
use App\Events\RiderLocationUpdated;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RiderProfile;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function createReadyRiderOrder(array $overrides = []): Order
{
    $customer = User::factory()->create([
        'name' => 'Customer Lina',
        'address' => 'Customer Home, Tagum City',
        'lat' => 7.449000,
        'lng' => 125.810000,
    ]);

    $vendorUser = User::factory()->vendor()->create();
    $vendor = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create([
        'store_name' => 'Nanay Cora Greens',
        'vendor_address' => 'Public Market Stall 8, Tagum City',
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

test('customer can apply for rider access and admin can approve the profile', function () {
    Event::fake([NotificationCreated::class]);

    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create(['name' => 'Juan Rider']);

    Livewire::actingAs($customer)
        ->test('pages::rider.registration')
        ->set('vehicle_type', 'motorcycle')
        ->set('plate_number', 'ABC-1234')
        ->set('contact_number', '+63 912 345 6789')
        ->call('submit')
        ->assertRedirect(route('rider.registration'));

    $profile = RiderProfile::query()->whereBelongsTo($customer)->first();

    expect($profile)->not->toBeNull()
        ->and($profile->status)->toBe('pending')
        ->and($profile->vehicle_type)->toBe('motorcycle')
        ->and($profile->plate_number)->toBe('ABC-1234')
        ->and($profile->contact_number)->toBe('+63 912 345 6789');

    expect(Notification::query()
        ->where('user_id', $admin->getKey())
        ->where('type', NotificationType::System)
        ->where('title', 'New rider application')
        ->exists())->toBeTrue();

    Livewire::actingAs($admin)
        ->test('pages::admin.riders')
        ->call('approve', $profile->getKey());

    $customer->refresh();
    $profile->refresh();

    expect($customer->role)->toBe(UserRole::Rider)
        ->and($customer->homeRoute())->toBe('rider.dashboard')
        ->and($profile->status)->toBe('approved')
        ->and($profile->approved_at)->not->toBeNull();
});

test('rider can claim a ready order and complete the delivery flow', function () {
    Queue::fake([SendOrderNotificationJob::class]);

    $rider = User::factory()->rider()->create();
    RiderProfile::factory()->for($rider, 'user')->approved()->create();
    $order = createReadyRiderOrder();

    $component = Livewire::actingAs($rider)
        ->test('pages::rider.dashboard');

    $component->call('claimOrder', $order->getKey());

    $order->refresh();

    expect($order->rider_id)->toBe($rider->getKey())
        ->and($order->order_status)->toBe(OrderStatus::PickedUp)
        ->and($order->picked_up_at)->not->toBeNull();

    $component->call('markOutForDelivery', $order->getKey());

    $order->refresh();

    expect($order->order_status)->toBe(OrderStatus::OutForDelivery)
        ->and($order->out_for_delivery_at)->not->toBeNull();

    $component->call('markDelivered', $order->getKey());

    $order->refresh();

    expect($order->order_status)->toBe(OrderStatus::Delivered)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->payment?->status)->toBe(PaymentStatus::Paid)
        ->and($order->payment?->paid_at)->not->toBeNull();

    Queue::assertPushed(SendOrderNotificationJob::class, 3);
});

test('rider dashboard map follows the rider to pickup before delivery', function () {
    $rider = User::factory()->rider()->create();
    RiderProfile::factory()->for($rider, 'user')->approved()->create();
    $order = createReadyRiderOrder([
        'rider_id' => $rider->getKey(),
        'order_status' => OrderStatus::PickedUp,
        'rider_lat' => 7.4512345,
        'rider_lng' => 125.8123456,
    ]);

    $response = $this->actingAs($rider)
        ->get(route('rider.dashboard'))
        ->assertOk()
        ->assertSee('Delivery route')
        ->assertSee('Message customer')
        ->assertSee('rider-location-updated', false)
        ->assertSee('liveRider: true', false)
        ->assertSee('routeMode:', false)
        ->assertSee('pickup', false)
        ->assertSee(route('messages.conversation', ['conversationReference' => $order->customer_id, 'order' => $order->id]), false);

    expect($response->getContent())->toContain('riderPoint:');
});

test('rider delivery detail map switches to the customer route when out for delivery', function () {
    $rider = User::factory()->rider()->create();
    RiderProfile::factory()->for($rider, 'user')->approved()->create();
    $order = createReadyRiderOrder([
        'rider_id' => $rider->getKey(),
        'order_status' => OrderStatus::OutForDelivery,
        'rider_lat' => 7.4512345,
        'rider_lng' => 125.8123456,
    ]);

    $this->actingAs($rider)
        ->get(route('rider.deliveries.show', ['orderReference' => $order->getKey()]))
        ->assertOk()
        ->assertSee('Delivery route')
        ->assertSee('rider-location-updated', false)
        ->assertSee('liveRider: true', false)
        ->assertSee('routeMode:', false)
        ->assertSee('dropoff', false)
        ->assertSee('Message customer');
});

test('rider location endpoint updates the rider profile and assigned active order', function () {
    Event::fake([RiderLocationUpdated::class]);

    $rider = User::factory()->rider()->create();
    $profile = RiderProfile::factory()->for($rider, 'user')->approved()->create();
    $order = createReadyRiderOrder([
        'rider_id' => $rider->getKey(),
        'order_status' => OrderStatus::OutForDelivery,
    ]);

    $this->actingAs($rider)
        ->postJson(route('rider.location.update'), [
            'lat' => 7.4512345,
            'lng' => 125.8123456,
            'order_id' => $order->getKey(),
        ])
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    $profile->refresh();
    $order->refresh();

    expect($profile->current_lat)->toBe(7.4512345)
        ->and($profile->current_lng)->toBe(125.8123456)
        ->and((float) $order->rider_lat)->toBe(7.4512345)
        ->and((float) $order->rider_lng)->toBe(125.8123456);

    Event::assertDispatched(RiderLocationUpdated::class, fn (RiderLocationUpdated $event) => $event->orderId === $order->getKey()
        && $event->lat === 7.4512345
        && $event->lng === 125.8123456);
});

test('non rider users cannot post rider locations', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->postJson(route('rider.location.update'), [
            'lat' => 7.4512345,
            'lng' => 125.8123456,
        ])
        ->assertForbidden();
});

test('customer order detail shows live rider tracking for active delivery coordinates', function () {
    $rider = User::factory()->rider()->create();
    RiderProfile::factory()->for($rider, 'user')->approved()->create();
    $order = createReadyRiderOrder([
        'rider_id' => $rider->getKey(),
        'order_status' => OrderStatus::OutForDelivery,
        'rider_lat' => 7.4512345,
        'rider_lng' => 125.8123456,
    ]);

    $this->actingAs($order->customer)
        ->get(route('shop.orders.show', ['orderReference' => $order->getKey()]))
        ->assertOk()
        ->assertSee('Live tracking')
        ->assertSee('unified-order-map', false);
});
