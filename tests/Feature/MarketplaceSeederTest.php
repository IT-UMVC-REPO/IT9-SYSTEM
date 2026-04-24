<?php

use App\Enums\MarketCategory;
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
use Database\Seeders\MarketplaceDemoSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

test('database seeder provisions the expected marketplace dataset', function () {
    $this->seed();

    $marketCategoryCount = count(MarketCategory::cases());
    $topLevelCategoryCount = count(MarketCategory::topLevelCases());
    $leafCategoryCount = count(MarketCategory::leafCases());
    $expectedProductCount = ($leafCategoryCount * 3)
        + (
            MarketplaceDemoSeeder::ADDITIONAL_APPROVED_VENDOR_COUNT
            * (
                MarketplaceDemoSeeder::APPROVED_VENDOR_ACTIVE_PRODUCT_COUNT
                + MarketplaceDemoSeeder::APPROVED_VENDOR_INACTIVE_PRODUCT_COUNT
            )
        );
    $expectedUserCount = 3
        + MarketplaceDemoSeeder::ADDITIONAL_APPROVED_VENDOR_COUNT
        + MarketplaceDemoSeeder::PENDING_VENDOR_COUNT
        + MarketplaceDemoSeeder::REJECTED_VENDOR_COUNT
        + MarketplaceDemoSeeder::ADDITIONAL_CUSTOMER_COUNT;
    $expectedVendorProfileCount = 1
        + MarketplaceDemoSeeder::ADDITIONAL_APPROVED_VENDOR_COUNT
        + MarketplaceDemoSeeder::PENDING_VENDOR_COUNT
        + MarketplaceDemoSeeder::REJECTED_VENDOR_COUNT;
    $expectedApprovedVendorCount = 1 + MarketplaceDemoSeeder::ADDITIONAL_APPROVED_VENDOR_COUNT;
    $expectedNotificationCount = MarketplaceDemoSeeder::ORDER_COUNT + MarketplaceDemoSeeder::EXTRA_NOTIFICATION_COUNT;
    $stableVendor = User::query()
        ->with('vendorProfile')
        ->where('email', MarketplaceDemoSeeder::STABLE_VENDOR_EMAIL)
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

    expect(User::query()->where('email', MarketplaceDemoSeeder::STABLE_ADMIN_EMAIL)->first()?->role)->toBe(UserRole::Admin)
        ->and($stableVendor->role)->toBe(UserRole::Vendor)
        ->and($stableVendor->vendorProfile?->status)->toBe(VendorStatus::Approved)
        ->and(User::query()->where('email', MarketplaceDemoSeeder::STABLE_CUSTOMER_EMAIL)->first()?->role)->toBe(UserRole::Customer)
        ->and(Role::query()->count())->toBe(3)
        ->and(User::query()->count())->toBe($expectedUserCount)
        ->and(VendorProfile::query()->count())->toBe($expectedVendorProfileCount)
        ->and(VendorProfile::query()->approved()->count())->toBe($expectedApprovedVendorCount)
        ->and(Category::query()->count())->toBe($marketCategoryCount)
        ->and(Category::query()->whereNull('parent_id')->count())->toBe($topLevelCategoryCount)
        ->and(Category::query()->whereNotNull('parent_id')->count())->toBe($leafCategoryCount)
        ->and(Category::query()->whereNull('slug')->exists())->toBeFalse()
        ->and(Product::query()->count())->toBe($expectedProductCount)
        ->and(Cart::query()->count())->toBe(MarketplaceDemoSeeder::CART_COUNT)
        ->and($cartItemsCount)->toBeGreaterThanOrEqual(60)
        ->and($cartItemsCount)->toBeLessThanOrEqual(150)
        ->and(Favorite::query()->count())->toBeGreaterThanOrEqual(MarketplaceDemoSeeder::FAVORITED_CUSTOMER_COUNT)
        ->and(Order::query()->count())->toBe(MarketplaceDemoSeeder::ORDER_COUNT)
        ->and($orderItemsCount)->toBeGreaterThanOrEqual(120)
        ->and($orderItemsCount)->toBeLessThanOrEqual(480)
        ->and(Payment::query()->count())->toBe(MarketplaceDemoSeeder::ORDER_COUNT)
        ->and($messagesCount)->toBeGreaterThanOrEqual(MarketplaceDemoSeeder::ORDER_COUNT)
        ->and($messagesCount)->toBeLessThanOrEqual(MarketplaceDemoSeeder::ORDER_COUNT * 2)
        ->and(Notification::query()->count())->toBe($expectedNotificationCount)
        ->and($favoritesCount)->toBeLessThanOrEqual(MarketplaceDemoSeeder::FAVORITED_CUSTOMER_COUNT * 3)
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
        ->and(User::query()->where('email', MarketplaceDemoSeeder::STABLE_ADMIN_EMAIL)->count())->toBe(1)
        ->and(User::query()->where('email', MarketplaceDemoSeeder::STABLE_VENDOR_EMAIL)->count())->toBe(1)
        ->and(User::query()->where('email', MarketplaceDemoSeeder::STABLE_CUSTOMER_EMAIL)->count())->toBe(1)
        ->and(VendorProfile::query()->where('store_name', MarketplaceDemoSeeder::STABLE_VENDOR_STORE_NAME)->count())->toBe(1)
        ->and(Category::query()->count())->toBe(count(MarketCategory::cases()))
        ->and(Category::query()->whereNull('slug')->exists())->toBeFalse()
        ->and($duplicateCategorySlugs)->toBeFalse()
        ->and($duplicateVendorProfiles)->toBeFalse()
        ->and($duplicateCarts)->toBeFalse()
        ->and($duplicatePayments)->toBeFalse();
});
