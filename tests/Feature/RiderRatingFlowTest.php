<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RiderProfile;
use App\Models\RiderRating;
use App\Models\User;
use App\Models\VendorProfile;
use Livewire\Livewire;

test('customer can rate a delivered rider order once', function () {
    $customer = User::factory()->create();
    $rider = User::factory()->rider()->create();
    $profile = RiderProfile::factory()->for($rider, 'user')->approved()->create();
    $vendorUser = User::factory()->vendor()->create();
    $vendor = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();
    $order = Order::factory()
        ->for($customer, 'customer')
        ->for($vendor, 'vendor')
        ->create([
            'rider_id' => $rider->getKey(),
            'order_status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
        ]);
    Payment::factory()->for($order)->completed()->create([
        'amount' => $order->total_amount,
    ]);

    Livewire::actingAs($customer)
        ->test('rider.rate-rider', ['orderId' => $order->getKey()])
        ->set('rating', 5)
        ->set('comment', 'Careful and quick delivery.')
        ->call('submit')
        ->assertDispatched('rating-submitted');

    $profile->refresh();

    expect(RiderRating::query()->whereBelongsTo($order)->count())->toBe(1)
        ->and((float) $profile->rating)->toBe(5.0)
        ->and($profile->total_ratings)->toBe(1);

    Livewire::actingAs($customer)
        ->test('rider.rate-rider', ['orderId' => $order->getKey()])
        ->assertSee('You already rated this delivery');
});

test('customer order detail includes rider rating component after delivery', function () {
    $customer = User::factory()->create();
    $rider = User::factory()->rider()->create();
    RiderProfile::factory()->for($rider, 'user')->approved()->create();
    $vendorUser = User::factory()->vendor()->create();
    $vendor = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create([
        'store_name' => 'Rating Stall',
    ]);
    $order = Order::factory()
        ->for($customer, 'customer')
        ->for($vendor, 'vendor')
        ->create([
            'rider_id' => $rider->getKey(),
            'order_status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
        ]);
    Payment::factory()->for($order)->completed()->create([
        'amount' => $order->total_amount,
    ]);

    $this->actingAs($customer)
        ->get(route('shop.orders.show', ['orderReference' => $order->getKey()]))
        ->assertOk()
        ->assertSee('Rate your rider');
});
