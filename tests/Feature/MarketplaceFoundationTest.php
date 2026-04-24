<?php

use App\Enums\MarketCategory;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

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
        'payment_method' => PaymentMethod::Gcash,
    ]);

    $orderItem = OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => $product->price,
    ]);

    $payment = Payment::factory()->for($order)->completed()->create([
        'amount' => $order->total_amount,
        'method' => PaymentMethod::Gcash,
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
        ->and($order->fresh()->payment_method)->toBe(PaymentMethod::Gcash)
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->order_status)->toBe(OrderStatus::Pending)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($cart->customer->is($customer))->toBeTrue()
        ->and($cartItem->product->is($product))->toBeTrue()
        ->and($orderItem->order->is($order))->toBeTrue()
        ->and($message->order->is($order))->toBeTrue()
        ->and($notification->user->is($customer))->toBeTrue();
});

test('vendor profiles are one to one with users', function () {
    $user = User::factory()->create();

    VendorProfile::factory()->for($user, 'user')->create();

    expect(fn () => VendorProfile::factory()->for($user, 'user')->create())
        ->toThrow(QueryException::class);
});

test('carts are one to one with customers', function () {
    $customer = User::factory()->create();

    Cart::factory()->for($customer, 'customer')->create();

    expect(fn () => Cart::factory()->for($customer, 'customer')->create())
        ->toThrow(QueryException::class);
});

test('payments are one to one with orders', function () {
    $order = Order::factory()->create();

    Payment::factory()->for($order)->create();

    expect(fn () => Payment::factory()->for($order)->create())
        ->toThrow(QueryException::class);
});

test('favorites are unique per customer and vendor', function () {
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
});

test('database seeder provisions a large marketplace dataset', function () {
    $this->seed();

    $marketCategoryCount = count(MarketCategory::cases());
    $topLevelCategoryCount = count(MarketCategory::topLevelCases());
    $leafCategoryCount = count(MarketCategory::leafCases());
    $expectedProductCount = ($leafCategoryCount * 3) + (14 * 10);
    $stableVendor = User::query()
        ->with('vendorProfile')
        ->where('email', 'vendor@example.com')
        ->firstOrFail();
    $messagesCount = Message::query()->count();
    $favoritesCount = Favorite::query()->count();
    $cartItemsCount = CartItem::query()->count();
    $orderItemsCount = OrderItem::query()->count();
    $mismatchedOrderItems = DB::table('order_items')
        ->join('orders', 'orders.id', '=', 'order_items.order_id')
        ->join('products', 'products.id', '=', 'order_items.product_id')
        ->whereColumn('orders.vendor_id', '!=', 'products.vendor_id')
        ->exists();
    $productsAssignedToParentCategories = Product::query()
        ->whereHas('category.children')
        ->exists();

    expect(User::query()->where('email', 'admin@example.com')->first()?->role)->toBe(UserRole::Admin)
        ->and($stableVendor->role)->toBe(UserRole::Vendor)
        ->and($stableVendor->vendorProfile?->status)->toBe(VendorStatus::Approved)
        ->and(User::query()->where('email', 'test@example.com')->first()?->role)->toBe(UserRole::Customer)
        ->and(Role::query()->count())->toBe(3)
        ->and(User::query()->count())->toBe(95)
        ->and(VendorProfile::query()->count())->toBe(19)
        ->and(VendorProfile::query()->approved()->count())->toBe(15)
        ->and(Category::query()->count())->toBe($marketCategoryCount)
        ->and(Category::query()->whereNull('parent_id')->count())->toBe($topLevelCategoryCount)
        ->and(Category::query()->whereNotNull('parent_id')->count())->toBe($leafCategoryCount)
        ->and(Category::query()->whereNull('slug')->exists())->toBeFalse()
        ->and(Product::query()->count())->toBe($expectedProductCount)
        ->and(Cart::query()->count())->toBe(30)
        ->and($cartItemsCount)->toBeGreaterThanOrEqual(60)
        ->and($cartItemsCount)->toBeLessThanOrEqual(150)
        ->and(Favorite::query()->count())->toBeGreaterThanOrEqual(45)
        ->and(Order::query()->count())->toBe(120)
        ->and($orderItemsCount)->toBeGreaterThanOrEqual(120)
        ->and($orderItemsCount)->toBeLessThanOrEqual(480)
        ->and(Payment::query()->count())->toBe(120)
        ->and($messagesCount)->toBeGreaterThanOrEqual(120)
        ->and($messagesCount)->toBeLessThanOrEqual(240)
        ->and(Notification::query()->count())->toBe(145)
        ->and($favoritesCount)->toBeLessThanOrEqual(135)
        ->and(Order::query()->doesntHave('orderItems')->exists())->toBeFalse()
        ->and(Order::query()->doesntHave('payment')->exists())->toBeFalse()
        ->and($mismatchedOrderItems)->toBeFalse()
        ->and($productsAssignedToParentCategories)->toBeFalse();
});

test('database seeder is rerun safe for baseline records', function () {
    $this->seed();
    $this->seed();

    $duplicateVendorProfiles = DB::table('vendor_profiles')
        ->select('user_id')
        ->groupBy('user_id')
        ->havingRaw('COUNT(*) > 1')
        ->exists();
    $duplicateCarts = DB::table('carts')
        ->select('customer_id')
        ->groupBy('customer_id')
        ->havingRaw('COUNT(*) > 1')
        ->exists();
    $duplicatePayments = DB::table('payments')
        ->select('order_id')
        ->groupBy('order_id')
        ->havingRaw('COUNT(*) > 1')
        ->exists();
    $duplicateCategorySlugs = DB::table('categories')
        ->select('slug')
        ->groupBy('slug')
        ->havingRaw('COUNT(*) > 1')
        ->exists();

    expect(Role::query()->count())->toBe(3)
        ->and(Role::query()->where('name', 'admin')->count())->toBe(1)
        ->and(Role::query()->where('name', 'vendor')->count())->toBe(1)
        ->and(Role::query()->where('name', 'customer')->count())->toBe(1)
        ->and(User::query()->where('email', 'admin@example.com')->count())->toBe(1)
        ->and(User::query()->where('email', 'vendor@example.com')->count())->toBe(1)
        ->and(User::query()->where('email', 'test@example.com')->count())->toBe(1)
        ->and(VendorProfile::query()->where('store_name', 'Fresh Vendor Market')->count())->toBe(1)
        ->and(Category::query()->count())->toBe(count(MarketCategory::cases()))
        ->and(Category::query()->whereNull('slug')->exists())->toBeFalse()
        ->and($duplicateCategorySlugs)->toBeFalse()
        ->and($duplicateVendorProfiles)->toBeFalse()
        ->and($duplicateCarts)->toBeFalse()
        ->and($duplicatePayments)->toBeFalse();
});
