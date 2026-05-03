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
        'Storefront',
    ],
    'admin' => [
        fn () => User::factory()->admin()->create(),
        'admin.dashboard',
        'Vendors',
    ],
]);

test('customer header renders home in mobile navigation surfaces', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('customer.dashboard'));

    $response->assertOk();

    expect(substr_count($response->getContent(), '>Home<'))->toBe(2);
    expect(substr_count($response->getContent(), 'grid-cols-5'))->toBeGreaterThanOrEqual(1);

    $response->assertSee('Mobile primary navigation')
        ->assertSee('Open account menu')
        ->assertSee('ACCOUNT')
        ->assertSee('View Profile')
        ->assertSee('Settings')
        ->assertSee(route('profile.edit'), false);
});

test('vendor and admin headers render the dashboard navigation label in desktop drawer and bottom nav', function (callable $makeUser, string $routeName) {
    $user = $makeUser();

    $response = $this->actingAs($user)->get(route($routeName));

    $response->assertOk();

    expect(substr_count($response->getContent(), '>Dashboard<'))->toBe(3);
    $response->assertSee('Mobile primary navigation');
})->with([
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
