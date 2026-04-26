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

function createVendorSalesOrder(VendorProfile $vendor, string $productName, float $unitPrice, int $quantity, array $orderOverrides = [], array $paymentOverrides = []): array
{
    $customer = User::factory()->create();
    $category = Category::factory()->standalone()->create();
    $product = Product::factory()
        ->for($vendor, 'vendor')
        ->for($category)
        ->active()
        ->create([
            'name' => $productName,
            'price' => $unitPrice,
        ]);

    $totalAmount = $unitPrice * $quantity;

    $order = Order::factory()
        ->for($customer, 'customer')
        ->for($vendor, 'vendor')
        ->create(array_merge([
            'total_amount' => $totalAmount,
            'order_status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
            'created_at' => now()->subDays(2),
        ], $orderOverrides));

    OrderItem::query()->create([
        'order_id' => $order->getKey(),
        'product_id' => $product->getKey(),
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
    ]);

    $payment = Payment::factory()->for($order)->create(array_merge([
        'status' => $order->payment_status,
        'amount' => $order->total_amount,
        'paid_at' => now()->subDays(2),
    ], $paymentOverrides));

    return compact('customer', 'product', 'order', 'payment');
}

test('sales page renders for approved vendors', function () {
    $vendorUser = User::factory()->vendor()->create();
    VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();

    $this->actingAs($vendorUser)
        ->get(route('vendor.sales'))
        ->assertOk()
        ->assertSee('Sales overview')
        ->assertSee('Top products');
});

test('revenue totals only count delivered orders with paid payments', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();

    createVendorSalesOrder($vendorProfile, 'Fresh Tuna', 350, 1);
    createVendorSalesOrder($vendorProfile, 'Pending Payment Fish', 200, 1, [
        'payment_status' => PaymentStatus::Pending,
    ], [
        'status' => PaymentStatus::Pending,
        'paid_at' => null,
    ]);
    createVendorSalesOrder($vendorProfile, 'Not Yet Delivered Fish', 180, 1, [
        'order_status' => OrderStatus::Preparing,
        'payment_status' => PaymentStatus::Paid,
    ], [
        'status' => PaymentStatus::Paid,
    ]);

    $this->actingAs($vendorUser)
        ->get(route('vendor.sales'))
        ->assertOk()
        ->assertSee('₱350.00')
        ->assertSee('Orders fulfilled');
});

test('period filter narrows results', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();

    createVendorSalesOrder($vendorProfile, 'Recent Bangus', 100, 1, [
        'created_at' => now()->subDays(2),
    ], [
        'paid_at' => now()->subDays(2),
    ]);

    createVendorSalesOrder($vendorProfile, 'Older Salmon', 300, 1, [
        'created_at' => now()->subDays(12),
    ], [
        'paid_at' => now()->subDays(12),
    ]);

    $this->actingAs($vendorUser)
        ->get(route('vendor.sales', ['period' => 'week']))
        ->assertOk()
        ->assertSee('₱100.00');

    $this->actingAs($vendorUser)
        ->get(route('vendor.sales', ['period' => 'month']))
        ->assertOk()
        ->assertSee('₱400.00');
});

test('top products are ranked by revenue', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();

    createVendorSalesOrder($vendorProfile, 'Premium Tuna', 250, 2);
    createVendorSalesOrder($vendorProfile, 'Fresh Prawns', 150, 1);

    $this->actingAs($vendorUser)
        ->get(route('vendor.sales'))
        ->assertOk()
        ->assertSeeInOrder(['Premium Tuna', 'Fresh Prawns']);
});

test('vendor scope isolation is enforced on the sales page', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();

    createVendorSalesOrder($vendorProfile, 'Own Stall Special', 220, 1);

    $otherVendorUser = User::factory()->vendor()->create();
    $otherVendorProfile = VendorProfile::factory()->for($otherVendorUser, 'user')->approved()->create();
    createVendorSalesOrder($otherVendorProfile, 'Other Stall Special', 900, 1);

    $this->actingAs($vendorUser)
        ->get(route('vendor.sales'))
        ->assertOk()
        ->assertSee('Own Stall Special')
        ->assertDontSee('Other Stall Special');
});
