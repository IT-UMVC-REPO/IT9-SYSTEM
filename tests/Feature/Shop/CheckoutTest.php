<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\PayMongoService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function seedCheckoutCart(User $customer, int $quantity = 1, array $productOverrides = []): array
{
    $vendor = $productOverrides['vendor'] ?? VendorProfile::factory()->approved()->create();
    $category = $productOverrides['category'] ?? Category::factory()->standalone()->create();

    unset($productOverrides['vendor'], $productOverrides['category']);

    $product = Product::factory()
        ->for($vendor, 'vendor')
        ->for($category)
        ->active()
        ->create(array_merge([
            'price' => 120,
            'stock_quantity' => 8,
        ], $productOverrides));

    $cart = Cart::factory()->for($customer, 'customer')->create();
    $cartItem = CartItem::query()->create([
        'cart_id' => $cart->getKey(),
        'product_id' => $product->getKey(),
        'quantity' => $quantity,
    ]);

    return compact('vendor', 'category', 'product', 'cart', 'cartItem');
}

test('empty cart redirects to the cart page', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('shop.checkout'))
        ->assertRedirect(route('shop.cart'));
});

test('checkout with cash on delivery creates order records, decrements stock, clears cart, and redirects to order detail', function () {
    Queue::fake();

    $customer = User::factory()->create([
        'address' => '123 Market Street, Davao City',
    ]);
    $seeded = seedCheckoutCart($customer, quantity: 2);

    $component = Livewire::actingAs($customer)
        ->test('pages::shop.checkout')
        ->set('delivery_address', '123 Market Street, Davao City')
        ->set('notes', 'Please call when outside the gate.')
        ->set('payment_method', 'cod');

    $component->call('placeOrder');

    $order = Order::query()->with(['orderItems', 'payment'])->first();

    $component->assertRedirect(route('shop.orders.show', ['orderReference' => $order->getKey()]));

    expect($order)->not->toBeNull();
    expect($order->customer_id)->toBe($customer->getKey());
    expect($order->vendor_id)->toBe($seeded['vendor']->getKey());
    expect((float) $order->total_amount)->toBe(240.0);
    expect($order->payment_method)->toBe(PaymentMethod::Cod);
    expect($order->payment_status)->toBe(PaymentStatus::Pending);
    expect($order->orderItems)->toHaveCount(1);
    expect($order->orderItems->first()->quantity)->toBe(2);
    expect((float) $order->orderItems->first()->unit_price)->toBe(120.0);
    expect((float) $seeded['product']->fresh()->stock_quantity)->toBe(6.0);
    expect($seeded['cart']->fresh()->cartItems()->count())->toBe(0);
    expect($order->payment)->not->toBeNull();
    expect($order->payment->method)->toBe(PaymentMethod::Cod);

    Queue::assertPushed(SendOrderNotificationJob::class, fn (SendOrderNotificationJob $job) => $job->orderId === $order->getKey());
});

test('delivery address is required at checkout', function () {
    $customer = User::factory()->create();
    seedCheckoutCart($customer);

    Livewire::actingAs($customer)
        ->test('pages::shop.checkout')
        ->set('delivery_address', '')
        ->call('placeOrder')
        ->assertHasErrors(['delivery_address' => ['required']]);
});

test('checkout validates stock before creating an order', function () {
    $customer = User::factory()->create();
    $seeded = seedCheckoutCart($customer, quantity: 3, productOverrides: [
        'stock_quantity' => 2,
    ]);

    Livewire::actingAs($customer)
        ->test('pages::shop.checkout')
        ->set('delivery_address', '456 Palengke Road')
        ->call('placeOrder')
        ->assertHasErrors(["cart.{$seeded['cartItem']->getKey()}"]);

    expect(Order::query()->count())->toBe(0);
});

test('gcash checkout creates a paymongo source and redirects away', function () {
    $customer = User::factory()->create([
        'address' => '789 Riverside Street',
    ]);
    seedCheckoutCart($customer, quantity: 2);

    app()->instance(PayMongoService::class, new class extends PayMongoService
    {
        public function createGCashSource(
            int $amountInCentavos,
            string $currency = 'PHP',
            string $description = '',
            string $successUrl = '',
            string $failedUrl = '',
        ): array {
            expect($amountInCentavos)->toBe(24000);
            expect($currency)->toBe('PHP');

            return [
                'data' => [
                    'id' => 'src_test_123',
                    'attributes' => [
                        'redirect' => [
                            'checkout_url' => 'https://checkout.paymongo.test/src_test_123',
                        ],
                    ],
                ],
            ];
        }
    });

    Livewire::actingAs($customer)
        ->test('pages::shop.checkout')
        ->set('delivery_address', '789 Riverside Street')
        ->set('payment_method', 'gcash')
        ->call('placeOrder')
        ->assertRedirect('https://checkout.paymongo.test/src_test_123');

    $payment = Payment::query()->first();

    expect($payment)->not->toBeNull();
    expect($payment->method)->toBe(PaymentMethod::Gcash);
    expect($payment->reference_number)->toBe('src_test_123');
    expect(session('pending_payment_order_id'))->toBe($payment->order_id);
});

test('failed paymongo source creation restores the cart and removes the provisional order', function () {
    $customer = User::factory()->create([
        'address' => '101 Agdao Road',
    ]);
    $seeded = seedCheckoutCart($customer, quantity: 2);

    app()->instance(PayMongoService::class, new class extends PayMongoService
    {
        public function createGCashSource(
            int $amountInCentavos,
            string $currency = 'PHP',
            string $description = '',
            string $successUrl = '',
            string $failedUrl = '',
        ): array {
            throw new RuntimeException('PayMongo unavailable.');
        }
    });

    Livewire::actingAs($customer)
        ->test('pages::shop.checkout')
        ->set('delivery_address', '101 Agdao Road')
        ->set('payment_method', 'gcash')
        ->call('placeOrder');

    expect(Order::query()->count())->toBe(0);
    expect(Payment::query()->count())->toBe(0);
    expect($seeded['cart']->fresh()->cartItems()->count())->toBe(1);
    expect($seeded['cart']->fresh()->cartItems()->first()->quantity)->toBe(2);
    expect((float) $seeded['product']->fresh()->stock_quantity)->toBe(8.0);
});

test('paymongo webhook rejects an invalid signature', function () {
    config()->set('services.paymongo.webhook_secret', 'whsec_test_123');

    $payload = json_encode([
        'data' => [
            'id' => 'src_invalid',
            'attributes' => ['type' => 'source.chargeable'],
        ],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', route('webhooks.paymongo'), [], [], [], [
        'HTTP_Paymongo-Signature' => 'invalid-signature',
    ], $payload)->assertStatus(400);
});

test('paymongo webhook marks the payment as paid and dispatches an order notification job', function () {
    Queue::fake();
    Http::fake([
        'https://api.paymongo.test/*' => Http::response([
            'data' => ['id' => 'pay_test_123'],
        ], 200),
    ]);

    config()->set('services.paymongo.base_url', 'https://api.paymongo.test');
    config()->set('services.paymongo.secret_key', 'sk_test_123');
    config()->set('services.paymongo.webhook_secret', 'whsec_test_123');

    $customer = User::factory()->create();
    $order = Order::factory()->for($customer, 'customer')->create([
        'payment_method' => PaymentMethod::Gcash,
        'payment_status' => PaymentStatus::Pending,
    ]);
    $payment = Payment::factory()->for($order)->create([
        'method' => PaymentMethod::Gcash,
        'reference_number' => 'src_test_123',
        'status' => PaymentStatus::Pending,
        'amount' => 240,
    ]);

    $payload = json_encode([
        'data' => [
            'id' => 'src_test_123',
            'attributes' => ['type' => 'source.chargeable'],
        ],
    ], JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $payload, 'whsec_test_123');

    $this->call('POST', route('webhooks.paymongo'), [], [], [], [
        'HTTP_Paymongo-Signature' => $signature,
    ], $payload)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);

    Queue::assertPushed(SendOrderNotificationJob::class, fn (SendOrderNotificationJob $job) => $job->orderId === $order->getKey() && $job->broadcastOrderStatus === true);
});

test('paymongo webhook accepts v1 signature from composite header format', function () {
    Queue::fake();
    Http::fake([
        'https://api.paymongo.test/*' => Http::response([
            'data' => ['id' => 'pay_test_123'],
        ], 200),
    ]);

    config()->set('services.paymongo.base_url', 'https://api.paymongo.test');
    config()->set('services.paymongo.secret_key', 'sk_test_123');
    config()->set('services.paymongo.webhook_secret', 'whsec_test_123');

    $customer = User::factory()->create();
    $order = Order::factory()->for($customer, 'customer')->create([
        'payment_method' => PaymentMethod::Gcash,
        'payment_status' => PaymentStatus::Pending,
    ]);
    $payment = Payment::factory()->for($order)->create([
        'method' => PaymentMethod::Gcash,
        'reference_number' => 'src_composite_123',
        'status' => PaymentStatus::Pending,
        'amount' => 240,
    ]);

    $payload = json_encode([
        'data' => [
            'id' => 'src_composite_123',
            'attributes' => ['type' => 'source.chargeable'],
        ],
    ], JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $payload, 'whsec_test_123');

    $this->call('POST', route('webhooks.paymongo'), [], [], [], [
        'HTTP_Paymongo-Signature' => 't=1714137600,v1='.$signature,
    ], $payload)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('payment success return page renders the placed order', function () {
    $order = Order::factory()->create();

    $this->withSession(['pending_payment_order_id' => $order->getKey()])
        ->get(route('shop.payment.success'))
        ->assertOk()
        ->assertSee('Order placed!')
        ->assertSee('#'.str_pad((string) $order->getKey(), 6, '0', STR_PAD_LEFT))
        ->assertSessionMissing('pending_payment_order_id');
});

test('payment failure return page renders a retry path', function () {
    $this->withSession(['pending_payment_order_id' => 42])
        ->get(route('shop.payment.failed'))
        ->assertOk()
        ->assertSee('Payment was not completed')
        ->assertSee('Try again')
        ->assertSessionMissing('pending_payment_order_id');
});
