<?php

use App\Enums\AuditEvent;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\VendorStatus;
use App\Models\AuditLog;
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
        ->assertSee('mr-1 gap-0.5', false)
        ->assertSee('brand-panel-muted flex min-w-0 flex-col gap-3 p-4', false)
        ->assertSee('fa-solid fa-wallet', false)
        ->assertSee('chart.umd.min.js', false)
        ->assertSee('x-ref="canvas"', false)
        ->assertDontSee('createSukiApexChart', false)
        ->assertDontSee('ApexCharts', false)
        ->assertDontSee('application/json', false)
        ->assertSee($pendingVendor->store_name)
        ->assertSee($customer->name)
        ->assertSee($approvedVendor->store_name);
});

test('dashboard preview widgets only show three newest rows', function () {
    $admin = User::factory()->admin()->create();

    $vendors = collect(range(1, 4))->map(function (int $index): VendorProfile {
        return VendorProfile::factory()->create([
            'store_name' => "Pending Stall {$index}",
            'status' => VendorStatus::Pending,
            'created_at' => now()->subMinutes($index),
        ]);
    });

    $approvedVendor = VendorProfile::factory()->approved()->create();

    $orders = collect(range(1, 4))->map(function (int $index) use ($approvedVendor): Order {
        $customer = User::factory()->create([
            'name' => "Recent Buyer {$index}",
        ]);

        return Order::factory()
            ->for($customer, 'customer')
            ->for($approvedVendor, 'vendor')
            ->create([
                'total_amount' => 100 + $index,
                'created_at' => now()->subMinutes($index),
            ]);
    });

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee($vendors[0]->store_name)
        ->assertSee($vendors[1]->store_name)
        ->assertSee($vendors[2]->store_name)
        ->assertDontSee($vendors[3]->store_name)
        ->assertSee($orders[0]->customer->name)
        ->assertSee($orders[1]->customer->name)
        ->assertSee($orders[2]->customer->name)
        ->assertDontSee($orders[3]->customer->name);
});

test('dashboard platform feed eagerly renders latest audit entries', function () {
    $admin = User::factory()->admin()->create([
        'name' => 'Admin Liza',
    ]);

    $entries = collect(range(1, 6))->map(function (int $index) use ($admin): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $admin->id,
            'event' => AuditEvent::UserLoggedIn,
            'description' => "Audit event {$index}",
            'created_at' => now()->subMinutes(6 - $index),
        ]);
    });

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Platform feed')
        ->assertSee('Audit event 6')
        ->assertSee('Audit event 3')
        ->assertDontSee('Audit event 2')
        ->assertDontSee('Audit event 1')
        ->assertDontSee('Waiting for activity...');
});

test('non admin users are redirected away from the admin dashboard', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('customer.dashboard'));
});
