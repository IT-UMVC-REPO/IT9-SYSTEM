<?php

use App\Models\User;
use App\Models\VendorProfile;

test('authenticated users visiting guest routes are redirected to their portal', function () {
    $user = User::factory()->vendor()->create();
    VendorProfile::factory()->for($user, 'user')->approved()->create();

    $response = $this->actingAs($user)->get(route('login'));

    $response->assertRedirect(route('vendor.dashboard', absolute: false));
});

test('users are redirected to their own portal when they hit another role route', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.dashboard'));

    $response->assertRedirect(route('customer.dashboard', absolute: false));
});

test('non approved vendors are redirected to the customer portal when visiting vendor routes', function () {
    $user = User::factory()->vendor()->create();
    VendorProfile::factory()->for($user, 'user')->create();

    $response = $this->actingAs($user)->get(route('vendor.dashboard'));

    $response->assertRedirect(route('customer.dashboard', absolute: false));
});

test('approved vendors can access customer shopping routes', function () {
    $user = User::factory()->vendor()->create();
    VendorProfile::factory()->for($user, 'user')->approved()->create();

    $this->actingAs($user)
        ->get(route('customer.dashboard'))
        ->assertOk()
        ->assertSee('Browse storefront');

    $this->actingAs($user)
        ->get(route('shop.vendors'))
        ->assertOk()
        ->assertSee('Browse market stalls');
});
