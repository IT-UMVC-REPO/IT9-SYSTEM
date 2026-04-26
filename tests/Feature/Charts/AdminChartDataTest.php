<?php

use App\Enums\VendorStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorProfile;
use Livewire\Livewire;

test('admin revenue chart data returns 30 labels and fills empty days with zeroes', function () {
    $admin = User::factory()->admin()->create();
    $vendorProfile = VendorProfile::factory()->approved()->create();
    $customer = User::factory()->create();
    $todayRevenue = 150.75;

    $todayOrder = Order::factory()->for($customer, 'customer')->for($vendorProfile, 'vendor')->create();
    Payment::factory()->for($todayOrder, 'order')->completed()->create([
        'amount' => $todayRevenue,
        'paid_at' => now(),
    ]);

    $this->actingAs($admin);

    $chartData = Livewire::test('pages::admin.dashboard')->instance()->revenueChartData();
    $todayIndex = array_search(now()->format('M j'), $chartData['labels'], true);

    expect($chartData['labels'])->toHaveCount(30)
        ->and($todayIndex)->not->toBeFalse()
        ->and($chartData['series'][$todayIndex])->toBe($todayRevenue)
        ->and(collect($chartData['series'])->contains(fn ($value) => (float) $value === 0.0))->toBeTrue()
        ->and($chartData['series'])->not->toContain(null)
        ->and($chartData['total'])->toBe($todayRevenue);
});

test('vendor status donut data matches actual vendor profile counts', function () {
    $admin = User::factory()->admin()->create();

    VendorProfile::factory()->approved()->count(2)->create();
    VendorProfile::factory()->count(3)->create([
        'status' => VendorStatus::Pending,
    ]);
    VendorProfile::factory()->rejected()->count(1)->create();

    $this->actingAs($admin);

    $chartData = Livewire::test('pages::admin.dashboard')->instance()->vendorStatusChartData();

    expect($chartData['series'])->toBe([2, 3, 1])
        ->and($chartData['total'])->toBe(6);
});

test('user registration chart data matches the users created per day', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->create([
        'created_at' => now(),
    ]);
    User::factory()->create([
        'created_at' => now()->subDays(2),
    ]);

    $this->actingAs($admin);

    $chartData = Livewire::test('pages::admin.dashboard')->instance()->userRegistrationChartData();
    $todayIndex = array_search(now()->format('M j'), $chartData['labels'], true);
    $twoDaysAgoIndex = array_search(now()->subDays(2)->format('M j'), $chartData['labels'], true);

    expect($chartData['labels'])->toHaveCount(30)
        ->and($todayIndex)->not->toBeFalse()
        ->and($twoDaysAgoIndex)->not->toBeFalse()
        ->and($chartData['series'][$todayIndex])->toBe(3)
        ->and($chartData['series'][$twoDaysAgoIndex])->toBe(1);
});
