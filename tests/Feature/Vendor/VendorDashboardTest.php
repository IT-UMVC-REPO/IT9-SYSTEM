<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;

function seedVendorDashboardOrder(VendorProfile $vendor, array $orderOverrides = [], array $paymentOverrides = []): Order
{
    $customer = User::factory()->create();
    $category = Category::factory()->standalone()->create();
    $product = Product::factory()
        ->for($vendor, 'vendor')
        ->for($category)
        ->active()
        ->create([
            'price' => 125,
        ]);

    $order = Order::factory()
        ->for($customer, 'customer')
        ->for($vendor, 'vendor')
        ->create(array_merge([
            'total_amount' => 250,
            'order_status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
        ], $orderOverrides));

    OrderItem::query()->create([
        'order_id' => $order->getKey(),
        'product_id' => $product->getKey(),
        'quantity' => 2,
        'unit_price' => 125,
    ]);

    Payment::factory()->for($order)->create(array_merge([
        'status' => $order->payment_status,
        'amount' => $order->total_amount,
        'paid_at' => $order->payment_status === PaymentStatus::Paid ? now() : null,
    ], $paymentOverrides));

    return $order;
}

test('page renders for approved vendors', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create([
        'store_name' => 'Davao Greens Hub',
    ]);

    $this->actingAs($vendorUser)
        ->get(route('vendor.dashboard'))
        ->assertOk()
        ->assertSee('Welcome back to Davao Greens Hub')
        ->assertSee('Sales overview');
});

test('non approved vendor is redirected to customer dashboard', function () {
    $vendorUser = User::factory()->vendor()->create();
    VendorProfile::factory()->for($vendorUser, 'user')->create();

    $this->actingAs($vendorUser)
        ->get(route('vendor.dashboard'))
        ->assertRedirect(route('customer.dashboard'));
});

test('kpi counts reflect actual vendor data', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();

    Product::factory()->for($vendorProfile, 'vendor')->active()->create([
        'name' => 'Low Stock Okra',
        'stock_quantity' => 4,
    ]);
    Product::factory()->for($vendorProfile, 'vendor')->create([
        'name' => 'Inactive Product',
    ]);

    seedVendorDashboardOrder($vendorProfile, [
        'order_status' => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
    ]);

    $deliveredOrder = seedVendorDashboardOrder($vendorProfile, [
        'order_status' => OrderStatus::Delivered,
        'payment_status' => PaymentStatus::Paid,
        'total_amount' => 340,
    ], [
        'status' => PaymentStatus::Paid,
        'amount' => 340,
        'paid_at' => now(),
    ]);

    $this->actingAs($vendorUser)
        ->get(route('vendor.dashboard'))
        ->assertOk()
        ->assertSee('Total products')
        ->assertSee('Active products')
        ->assertSee('Pending orders')
        ->assertSee('PHP 340.00')
        ->assertSee('Low Stock Okra')
        ->assertSee($deliveredOrder->customer->name);
});
