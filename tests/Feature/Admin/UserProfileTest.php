<?php

use App\Models\User;
use App\Models\VendorProfile;

test('admins can view a user profile page', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create([
        'name' => 'Test Customer',
        'email' => 'customer@example.com',
    ]);

    VendorProfile::factory()->for($user, 'user')->create([
        'store_name' => 'Customer Vendor Profile',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.show', $user))
        ->assertOk()
        ->assertSee('User profile')
        ->assertSee($user->name)
        ->assertSee($user->email)
        ->assertSee('Customer Vendor Profile');
});

test('non admins are redirected away from admin user profile pages', function () {
    $customer = User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.users.show', $user))
        ->assertRedirect(route('customer.dashboard'));
});
