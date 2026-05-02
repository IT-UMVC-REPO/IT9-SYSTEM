<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderStatusUpdated;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function createVendorManagedOrder(VendorProfile $vendor, array $orderOverrides = [], array $paymentOverrides = []): array
{
    $customer = User::factory()->create();
    $category = Category::factory()->standalone()->create();
    $product = Product::factory()
        ->for($vendor, 'vendor')
        ->for($category)
        ->active()
        ->create([
            'name' => 'Market Fresh Tilapia',
            'price' => 150,
        ]);

    $order = Order::factory()
        ->for($customer, 'customer')
        ->for($vendor, 'vendor')
        ->create(array_merge([
            'total_amount' => 300,
            'order_status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
        ], $orderOverrides));

    OrderItem::query()->create([
        'order_id' => $order->getKey(),
        'product_id' => $product->getKey(),
        'quantity' => 2,
        'unit_price' => 150,
    ]);

    $payment = Payment::factory()->for($order)->create(array_merge([
        'status' => $order->payment_status,
        'amount' => $order->total_amount,
        'paid_at' => $order->payment_status === PaymentStatus::Paid ? now() : null,
    ], $paymentOverrides));

    return compact('customer', 'order', 'payment', 'product');
}

test('vendor can view their orders', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();

    $own = createVendorManagedOrder($vendorProfile);

    $otherVendorUser = User::factory()->vendor()->create();
    $otherVendorProfile = VendorProfile::factory()->for($otherVendorUser, 'user')->approved()->create();
    $other = createVendorManagedOrder($otherVendorProfile);

    $this->actingAs($vendorUser)
        ->get(route('vendor.orders'))
        ->assertOk()
        ->assertSee($own['customer']->name)
        ->assertDontSee($other['customer']->name);
});

test('vendor cannot view another vendors orders', function () {
    $vendorUser = User::factory()->vendor()->create();
    VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();

    $otherVendorUser = User::factory()->vendor()->create();
    $otherVendorProfile = VendorProfile::factory()->for($otherVendorUser, 'user')->approved()->create();
    $other = createVendorManagedOrder($otherVendorProfile);

    $this->actingAs($vendorUser)
        ->get(route('vendor.orders.show', ['orderReference' => $other['order']->getKey()]))
        ->assertNotFound();
});

test('vendor order detail shows the back link and peso totals', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();
    $tracked = createVendorManagedOrder($vendorProfile);

    $this->actingAs($vendorUser)
        ->get(route('vendor.orders.show', ['orderReference' => $tracked['order']->getKey()]))
        ->assertOk()
        ->assertSee('Back to order queue')
        ->assertSee("\u{20B1}300.00")
        ->assertSee("\u{20B1}150.00 each");
});

test('status advances correctly from pending to delivered', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();
    $tracked = createVendorManagedOrder($vendorProfile);

    $component = Livewire::actingAs($vendorUser)
        ->test('pages::vendor.order-detail', ['orderReference' => (string) $tracked['order']->getKey()]);

    $component->call('advanceStatus');
    expect($tracked['order']->fresh()->order_status)->toBe(OrderStatus::Confirmed);

    $component->call('advanceStatus');
    expect($tracked['order']->fresh()->order_status)->toBe(OrderStatus::Preparing);

    $component->call('advanceStatus');
    expect($tracked['order']->fresh()->order_status)->toBe(OrderStatus::Ready);

    $component->call('advanceStatus');
    expect($tracked['order']->fresh()->order_status)->toBe(OrderStatus::Delivered);
    expect($tracked['order']->fresh()->payment_status)->toBe(PaymentStatus::Paid);
    expect($tracked['payment']->fresh()->status)->toBe(PaymentStatus::Paid);
});

test('cannot advance past delivered', function () {
    Queue::fake();

    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();
    $tracked = createVendorManagedOrder($vendorProfile, [
        'order_status' => OrderStatus::Delivered,
        'payment_status' => PaymentStatus::Paid,
    ], [
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.order-detail', ['orderReference' => (string) $tracked['order']->getKey()])
        ->call('advanceStatus');

    expect($tracked['order']->fresh()->order_status)->toBe(OrderStatus::Delivered);
    Queue::assertNothingPushed();
});

test('customer notification is dispatched on status change', function () {
    Queue::fake();

    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();
    $tracked = createVendorManagedOrder($vendorProfile);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.order-detail', ['orderReference' => (string) $tracked['order']->getKey()])
        ->call('advanceStatus');

    Queue::assertPushed(SendOrderNotificationJob::class, function (SendOrderNotificationJob $job) use ($tracked) {
        return $job->orderId === $tracked['order']->getKey()
            && $job->userId === $tracked['customer']->getKey()
            && $job->broadcastOrderStatus === true;
    });
});

test('vendor can save estimated delivery for confirmed or preparing orders', function () {
    Event::fake([OrderStatusUpdated::class]);

    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();
    $tracked = createVendorManagedOrder($vendorProfile, [
        'order_status' => OrderStatus::Confirmed,
    ]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.order-detail', ['orderReference' => (string) $tracked['order']->getKey()])
        ->set('estimated_delivery_at', '2026-05-03T14:30')
        ->set('delay_note', 'Delayed due to weather')
        ->call('saveDeliveryEstimate')
        ->assertHasNoErrors();

    $order = $tracked['order']->fresh();

    expect($order->estimated_delivery_at?->format('Y-m-d H:i'))->toBe('2026-05-03 14:30')
        ->and($order->delay_note)->toBe('Delayed due to weather');

    Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event) => $event->order->is($order));
});

test('cancel works for pending orders', function () {
    Queue::fake();

    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();
    $tracked = createVendorManagedOrder($vendorProfile, [
        'order_status' => OrderStatus::Pending,
    ]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.order-detail', ['orderReference' => (string) $tracked['order']->getKey()])
        ->call('cancelOrder');

    expect($tracked['order']->fresh()->order_status)->toBe(OrderStatus::Cancelled);

    Queue::assertPushed(SendOrderNotificationJob::class);
});

test('cancel does not work for later order stages', function () {
    Queue::fake();

    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();
    $tracked = createVendorManagedOrder($vendorProfile, [
        'order_status' => OrderStatus::Preparing,
    ]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.order-detail', ['orderReference' => (string) $tracked['order']->getKey()])
        ->call('cancelOrder');

    expect($tracked['order']->fresh()->order_status)->toBe(OrderStatus::Preparing);
    Queue::assertNothingPushed();
});

test('cancel does not work once the order is confirmed', function () {
    Queue::fake();

    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();
    $tracked = createVendorManagedOrder($vendorProfile, [
        'order_status' => OrderStatus::Confirmed,
    ]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.order-detail', ['orderReference' => (string) $tracked['order']->getKey()])
        ->call('cancelOrder');

    expect($tracked['order']->fresh()->order_status)->toBe(OrderStatus::Confirmed);
    Queue::assertNothingPushed();
});
