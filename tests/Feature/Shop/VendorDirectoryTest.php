<?php

use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Support\Facades\Route;

test('page renders and shows approved vendors only', function () {
    $customer = User::factory()->create();
    $approvedVendor = VendorProfile::factory()->approved()->create([
        'store_name' => 'Approved Wet Market Stall',
    ]);
    $pendingVendor = VendorProfile::factory()->create([
        'store_name' => 'Pending Wet Market Stall',
    ]);
    $rejectedVendor = VendorProfile::factory()->rejected()->create([
        'store_name' => 'Rejected Wet Market Stall',
    ]);

    $this->actingAs($customer)
        ->get(route('shop.vendors'))
        ->assertOk()
        ->assertSee('Discover Stalls')
        ->assertSee('Browse Vendors &amp; Find Stalls', false)
        ->assertSee('Show Map')
        ->assertSee('Hide Map')
        ->assertSee('vendor-selected', false)
        ->assertSee('class="brand-panel relative overflow-hidden p-4 transition sm:p-5"', false)
        ->assertSee('class="absolute inset-4 z-[30] flex items-center justify-center', false)
        ->assertDontSee('class="fixed inset-0 z-[90]', false)
        ->assertSee($approvedVendor->store_name)
        ->assertDontSee($pendingVendor->store_name)
        ->assertDontSee($rejectedVendor->store_name);
});

test('search filters vendors by store name', function () {
    $customer = User::factory()->create();
    $matchingVendor = VendorProfile::factory()->approved()->create([
        'store_name' => 'Golden Pechay Stall',
    ]);
    $otherVendor = VendorProfile::factory()->approved()->create([
        'store_name' => 'Bajada Seafood Corner',
    ]);

    $this->actingAs($customer)
        ->get(route('shop.vendors', ['search' => 'Pechay']))
        ->assertOk()
        ->assertSee($matchingVendor->store_name)
        ->assertDontSee($otherVendor->store_name);
});

test('pending and rejected vendors do not appear in the directory', function () {
    $customer = User::factory()->create();

    VendorProfile::factory()->create([
        'store_name' => 'Still Pending',
    ]);

    VendorProfile::factory()->rejected()->create([
        'store_name' => 'Rejected Stall',
    ]);

    $this->actingAs($customer)
        ->get(route('shop.vendors'))
        ->assertOk()
        ->assertSee('No approved stalls found')
        ->assertDontSee('Still Pending')
        ->assertDontSee('Rejected Stall');
});

test('empty state shows when there are no approved vendors', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('shop.vendors'))
        ->assertOk()
        ->assertSee('No approved stalls found');
});

test('dedicated shop map route is removed', function (): void {
    expect(Route::has('shop.map'))->toBeFalse();
});
