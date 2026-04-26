<?php

use App\Models\User;
use App\Models\VendorCustomerStar;
use App\Models\VendorProfile;
use Livewire\Livewire;

test('vendors can star customers', function () {
    $vendor = User::factory()->vendor()->create();
    VendorProfile::factory()->for($vendor, 'user')->approved()->create();
    $customer = User::factory()->create();

    Livewire::actingAs($vendor)
        ->test('customer.star-button', ['customer' => $customer])
        ->call('star')
        ->assertDispatched('customer-stars-updated');

    expect(VendorCustomerStar::query()
        ->where('vendor_user_id', $vendor->getKey())
        ->where('customer_id', $customer->getKey())
        ->count())->toBe(1);
});

test('vendors can unstar customers', function () {
    $vendor = User::factory()->vendor()->create();
    VendorProfile::factory()->for($vendor, 'user')->approved()->create();
    $customer = User::factory()->create();

    VendorCustomerStar::query()->create([
        'vendor_user_id' => $vendor->getKey(),
        'customer_id' => $customer->getKey(),
    ]);

    Livewire::actingAs($vendor)
        ->test('customer.star-button', ['customer' => $customer])
        ->call('unstar')
        ->assertDispatched('customer-stars-updated');

    expect(VendorCustomerStar::query()
        ->where('vendor_user_id', $vendor->getKey())
        ->where('customer_id', $customer->getKey())
        ->exists())->toBeFalse();
});

test('customers cannot use the star button component', function () {
    $customer = User::factory()->create();
    $otherCustomer = User::factory()->create();

    Livewire::actingAs($customer)
        ->test('customer.star-button', ['customer' => $otherCustomer])
        ->assertForbidden();
});
