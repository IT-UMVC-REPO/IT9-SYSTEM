<?php

use App\Models\User;
use App\Models\VendorProfile;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('customer.dashboard', absolute: false));
});

test('shared app header shows role-aware navigation', function (callable $makeUser, string $routeName, string $expectedLabel) {
    $user = $makeUser();

    $response = $this->actingAs($user)->get(route($routeName));

    $response->assertOk()
        ->assertSee('SukiMarket')
        ->assertSee($expectedLabel)
        ->assertSee('Settings');
})->with([
    'customer' => [
        fn () => User::factory()->create(),
        'customer.dashboard',
        'Seller setup',
    ],
    'vendor' => [
        function () {
            $user = User::factory()->vendor()->create();
            VendorProfile::factory()->for($user, 'user')->approved()->create();

            return $user;
        },
        'vendor.dashboard',
        'Sales',
    ],
    'admin' => [
        fn () => User::factory()->admin()->create(),
        'admin.dashboard',
        'Vendors',
    ],
]);
