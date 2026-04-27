<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function seedTrackedOrder(User $customer, array $orderOverrides = [], array $paymentOverrides = []): array
{
    $vendor = VendorProfile::factory()->approved()->create();
    $category = Category::factory()->standalone()->create([
        'name' => 'Vegetables',
        'slug' => fake()->unique()->slug(),
    ]);

    $order = Order::factory()
        ->for($customer, 'customer')
        ->for($vendor, 'vendor')
        ->create(array_merge([
            'total_amount' => 255,
            'payment_method' => PaymentMethod::Cod,
            'payment_status' => PaymentStatus::Pending,
            'order_status' => OrderStatus::Pending,
            'delivery_address' => 'Poblacion Market Lane, Davao City',
            'notes' => 'Please leave at the guard house.',
        ], $orderOverrides));

    $products = Product::factory()
        ->count(2)
        ->for($vendor, 'vendor')
        ->for($category)
        ->active()
        ->sequence(
            ['name' => 'Fresh Talong', 'price' => 75],
            ['name' => 'Pechay Bundle', 'price' => 105],
        )
        ->create();

    OrderItem::query()->create([
        'order_id' => $order->getKey(),
        'product_id' => $products[0]->getKey(),
        'quantity' => 2,
        'unit_price' => 75,
    ]);

    OrderItem::query()->create([
        'order_id' => $order->getKey(),
        'product_id' => $products[1]->getKey(),
        'quantity' => 1,
        'unit_price' => 105,
    ]);

    $payment = Payment::factory()
        ->for($order)
        ->create(array_merge([
            'method' => $order->payment_method,
            'status' => $order->payment_status,
            'amount' => $order->total_amount,
        ], $paymentOverrides));

    return compact('vendor', 'category', 'order', 'products', 'payment');
}

test('customer can view their orders', function () {
    $customer = User::factory()->create();
    $own = seedTrackedOrder($customer);
    $other = seedTrackedOrder(User::factory()->create());

    $this->actingAs($customer)
        ->get(route('shop.orders'))
        ->assertOk()
        ->assertSee($own['vendor']->store_name)
        ->assertSee('Order #'.str_pad((string) $own['order']->getKey(), 6, '0', STR_PAD_LEFT))
        ->assertDontSee($other['vendor']->store_name);
});

test('customer cannot view another customer order', function () {
    $customer = User::factory()->create();
    $tracked = seedTrackedOrder(User::factory()->create());

    $this->actingAs($customer)
        ->get(route('shop.orders.show', ['orderReference' => $tracked['order']->getKey()]))
        ->assertForbidden();
});

test('order detail shows correct line items and totals', function () {
    $customer = User::factory()->create();
    $tracked = seedTrackedOrder($customer, [
        'total_amount' => 255,
    ]);

    $this->actingAs($customer)
        ->get(route('shop.orders.show', ['orderReference' => $tracked['order']->getKey()]))
        ->assertOk()
        ->assertSee('Fresh Talong')
        ->assertSee('Pechay Bundle')
        ->assertSee("\u{20B1}255.00")
        ->assertSee('Back to orders')
        ->assertSee('Poblacion Market Lane, Davao City')
        ->assertSee('Please leave at the guard house.')
        ->assertSee('View full message history in your inbox');
});

test('cancel order changes the status and dispatches a vendor notification job', function () {
    Queue::fake();

    $customer = User::factory()->create();
    $tracked = seedTrackedOrder($customer);

    Livewire::actingAs($customer)
        ->test('pages::shop.order-detail', ['orderReference' => (string) $tracked['order']->getKey()])
        ->call('cancelOrder');

    expect($tracked['order']->fresh()->order_status)->toBe(OrderStatus::Cancelled);
    expect($tracked['order']->fresh()->payment_status)->toBe(PaymentStatus::Failed);
    expect($tracked['payment']->fresh()->status)->toBe(PaymentStatus::Failed);

    Queue::assertPushed(SendOrderNotificationJob::class, function (SendOrderNotificationJob $job) use ($tracked) {
        return $job->orderId === $tracked['order']->getKey()
            && $job->userId === $tracked['vendor']->user_id
            && $job->broadcastOrderStatus === true;
    });
});

test('cancelled order does not allow further cancellation', function () {
    Queue::fake();

    $customer = User::factory()->create();
    $tracked = seedTrackedOrder($customer, [
        'order_status' => OrderStatus::Cancelled,
        'payment_status' => PaymentStatus::Failed,
    ], [
        'status' => PaymentStatus::Failed,
    ]);

    Livewire::actingAs($customer)
        ->test('pages::shop.order-detail', ['orderReference' => (string) $tracked['order']->getKey()])
        ->call('cancelOrder');

    Queue::assertNothingPushed();
    expect($tracked['order']->fresh()->order_status)->toBe(OrderStatus::Cancelled);
});
