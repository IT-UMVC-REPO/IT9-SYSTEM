<?php

use App\Models\Order;
use App\Models\User;
use App\Models\VendorProfile;

test('vendors can view a customer profile page with star and report actions', function () {
    $vendor = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendor, 'user')->approved()->create();
    $customer = User::factory()->create([
        'name' => 'Profiled Customer',
    ]);

    Order::factory()->for($customer, 'customer')->for($vendorProfile, 'vendor')->create();

    $this->actingAs($vendor)
        ->get(route('shop.customers.show', $customer))
        ->assertOk()
        ->assertSee('Customer profile')
        ->assertSee($customer->name)
        ->assertSee('Report this customer')
        ->assertSeeLivewire('customer.star-button')
        ->assertSeeLivewire('report.report-modal');
});

test('customers can view a customer profile page without vendor-only actions', function () {
    $viewer = User::factory()->create();
    $customer = User::factory()->create([
        'name' => 'Another Customer',
    ]);

    $this->actingAs($viewer)
        ->get(route('shop.customers.show', $customer))
        ->assertOk()
        ->assertSee('Customer profile')
        ->assertDontSee('Report this customer')
        ->assertDontSeeLivewire('customer.star-button');
});
