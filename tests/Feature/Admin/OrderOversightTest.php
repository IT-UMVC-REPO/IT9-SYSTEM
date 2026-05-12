<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorProfile;

function createOversightOrder(string $customerName, string $vendorName, OrderStatus $status): Order
{
    $customer = User::factory()->create([
        'name' => $customerName,
    ]);
    $vendor = VendorProfile::factory()->approved()->create([
        'store_name' => $vendorName,
    ]);

    $order = Order::factory()
        ->for($customer, 'customer')
        ->for($vendor, 'vendor')
        ->create([
            'total_amount' => 275,
            'order_status' => $status,
            'payment_status' => $status === OrderStatus::Delivered ? PaymentStatus::Paid : PaymentStatus::Pending,
        ]);

    Payment::factory()->for($order)->create([
        'status' => $order->payment_status,
        'amount' => $order->total_amount,
        'paid_at' => $order->payment_status === PaymentStatus::Paid ? now() : null,
    ]);

    return $order;
}

test('admin can view all orders', function () {
    $admin = User::factory()->admin()->create();

    createOversightOrder('Maria Santos', 'Nanay Cora Greens', OrderStatus::Pending);
    createOversightOrder('Leo Cruz', 'Kuya Ben Seafood', OrderStatus::Delivered);

    $this->actingAs($admin)
        ->get(route('admin.orders'))
        ->assertOk()
        ->assertSee('Maria Santos')
        ->assertSee('Nanay Cora Greens')
        ->assertSee('Leo Cruz')
        ->assertSee('Kuya Ben Seafood');
});

test('status filter works', function () {
    $admin = User::factory()->admin()->create();

    createOversightOrder('Pending Buyer', 'Pending Stall', OrderStatus::Pending);
    createOversightOrder('Delivered Buyer', 'Delivered Stall', OrderStatus::Delivered);

    $this->actingAs($admin)
        ->get(route('admin.orders', ['statusFilter' => OrderStatus::Pending->value]))
        ->assertOk()
        ->assertSee('Pending Buyer')
        ->assertDontSee('Delivered Buyer');
});

test('search narrows by customer name and vendor name', function () {
    $admin = User::factory()->admin()->create();

    createOversightOrder('Customer Angela', 'Angela Greens', OrderStatus::Pending);
    createOversightOrder('Customer Miguel', 'Bajada Seafood', OrderStatus::Pending);

    $this->actingAs($admin)
        ->get(route('admin.orders', ['search' => 'Angela']))
        ->assertOk()
        ->assertSee('Customer Angela')
        ->assertSee('Angela Greens')
        ->assertDontSee('Customer Miguel')
        ->assertDontSee('Bajada Seafood');

    $this->actingAs($admin)
        ->get(route('admin.orders', ['search' => 'Seafood']))
        ->assertOk()
        ->assertSee('Customer Miguel')
        ->assertSee('Bajada Seafood')
        ->assertDontSee('Customer Angela');
});

test('non admin users are redirected away', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.orders'))
        ->assertRedirect(route('customer.dashboard'));
});
