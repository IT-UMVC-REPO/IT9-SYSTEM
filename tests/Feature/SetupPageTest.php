<?php

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\VendorProfile;

test('guests are redirected to login when opening the setup page', function () {
    $this->get(route('setup'))
        ->assertRedirect(route('login'));
});

test('authenticated customers can see the setup page with both registration cards', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('setup'))
        ->assertOk()
        ->assertSee('Vendor Registration')
        ->assertSee('Rider Registration')
        ->assertSee('Start application');
});

test('approved vendors are redirected to the vendor dashboard from the setup page', function () {
    $vendor = User::factory()->vendor()->create();
    VendorProfile::factory()->for($vendor, 'user')->approved()->create();

    $this->actingAs($vendor)
        ->get(route('setup'))
        ->assertRedirect(route('vendor.dashboard'));
});

test('customers with a pending vendor profile see the pending badge on the setup page', function () {
    $customer = User::factory()->create();

    VendorProfile::factory()->for($customer, 'user')->create([
        'store_name' => 'Market Harvest',
        'status' => VendorStatus::Pending,
    ]);

    $this->actingAs($customer)
        ->get(route('setup'))
        ->assertOk()
        ->assertSee('Pending')
        ->assertSee('View application');
});
