<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductUnit;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductUnitVariant;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function makeVariantCheckoutProduct(): array
{
    $vendor = VendorProfile::factory()->approved()->create();
    $category = Category::factory()->standalone()->create();
    $product = Product::factory()
        ->for($vendor, 'vendor')
        ->for($category)
        ->active()
        ->create([
            'price' => 10,
            'stock_quantity' => 30,
            'unit' => ProductUnit::Piece,
            'canonical_stock_unit' => null,
            'canonical_stock_quantity' => null,
        ]);

    $piece = ProductUnitVariant::factory()->default()->for($product)->create([
        'unit' => ProductUnit::Piece,
        'price' => 10,
        'stock_quantity' => 30,
    ]);

    $dozen = ProductUnitVariant::factory()->for($product)->create([
        'unit' => ProductUnit::Dozen,
        'price' => 100,
        'stock_quantity' => 5,
        'conversion_unit' => ProductUnit::Piece,
        'conversion_unit_quantity' => 12,
        'sort_order' => 1,
    ]);

    return compact('product', 'piece', 'dozen');
}

test('cart stores the selected variant and checkout snapshots variant price and unit', function (): void {
    Queue::fake();

    $customer = User::factory()->create([
        'address' => 'Tagum City Public Market',
    ]);
    $seeded = makeVariantCheckoutProduct();

    Livewire::actingAs($customer)
        ->test('cart.add-to-cart', ['product' => $seeded['product']])
        ->set('selectedVariantId', $seeded['dozen']->getKey())
        ->set('quantity', '2')
        ->call('addToCart')
        ->assertDispatched('cart-updated');

    $cart = Cart::query()->where('customer_id', $customer->getKey())->firstOrFail();
    $cartItem = $cart->cartItems()->sole();

    expect($cartItem->product_unit_variant_id)->toBe($seeded['dozen']->getKey())
        ->and($cartItem->quantity)->toBe(2);

    Livewire::actingAs($customer)
        ->test('pages::shop.checkout')
        ->set('delivery_address', 'Tagum City Public Market')
        ->set('payment_method', 'cod')
        ->call('placeOrder')
        ->assertHasNoErrors()
        ->assertRedirect(route('shop.orders'));

    $order = Order::query()->with('orderItems.unitVariant')->sole();
    $orderItem = $order->orderItems->first();

    expect((float) $order->total_amount)->toBe(200.0)
        ->and($orderItem->product_unit_variant_id)->toBe($seeded['dozen']->getKey())
        ->and($orderItem->unit)->toBe(ProductUnit::Dozen)
        ->and((float) $orderItem->unit_price)->toBe(100.0)
        ->and($seeded['dozen']->fresh()->stock_quantity)->toBe(3)
        ->and($seeded['piece']->fresh()->stock_quantity)->toBe(30);

    Queue::assertPushed(SendOrderNotificationJob::class);
});

test('cancelling an order restocks the selected count variant', function (): void {
    Queue::fake();

    $customer = User::factory()->create([
        'address' => 'Tagum City Public Market',
    ]);
    $seeded = makeVariantCheckoutProduct();
    $cart = Cart::factory()->for($customer, 'customer')->create();
    $cart->cartItems()->create([
        'product_id' => $seeded['product']->getKey(),
        'product_unit_variant_id' => $seeded['dozen']->getKey(),
        'quantity' => 2,
    ]);

    Livewire::actingAs($customer)
        ->test('pages::shop.checkout')
        ->set('delivery_address', 'Tagum City Public Market')
        ->set('payment_method', 'cod')
        ->call('placeOrder')
        ->assertHasNoErrors();

    $order = Order::query()->sole();

    expect($seeded['dozen']->fresh()->stock_quantity)->toBe(3);

    Livewire::actingAs($customer)
        ->test('pages::shop.order-detail', ['orderReference' => (string) $order->getKey()])
        ->call('cancelOrder');

    expect($order->fresh()->order_status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Failed)
        ->and($seeded['dozen']->fresh()->stock_quantity)->toBe(5)
        ->and($seeded['piece']->fresh()->stock_quantity)->toBe(30);
});
