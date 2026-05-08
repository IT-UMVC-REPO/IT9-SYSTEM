<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use Livewire\Livewire;

function makeCartProduct(array $overrides = []): Product
{
    $vendorProfile = $overrides['vendor'] ?? VendorProfile::factory()->approved()->create();
    $category = $overrides['category'] ?? Category::factory()->standalone()->create();

    unset($overrides['vendor'], $overrides['category']);

    return Product::factory()
        ->for($vendorProfile, 'vendor')
        ->for($category)
        ->active()
        ->create($overrides);
}

test('adding to cart creates a cart item', function () {
    $customer = User::factory()->create();
    $product = makeCartProduct([
        'stock_quantity' => 8,
    ]);

    Livewire::actingAs($customer)
        ->test('cart.add-to-cart', ['product' => $product])
        ->set('quantity', '2')
        ->call('addToCart')
        ->assertDispatched('cart-updated');

    $cart = Cart::query()->where('customer_id', $customer->getKey())->first();

    expect($cart)->not->toBeNull();
    expect($cart->cartItems()->where('product_id', $product->getKey())->first()->quantity)->toBe(2);
});

test('adding the same product again increases quantity and caps at stock', function () {
    $customer = User::factory()->create();
    $product = makeCartProduct([
        'stock_quantity' => 5,
    ]);

    $cart = Cart::factory()->for($customer, 'customer')->create();
    CartItem::query()->create([
        'cart_id' => $cart->getKey(),
        'product_id' => $product->getKey(),
        'quantity' => 4,
    ]);

    Livewire::actingAs($customer)
        ->test('cart.add-to-cart', ['product' => $product])
        ->set('quantity', '3')
        ->call('addToCart')
        ->assertDispatched('cart-updated');

    expect(
        CartItem::query()
            ->where('cart_id', $cart->getKey())
            ->where('product_id', $product->getKey())
            ->value('quantity')
    )->toBe(5);
});

test('adding items from multiple vendors to the cart is allowed', function () {
    $customer = User::factory()->create();
    $firstVendor = VendorProfile::factory()->approved()->create([
        'store_name' => 'Nanay Lina Greens',
    ]);
    $secondVendor = VendorProfile::factory()->approved()->create([
        'store_name' => 'Kuya Ben Seafood',
    ]);
    $firstProduct = makeCartProduct([
        'vendor' => $firstVendor,
        'stock_quantity' => 6,
    ]);
    $secondProduct = makeCartProduct([
        'vendor' => $secondVendor,
        'stock_quantity' => 6,
    ]);

    $cart = Cart::factory()->for($customer, 'customer')->create();
    CartItem::query()->create([
        'cart_id' => $cart->getKey(),
        'product_id' => $firstProduct->getKey(),
        'quantity' => 2,
    ]);

    Livewire::actingAs($customer)
        ->test('cart.add-to-cart', ['product' => $secondProduct])
        ->set('quantity', '3')
        ->call('addToCart')
        ->assertDispatched('cart-updated');

    $cartItems = $cart->fresh()->cartItems()->orderBy('id')->get();

    expect($cartItems)->toHaveCount(2);
    expect($cartItems->pluck('product_id')->all())->toBe([
        $firstProduct->getKey(),
        $secondProduct->getKey(),
    ]);
    expect($cartItems->pluck('quantity')->all())->toBe([2, 3]);
});

test('customers can remove items from the cart page', function () {
    $customer = User::factory()->create();
    $product = makeCartProduct();
    $cart = Cart::factory()->for($customer, 'customer')->create();
    $cartItem = CartItem::query()->create([
        'cart_id' => $cart->getKey(),
        'product_id' => $product->getKey(),
        'quantity' => 2,
    ]);

    Livewire::actingAs($customer)
        ->test('pages::shop.cart')
        ->call('removeItem', $cartItem->getKey())
        ->assertDispatched('cart-updated');

    $this->assertModelMissing($cartItem);
});

test('updating quantity respects stock bounds', function () {
    $customer = User::factory()->create();
    $product = makeCartProduct([
        'stock_quantity' => 4,
    ]);
    $cart = Cart::factory()->for($customer, 'customer')->create();
    $cartItem = CartItem::query()->create([
        'cart_id' => $cart->getKey(),
        'product_id' => $product->getKey(),
        'quantity' => 1,
    ]);

    Livewire::actingAs($customer)
        ->test('pages::shop.cart')
        ->call('updateQuantity', $cartItem->getKey(), 10)
        ->assertDispatched('cart-updated');

    expect($cartItem->fresh()->quantity)->toBe(4);
});

test('cart inline steppers use holdable Alpine controls', function () {
    $customer = User::factory()->create();
    $product = makeCartProduct([
        'stock_quantity' => 5,
    ]);
    $cart = Cart::factory()->for($customer, 'customer')->create();

    CartItem::query()->create([
        'cart_id' => $cart->getKey(),
        'product_id' => $product->getKey(),
        'quantity' => 2,
    ]);

    $this->actingAs($customer)
        ->get(route('shop.cart'))
        ->assertOk()
        ->assertSee('stepperButton(() => $wire.decrementQuantity', false)
        ->assertSee('stepperButton(() => $wire.incrementQuantity', false);
});

test('cart stepper methods adjust quantity against current stock', function () {
    $customer = User::factory()->create();
    $product = makeCartProduct([
        'stock_quantity' => 3,
    ]);
    $cart = Cart::factory()->for($customer, 'customer')->create();
    $cartItem = CartItem::query()->create([
        'cart_id' => $cart->getKey(),
        'product_id' => $product->getKey(),
        'quantity' => 2,
    ]);

    Livewire::actingAs($customer)
        ->test('pages::shop.cart')
        ->call('incrementQuantity', $cartItem->getKey())
        ->call('incrementQuantity', $cartItem->getKey())
        ->call('decrementQuantity', $cartItem->getKey())
        ->assertDispatched('cart-updated');

    expect($cartItem->fresh()->quantity)->toBe(2);
});

test('empty cart shows the empty state', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('shop.cart'))
        ->assertOk()
        ->assertSee('Your cart is empty')
        ->assertSee('Browse the market');
});

test('cart badge reflects total quantity across vendors', function () {
    $customer = User::factory()->create();
    $cart = Cart::factory()->for($customer, 'customer')->create();
    $firstVendor = VendorProfile::factory()->approved()->create();
    $secondVendor = VendorProfile::factory()->approved()->create();
    $firstProduct = makeCartProduct([
        'vendor' => $firstVendor,
    ]);
    $secondProduct = makeCartProduct([
        'vendor' => $secondVendor,
    ]);

    CartItem::query()->create([
        'cart_id' => $cart->getKey(),
        'product_id' => $firstProduct->getKey(),
        'quantity' => 2,
    ]);

    CartItem::query()->create([
        'cart_id' => $cart->getKey(),
        'product_id' => $secondProduct->getKey(),
        'quantity' => 3,
    ]);

    Livewire::actingAs($customer)
        ->test('cart.cart-badge')
        ->assertSee('5');
});
