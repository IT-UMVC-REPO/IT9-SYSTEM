<?php

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\VendorStatus;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;

test('admins can view the operational dashboard', function () {
    $admin = User::factory()->admin()->create();
    $pendingVendor = VendorProfile::factory()->create([
        'store_name' => 'Fresh Catch Corner',
        'status' => VendorStatus::Pending,
        'created_at' => now()->subHour(),
    ]);
    $approvedVendor = VendorProfile::factory()->approved()->create([
        'store_name' => 'Approved Greens',
    ]);
    $customer = User::factory()->create([
        'name' => 'Celia Buyer',
    ]);
    $order = Order::factory()->for($customer, 'customer')->for($approvedVendor, 'vendor')->create([
        'total_amount' => 245.50,
        'order_status' => OrderStatus::Preparing,
        'created_at' => now()->subMinutes(10),
    ]);

    Payment::factory()->for($order, 'order')->completed()->create([
        'amount' => 245.50,
        'paid_at' => now(),
    ]);

    Product::factory()->for($approvedVendor, 'vendor')->active()->count(2)->create();
    Message::query()->create([
        'sender_id' => $customer->id,
        'receiver_id' => $approvedVendor->user_id,
        'content' => 'Is the order still on the way?',
        'is_read' => false,
        'created_at' => now(),
    ]);

    Notification::query()->create([
        'user_id' => $customer->id,
        'title' => 'Order updated',
        'message' => 'Your order is being prepared.',
        'type' => NotificationType::OrderUpdate,
        'is_read' => false,
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Operational overview')
        ->assertSee('Pending approvals')
        ->assertSee('Recent orders')
        ->assertSee('Platform health')
        ->assertSee('chart.umd.min.js', false)
        ->assertSee('x-ref="canvas"', false)
        ->assertDontSee('createSukiApexChart', false)
        ->assertDontSee('ApexCharts', false)
        ->assertDontSee('application/json', false)
        ->assertSee($pendingVendor->store_name)
        ->assertSee($customer->name)
        ->assertSee($approvedVendor->store_name);
});

test('non admin users are redirected away from the admin dashboard', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('customer.dashboard'));
});
