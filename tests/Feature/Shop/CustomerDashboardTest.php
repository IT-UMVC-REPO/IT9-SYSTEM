<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;

function createCustomerDashboardOrder(User $customer, string $storeName, int $daysAgo): Order
{
    $vendor = VendorProfile::factory()->approved()->create([
        'store_name' => $storeName,
    ]);
    $category = Category::factory()->standalone()->create();
    $product = Product::factory()
        ->for($vendor, 'vendor')
        ->for($category)
        ->active()
        ->create([
            'price' => 140,
        ]);

    $order = Order::factory()
        ->for($customer, 'customer')
        ->for($vendor, 'vendor')
        ->create([
            'total_amount' => 280,
            'payment_method' => PaymentMethod::Cod,
            'payment_status' => PaymentStatus::Pending,
            'order_status' => OrderStatus::Pending,
            'created_at' => now()->subDays($daysAgo),
        ]);

    OrderItem::query()->create([
        'order_id' => $order->getKey(),
        'product_id' => $product->getKey(),
        'quantity' => 2,
        'unit_price' => 140,
    ]);

    Payment::factory()->for($order)->create([
        'method' => $order->payment_method,
        'status' => $order->payment_status,
        'amount' => $order->total_amount,
    ]);

    return $order;
}

test('page renders for authenticated customers', function () {
    $customer = User::factory()->create([
        'name' => 'Maria Santos',
    ]);

    $this->actingAs($customer)
        ->get(route('customer.dashboard'))
        ->assertOk()
        ->assertSee('Maria Santos')
        ->assertSee('Browse storefront')
        ->assertSee('Favourite stalls');
});

test('shows the three most recent orders', function () {
    $customer = User::factory()->create();

    createCustomerDashboardOrder($customer, 'Oldest Stall', 8);
    createCustomerDashboardOrder($customer, 'Second Stall', 3);
    createCustomerDashboardOrder($customer, 'Newest Stall', 0);
    createCustomerDashboardOrder($customer, 'Third Stall', 1);

    $this->actingAs($customer)
        ->get(route('customer.dashboard'))
        ->assertOk()
        ->assertSee('Newest Stall')
        ->assertSee('Third Stall')
        ->assertSee('Second Stall')
        ->assertDontSee('Oldest Stall');
});

test('shows empty state when the customer has no orders', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('customer.dashboard'))
        ->assertOk()
        ->assertSee('No orders yet')
        ->assertSee('Once you place your first order');
});

test('guests are redirected to login', function () {
    $this->get(route('customer.dashboard'))
        ->assertRedirect(route('login'));
});
