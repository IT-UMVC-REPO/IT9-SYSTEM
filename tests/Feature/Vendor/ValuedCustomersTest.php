<?php

use App\Models\User;
use App\Models\VendorCustomerStar;
use App\Models\VendorProfile;

test('vendors can view valued customers page with starred customers', function () {
    $vendor = User::factory()->vendor()->create();
    VendorProfile::factory()->for($vendor, 'user')->approved()->create();

    $starredCustomer = User::factory()->create([
        'name' => 'Repeat Buyer',
        'email' => 'repeat@example.com',
    ]);

    VendorCustomerStar::query()->create([
        'vendor_user_id' => $vendor->getKey(),
        'customer_id' => $starredCustomer->getKey(),
    ]);

    $this->actingAs($vendor)
        ->get(route('vendor.valued-customers'))
        ->assertOk()
        ->assertSee('Valued customers')
        ->assertSee('Repeat Buyer')
        ->assertSee(route('shop.customers.show', $starredCustomer), false);
});

test('valued customers search filters by customer name', function () {
    $vendor = User::factory()->vendor()->create();
    VendorProfile::factory()->for($vendor, 'user')->approved()->create();

    $matchingCustomer = User::factory()->create(['name' => 'Maria Local']);
    $otherCustomer = User::factory()->create(['name' => 'Jose Buyer']);

    VendorCustomerStar::query()->create([
        'vendor_user_id' => $vendor->getKey(),
        'customer_id' => $matchingCustomer->getKey(),
    ]);

    VendorCustomerStar::query()->create([
        'vendor_user_id' => $vendor->getKey(),
        'customer_id' => $otherCustomer->getKey(),
    ]);

    $this->actingAs($vendor)
        ->get(route('vendor.valued-customers', ['search' => 'Maria']))
        ->assertOk()
        ->assertSee('Maria Local')
        ->assertDontSee('Jose Buyer');
});

test('non-vendors are redirected away from valued customers page', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('vendor.valued-customers'))
        ->assertRedirect(route('customer.dashboard'));
});
