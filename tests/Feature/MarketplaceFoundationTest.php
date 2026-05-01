<?php

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('marketplace tables and user columns exist', function () {
    expect(Schema::hasColumns('users', ['role', 'phone', 'address', 'profile_image', 'is_active']))->toBeTrue()
        ->and(Schema::hasTable('vendor_profiles'))->toBeTrue()
        ->and(Schema::hasTable('categories'))->toBeTrue()
        ->and(Schema::hasTable('products'))->toBeTrue()
        ->and(Schema::hasTable('carts'))->toBeTrue()
        ->and(Schema::hasTable('cart_items'))->toBeTrue()
        ->and(Schema::hasTable('orders'))->toBeTrue()
        ->and(Schema::hasTable('order_items'))->toBeTrue()
        ->and(Schema::hasTable('payments'))->toBeTrue()
        ->and(Schema::hasTable('messages'))->toBeTrue()
        ->and(Schema::hasTable('favorites'))->toBeTrue()
        ->and(Schema::hasTable('notifications'))->toBeTrue();
});

test('marketplace factories build related records with enum casts', function () {
    $vendorProfile = VendorProfile::factory()->approved()->create();
    $category = Category::factory()->create();
    $product = Product::factory()
        ->for($vendorProfile, 'vendor')
        ->for($category)
        ->active()
        ->create();
    $customer = User::factory()->create();
    $cart = Cart::factory()->for($customer, 'customer')->create();

    $cartItem = CartItem::query()->create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $order = Order::factory()->for($customer, 'customer')->create([
        'vendor_id' => $vendorProfile->id,
        'total_amount' => 99.90,
        'payment_method' => PaymentMethod::Cod,
    ]);

    $orderItem = OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => $product->price,
    ]);

    $payment = Payment::factory()->for($order)->completed()->create([
        'amount' => $order->total_amount,
        'method' => PaymentMethod::Cod,
    ]);

    $message = Message::query()->create([
        'sender_id' => $customer->id,
        'receiver_id' => $vendorProfile->user_id,
        'order_id' => $order->id,
        'content' => 'Please prepare this order early.',
    ]);

    $notification = Notification::query()->create([
        'user_id' => $customer->id,
        'title' => 'Order updated',
        'message' => 'Your order is confirmed.',
        'type' => NotificationType::OrderUpdate,
    ]);

    expect($vendorProfile->fresh()->status)->toBe(VendorStatus::Approved)
        ->and($vendorProfile->user->role)->toBe(UserRole::Vendor)
        ->and($product->fresh()->status)->toBe(ProductStatus::Active)
        ->and($order->fresh()->payment_method)->toBe(PaymentMethod::Cod)
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->order_status)->toBe(OrderStatus::Pending)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($cart->customer->is($customer))->toBeTrue()
        ->and($cartItem->product->is($product))->toBeTrue()
        ->and($orderItem->order->is($order))->toBeTrue()
        ->and($message->order->is($order))->toBeTrue()
        ->and($notification->user->is($customer))->toBeTrue();
});

test('marketplace factories use readable non lorem descriptions', function () {
    $vendorProfile = VendorProfile::factory()->make();
    $product = Product::factory()->make();

    expect(strtolower($vendorProfile->store_description))->not->toContain('lorem ipsum')
        ->and(strtolower($product->description))->not->toContain('lorem ipsum')
        ->and($vendorProfile->store_description)->not->toBeEmpty()
        ->and($product->description)->not->toBeEmpty();
});

test('marketplace unique constraints reject duplicate records for :dataset', function (Closure $assertDuplicateInsertFails) {
    $assertDuplicateInsertFails();
})->with([
    'vendor profiles are one to one with users' => [
        function (): void {
            $user = User::factory()->create();

            VendorProfile::factory()->for($user, 'user')->create();

            expect(fn () => VendorProfile::factory()->for($user, 'user')->create())
                ->toThrow(QueryException::class);
        },
    ],
    'carts are one to one with customers' => [
        function (): void {
            $customer = User::factory()->create();

            Cart::factory()->for($customer, 'customer')->create();

            expect(fn () => Cart::factory()->for($customer, 'customer')->create())
                ->toThrow(QueryException::class);
        },
    ],
    'payments are one to one with orders' => [
        function (): void {
            $order = Order::factory()->create();

            Payment::factory()->for($order)->create();

            expect(fn () => Payment::factory()->for($order)->create())
                ->toThrow(QueryException::class);
        },
    ],
    'favorites are unique per customer and vendor' => [
        function (): void {
            $customer = User::factory()->create();
            $vendorProfile = VendorProfile::factory()->approved()->create();

            Favorite::query()->create([
                'customer_id' => $customer->id,
                'vendor_id' => $vendorProfile->id,
            ]);

            expect(fn () => Favorite::query()->create([
                'customer_id' => $customer->id,
                'vendor_id' => $vendorProfile->id,
            ]))->toThrow(QueryException::class);
        },
    ],
]);
