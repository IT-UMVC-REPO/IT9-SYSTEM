<?php

use App\Models\RiderProfile;
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
    'rider' => [
        function () {
            $user = User::factory()->rider()->create();
            RiderProfile::factory()->for($user, 'user')->approved()->create();

            return $user;
        },
        'rider.dashboard',
    ],
]);

test('shared app header shows role-aware navigation', function (callable $makeUser, string $routeName, string $expectedLabel) {
    $user = $makeUser();

    $response = $this->actingAs($user)->get(route($routeName));

    $response->assertOk()
        ->assertSee('LocalPalengke')
        ->assertSee($expectedLabel)
        ->assertSee('Settings');
})->with([
    'customer' => [
        fn () => User::factory()->create(),
        'customer.dashboard',
        'Setup',
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
        'Applications',
    ],
    'rider' => [
        function () {
            $user = User::factory()->rider()->create();
            RiderProfile::factory()->for($user, 'user')->approved()->create();

            return $user;
        },
        'rider.dashboard',
        'Messages',
    ],
]);

test('customer header renders home in mobile navigation surfaces', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('customer.dashboard'));

    $response->assertOk();

    expect(substr_count($response->getContent(), '>Home<'))->toBe(2);
    expect(substr_count($response->getContent(), 'grid-cols-5'))->toBeGreaterThanOrEqual(1);

    $response->assertSee('Mobile primary navigation')
        ->assertSee('Messages')
        ->assertSee(route('messages.inbox'), false)
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

test('admin header shows the messaging quick action', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertSee(route('messages.inbox'), false)
        ->assertSee('title="Messages"', false);

    expect(substr_count($response->getContent(), route('messages.inbox')))->toBe(2);
});

test('rider header shows messaging and riders can open the inbox', function () {
    $rider = User::factory()->rider()->create();
    RiderProfile::factory()->for($rider, 'user')->approved()->create();

    $this->actingAs($rider)
        ->get(route('rider.dashboard'))
        ->assertOk()
        ->assertSee(route('messages.inbox'), false)
        ->assertSee('title="Messages"', false)
        ->assertSee('Mobile primary navigation');

    $this->actingAs($rider)
        ->get(route('messages.inbox'))
        ->assertOk()
        ->assertSee('Messages inbox');
});
