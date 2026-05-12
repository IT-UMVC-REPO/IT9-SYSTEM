<?php

use App\Enums\MarketCategory;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Enums\ReportStatus;
use App\Enums\TagumCoordinate;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Enums\VideoCallStatus;
use App\Models\Cart;
use App\Models\Category;
use App\Models\ConversationGroup;
use App\Models\Favorite;
use App\Models\GroupMessage;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Report;
use App\Models\RiderProfile;
use App\Models\User;
use App\Models\UserNickname;
use App\Models\VendorCustomerStar;
use App\Models\VendorProfile;
use App\Models\VideoCall;
use Database\Seeders\MarketplaceDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

test('database seeder provisions the Tagum marketplace dataset in the requested ranges', function () {
    $this->seed();

    $stableVendor = User::query()
        ->with('vendorProfile')
        ->where('email', MarketplaceDemoSeeder::STABLE_VENDOR_EMAIL)
        ->firstOrFail();
    $stableAdmin = User::query()
        ->where('email', MarketplaceDemoSeeder::STABLE_ADMIN_EMAIL)
        ->firstOrFail();
    $stableCustomer = User::query()
        ->where('email', MarketplaceDemoSeeder::STABLE_CUSTOMER_EMAIL)
        ->firstOrFail();
    $stableRider = User::query()
        ->with('riderProfile')
        ->where('email', MarketplaceDemoSeeder::STABLE_RIDER_EMAIL)
        ->firstOrFail();
    $productCountsOutsideVendorRange = DB::table('products')
        ->select('vendor_id')
        ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count")
        ->selectRaw("SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count")
        ->groupBy('vendor_id')
        ->havingRaw('active_count < 20 OR active_count > 30 OR inactive_count < 3 OR inactive_count > 6')
        ->exists();
    $mismatchedOrderItems = DB::table('order_items')
        ->join('orders', 'orders.id', '=', 'order_items.order_id')
        ->join('products', 'products.id', '=', 'order_items.product_id')
        ->whereColumn('orders.vendor_id', '!=', 'products.vendor_id')
        ->exists();
    $invalidGroupMemberCounts = DB::table('conversation_group_members')
        ->select('group_id')
        ->groupBy('group_id')
        ->havingRaw('COUNT(*) < 3 OR COUNT(*) > 8')
        ->exists();
    $invalidGroupMessageCounts = DB::table('group_messages')
        ->select('group_id')
        ->groupBy('group_id')
        ->havingRaw('COUNT(*) < 15 OR COUNT(*) > 40')
        ->exists();
    $productsAssignedToParentCategories = Product::query()
        ->whereHas('category.children')
        ->exists();
    $namedTagumAddresses = collect(TagumCoordinate::namedPlaces())->pluck('address');
    $usersOutsideNamedPlaces = User::query()
        ->whereNotIn('address', $namedTagumAddresses)
        ->exists();
    $vendorsOutsideNamedPlaces = VendorProfile::query()
        ->whereNotIn('vendor_address', $namedTagumAddresses)
        ->exists();

    expect($stableAdmin->role)->toBe(UserRole::Admin)
        ->and(Hash::check('password', $stableAdmin->password))->toBeTrue()
        ->and($stableVendor->role)->toBe(UserRole::Vendor)
        ->and(Hash::check('password', $stableVendor->password))->toBeTrue()
        ->and($stableVendor->vendorProfile?->store_name)->toBe(MarketplaceDemoSeeder::STABLE_VENDOR_STORE_NAME)
        ->and($stableVendor->vendorProfile?->status)->toBe(VendorStatus::Approved)
        ->and($stableCustomer->role)->toBe(UserRole::Customer)
        ->and(Hash::check('password', $stableCustomer->password))->toBeTrue()
        ->and($stableRider->role)->toBe(UserRole::Rider)
        ->and(Hash::check('password', $stableRider->password))->toBeTrue()
        ->and($stableRider->riderProfile?->status)->toBe('approved')
        ->and(Role::query()->count())->toBe(4)
        ->and(User::query()->count())->toBeGreaterThanOrEqual(195)
        ->and(User::query()->count())->toBeLessThanOrEqual(220)
        ->and(User::query()->whereNull('lat')->orWhereNull('lng')->exists())->toBeFalse()
        ->and($usersOutsideNamedPlaces)->toBeFalse()
        ->and(User::query()->where('role', UserRole::Customer)->count())->toBeGreaterThanOrEqual(151)
        ->and(User::query()->where('role', UserRole::Customer)->count())->toBeLessThanOrEqual(166)
        ->and(VendorProfile::query()->where('status', VendorStatus::Approved)->count())->toBeGreaterThanOrEqual(30)
        ->and(VendorProfile::query()->where('status', VendorStatus::Approved)->count())->toBeLessThanOrEqual(35)
        ->and(RiderProfile::query()->where('status', 'approved')->count())->toBeGreaterThanOrEqual(1)
        ->and(VendorProfile::query()->whereNull('lat')->orWhereNull('lng')->exists())->toBeFalse()
        ->and($vendorsOutsideNamedPlaces)->toBeFalse()
        ->and(VendorProfile::query()->where('status', VendorStatus::Pending)->count())->toBeGreaterThanOrEqual(8)
        ->and(VendorProfile::query()->where('status', VendorStatus::Pending)->count())->toBeLessThanOrEqual(10)
        ->and(VendorProfile::query()->where('status', VendorStatus::Rejected)->count())->toBeGreaterThanOrEqual(5)
        ->and(VendorProfile::query()->where('status', VendorStatus::Rejected)->count())->toBeLessThanOrEqual(6)
        ->and(Category::query()->count())->toBe(count(MarketCategory::cases()))
        ->and(Category::query()->whereNull('parent_id')->count())->toBe(count(MarketCategory::topLevelCases()))
        ->and(Category::query()->whereNotNull('parent_id')->count())->toBe(count(MarketCategory::leafCases()))
        ->and(Category::query()->whereNull('slug')->exists())->toBeFalse()
        ->and(Product::query()->count())->toBeGreaterThanOrEqual(950)
        ->and(Product::query()->count())->toBeLessThanOrEqual(1050)
        ->and($productCountsOutsideVendorRange)->toBeFalse()
        ->and(Product::query()->where('status', ProductStatus::Active)->count())->toBeGreaterThan(Product::query()->where('status', ProductStatus::Inactive)->count())
        ->and($productsAssignedToParentCategories)->toBeFalse()
        ->and(Cart::query()->count())->toBeGreaterThanOrEqual(80)
        ->and(Cart::query()->count())->toBeLessThanOrEqual(100)
        ->and(Cart::query()->doesntHave('cartItems')->exists())->toBeFalse()
        ->and(Favorite::query()->count())->toBeGreaterThanOrEqual(200)
        ->and(Favorite::query()->count())->toBeLessThanOrEqual(300)
        ->and(Order::query()->count())->toBeGreaterThanOrEqual(400)
        ->and(Order::query()->count())->toBeLessThanOrEqual(500)
        ->and(Order::query()->doesntHave('orderItems')->exists())->toBeFalse()
        ->and(Order::query()->doesntHave('payment')->exists())->toBeFalse()
        ->and(Order::query()->where('payment_method', '!=', PaymentMethod::Cod)->exists())->toBeFalse()
        ->and(Order::query()->where('order_status', OrderStatus::Delivered)->whereNull('estimated_delivery_at')->exists())->toBeFalse()
        ->and(Payment::query()->count())->toBe(Order::query()->count())
        ->and(Payment::query()->where('method', '!=', PaymentMethod::Cod)->exists())->toBeFalse()
        ->and($mismatchedOrderItems)->toBeFalse()
        ->and(Message::query()->count())->toBeGreaterThanOrEqual(1000)
        ->and(Message::query()->count())->toBeLessThanOrEqual(1500)
        ->and(ConversationGroup::query()->count())->toBeGreaterThanOrEqual(15)
        ->and(ConversationGroup::query()->count())->toBeLessThanOrEqual(20)
        ->and($invalidGroupMemberCounts)->toBeFalse()
        ->and(GroupMessage::query()->count())->toBeGreaterThanOrEqual(300)
        ->and(GroupMessage::query()->count())->toBeLessThanOrEqual(500)
        ->and($invalidGroupMessageCounts)->toBeFalse()
        ->and(Notification::query()->count())->toBeGreaterThanOrEqual(600)
        ->and(Notification::query()->count())->toBeLessThanOrEqual(800)
        ->and(DB::table('notifications')->distinct()->pluck('type')->sort()->values()->all())->toBe(collect(NotificationType::cases())->map->value->sort()->values()->all())
        ->and(Report::query()->count())->toBeGreaterThanOrEqual(30)
        ->and(Report::query()->count())->toBeLessThanOrEqual(50)
        ->and(DB::table('reports')->distinct()->pluck('status')->sort()->values()->all())->toBe(collect(ReportStatus::cases())->map->value->sort()->values()->all())
        ->and(VideoCall::query()->count())->toBeGreaterThanOrEqual(40)
        ->and(VideoCall::query()->count())->toBeLessThanOrEqual(60)
        ->and(DB::table('video_calls')->distinct()->pluck('status')->sort()->values()->all())->toBe(collect(VideoCallStatus::cases())->map->value->sort()->values()->all())
        ->and(VideoCall::query()->whereIn('status', [VideoCallStatus::Ended, VideoCallStatus::Active])->doesntHave('participants')->exists())->toBeFalse()
        ->and(UserNickname::query()->count())->toBeGreaterThanOrEqual(100)
        ->and(UserNickname::query()->count())->toBeLessThanOrEqual(150)
        ->and(VendorCustomerStar::query()->count())->toBeGreaterThanOrEqual(80)
        ->and(VendorCustomerStar::query()->count())->toBeLessThanOrEqual(120);
});

test('database seeder is rerun safe for stable records and unique baseline tables', function () {
    $this->seed();
    $this->seed();

    $duplicateVendorProfiles = DB::table('vendor_profiles')
        ->select('user_id')
        ->groupBy('user_id')
        ->havingRaw('COUNT(*) > 1')
        ->exists();
    $duplicateCategorySlugs = DB::table('categories')
        ->select('slug')
        ->groupBy('slug')
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

    expect(Role::query()->count())->toBe(4)
        ->and(User::query()->where('email', MarketplaceDemoSeeder::STABLE_ADMIN_EMAIL)->count())->toBe(1)
        ->and(User::query()->where('email', MarketplaceDemoSeeder::STABLE_VENDOR_EMAIL)->count())->toBe(1)
        ->and(User::query()->where('email', MarketplaceDemoSeeder::STABLE_CUSTOMER_EMAIL)->count())->toBe(1)
        ->and(User::query()->where('email', MarketplaceDemoSeeder::STABLE_RIDER_EMAIL)->count())->toBe(1)
        ->and(RiderProfile::query()->where('user_id', User::query()->where('email', MarketplaceDemoSeeder::STABLE_RIDER_EMAIL)->value('id'))->count())->toBe(1)
        ->and(VendorProfile::query()->where('store_name', MarketplaceDemoSeeder::STABLE_VENDOR_STORE_NAME)->count())->toBe(1)
        ->and(Category::query()->count())->toBe(count(MarketCategory::cases()))
        ->and(Category::query()->whereNull('slug')->exists())->toBeFalse()
        ->and($duplicateCategorySlugs)->toBeFalse()
        ->and($duplicateVendorProfiles)->toBeFalse()
        ->and($duplicateCarts)->toBeFalse()
        ->and($duplicatePayments)->toBeFalse();
});
