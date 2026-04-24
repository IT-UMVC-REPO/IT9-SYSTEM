<?php

use App\Models\User;
use App\Models\VendorProfile;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('shared dashboard route redirects users to their portal home and preserves query strings', function (callable $makeUser, string $expectedRouteName) {
    $user = $makeUser();

    $response = $this->actingAs($user)->get(route('dashboard', ['tab' => 'alerts']));

    $response->assertRedirect(route($expectedRouteName, ['tab' => 'alerts'], absolute: false));
})->with([
    'customer' => [
        fn () => User::factory()->create(),
        'customer.dashboard',
    ],
    'vendor' => [
        function () {
            $user = User::factory()->vendor()->create();
            VendorProfile::factory()->for($user, 'user')->approved()->create();

            return $user;
        },
        'vendor.dashboard',
    ],
    'admin' => [
        fn () => User::factory()->admin()->create(),
        'admin.dashboard',
    ],
]);

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

test('customer header renders the home navigation link only once', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('customer.dashboard'));

    $response->assertOk();

    expect(substr_count($response->getContent(), '>Home<'))->toBe(1);
});
