<?php

use App\Enums\VendorStatus;
use App\Models\RiderProfile;
use App\Models\User;
use App\Models\VendorProfile;

test('admins can open the consolidated applications hub', function () {
    $admin = User::factory()->admin()->create();

    VendorProfile::factory()->create([
        'store_name' => 'Pending Produce',
        'status' => VendorStatus::Pending,
    ]);
    VendorProfile::factory()->approved()->create([
        'store_name' => 'Approved Greens',
    ]);
    VendorProfile::factory()->rejected()->create([
        'store_name' => 'Rejected Seafood',
    ]);

    RiderProfile::factory()->create([
        'status' => 'pending',
    ]);
    RiderProfile::factory()->approved()->create();
    RiderProfile::factory()->create([
        'status' => 'inactive',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.applications'))
        ->assertOk()
        ->assertSee('Applications')
        ->assertSee('Vendor applications')
        ->assertSee('Rider applications')
        ->assertSee(route('admin.vendors'), false)
        ->assertSee(route('admin.riders'), false)
        ->assertSee('Open vendor queue')
        ->assertSee('Open rider queue');
});

test('non admin users are redirected away from the applications hub', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.applications'))
        ->assertRedirect(route('customer.dashboard'));
});
