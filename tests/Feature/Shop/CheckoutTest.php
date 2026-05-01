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
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function makeCheckoutProduct(array $overrides = []): Product
{
    $vendor = $overrides['vendor'] ?? VendorProfile::factory()->approved()->create();
    $category = $overrides['category'] ?? Category::factory()->standalone()->create();

    unset($overrides['vendor'], $overrides['category']);

    return Product::factory()
        ->for($vendor, 'vendor')
        ->for($category)
        ->active()
        ->create(array_merge([
            'price' => 120,
            'stock_quantity' => 8,
        ], $overrides));
}

function addCheckoutItem(Cart $cart, int $quantity = 1, array $productOverrides = []): array
{
    $product = makeCheckoutProduct($productOverrides);
    $cartItem = CartItem::query()->create([
        'cart_id' => $cart->getKey(),
        'product_id' => $product->getKey(),
        'quantity' => $quantity,
    ]);

    return compact('product', 'cartItem');
}

function seedCheckoutCart(User $customer, int $quantity = 1, array $productOverrides = []): array
{
    $cart = Cart::factory()->for($customer, 'customer')->create();

    return array_merge(
        ['cart' => $cart],
        addCheckoutItem($cart, $quantity, $productOverrides),
    );
}

test('empty cart redirects to the cart page', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('shop.checkout'))
        ->assertRedirect(route('shop.cart'));
});

test('checkout with cash on delivery creates order records, decrements stock, clears cart, and redirects to orders list', function () {
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

    $order = Order::query()->with(['orderItems', 'payment'])->sole();

    $component->assertRedirect(route('shop.orders'));

    expect($order->customer_id)->toBe($customer->getKey());
    expect($order->vendor_id)->toBe($seeded['product']->vendor_id);
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

    Queue::assertPushed(SendOrderNotificationJob::class, 1);
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

test('multi vendor cod checkout creates one order per vendor, decrements stock, and clears all cart items', function () {
    Queue::fake();

    $customer = User::factory()->create([
        'address' => '88 Bankerohan Road, Davao City',
    ]);
    $cart = Cart::factory()->for($customer, 'customer')->create();
    $firstVendor = VendorProfile::factory()->approved()->create([
        'store_name' => 'Lina Greens',
    ]);
    $secondVendor = VendorProfile::factory()->approved()->create([
        'store_name' => 'Ben Fresh Catch',
    ]);

    $firstSeeded = addCheckoutItem($cart, quantity: 2, productOverrides: [
        'vendor' => $firstVendor,
        'price' => 120,
        'stock_quantity' => 8,
    ]);
    $secondSeeded = addCheckoutItem($cart, quantity: 1, productOverrides: [
        'vendor' => $secondVendor,
        'price' => 75,
        'stock_quantity' => 5,
    ]);

    $component = Livewire::actingAs($customer)
        ->test('pages::shop.checkout')
        ->set('delivery_address', '88 Bankerohan Road, Davao City')
        ->set('notes', 'Leave by the front gate.')
        ->set('payment_method', 'cod');

    $component->call('placeOrder');

    $orders = Order::query()->with(['orderItems', 'payment'])->orderBy('vendor_id')->get();

    $component->assertRedirect(route('shop.orders'));

    expect($orders)->toHaveCount(2);
    expect($orders->pluck('vendor_id')->all())->toBe([
        $firstVendor->getKey(),
        $secondVendor->getKey(),
    ]);
    expect($orders->pluck('payment_method')->all())->toBe([
        PaymentMethod::Cod,
        PaymentMethod::Cod,
    ]);
    expect($orders->pluck('payment_status')->all())->toBe([
        PaymentStatus::Pending,
        PaymentStatus::Pending,
    ]);
    expect($orders->pluck('total_amount')->map(fn ($amount) => (float) $amount)->all())->toBe([
        240.0,
        75.0,
    ]);
    expect($orders->pluck('orderItems')->map->sum('quantity')->all())->toBe([2, 1]);
    expect(Payment::query()->count())->toBe(2);
    expect($cart->fresh()->cartItems()->count())->toBe(0);
    expect((float) $firstSeeded['product']->fresh()->stock_quantity)->toBe(6.0);
    expect((float) $secondSeeded['product']->fresh()->stock_quantity)->toBe(4.0);

    Queue::assertPushed(SendOrderNotificationJob::class, 2);
});

test('checkout only renders cash on delivery as a payment option', function () {
    $customer = User::factory()->create([
        'address' => '11 Victoria Plaza, Davao City',
    ]);
    seedCheckoutCart($customer, quantity: 1);

    $this->actingAs($customer)
        ->get(route('shop.checkout'))
        ->assertOk()
        ->assertSee('Cash on Delivery')
        ->assertSee('Cash on Delivery confirmed')
        ->assertDontSee('Digital payments')
        ->assertDontSee('Payment service');
});

test('checkout rejects non cod payment method payloads', function () {
    $customer = User::factory()->create([
        'address' => '11 Victoria Plaza, Davao City',
    ]);
    $cart = Cart::factory()->for($customer, 'customer')->create();

    addCheckoutItem($cart);

    Livewire::actingAs($customer)
        ->test('pages::shop.checkout')
        ->set('delivery_address', '11 Victoria Plaza, Davao City')
        ->set('payment_method', 'digital')
        ->call('placeOrder')
        ->assertHasErrors(['payment_method']);

    expect(Order::query()->count())->toBe(0);
    expect(Payment::query()->count())->toBe(0);
});
