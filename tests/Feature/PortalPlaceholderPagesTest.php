<?php

use App\Models\User;
use App\Models\VendorProfile;

test('customer placeholder pages render minimal tbd shells', function (string $routeName, array|Closure $parameters, string $heading) {
    $user = User::factory()->create();
    $resolvedParameters = $parameters instanceof Closure ? $parameters() : $parameters;

    $response = $this->actingAs($user)->get(route($routeName, $resolvedParameters));

    $response->assertOk()
        ->assertSee($heading)
        ->assertSee('TBD')
        ->assertDontSee('Planned content')
        ->assertDontSee('Linked placeholders');
})->with([
    ['customer.dashboard', [], 'Customer dashboard'],
    ['shop.favorites', [], 'Favourites'],
    ['shop.vendors', [], 'Market stalls'],
    ['shop.vendors.show', fn (): array => ['vendorProfile' => VendorProfile::factory()->approved()->create()], 'Vendor profile'],
]);

test('vendor placeholder pages render minimal tbd shells', function (string $routeName, array $parameters, string $heading) {
    $user = User::factory()->vendor()->create();
    VendorProfile::factory()->for($user, 'user')->approved()->create();

    $response = $this->actingAs($user)->get(route($routeName, $parameters));

    $response->assertOk()
        ->assertSee($heading)
        ->assertSee('TBD')
        ->assertDontSee('Planned content')
        ->assertDontSee('Linked placeholders');
})->with([
    ['vendor.dashboard', [], 'Vendor dashboard'],
    ['vendor.orders', [], 'Order management'],
    ['vendor.orders.show', ['orderReference' => 'sample-order'], 'Order detail'],
    ['vendor.sales', [], 'Sales summary'],
]);

test('admin placeholder pages render minimal tbd shells', function (string $routeName, array $parameters, string $heading) {
    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user)->get(route($routeName, $parameters));

    $response->assertOk()
        ->assertSee($heading)
        ->assertSee('TBD')
        ->assertDontSee('Planned content')
        ->assertDontSee('Linked placeholders');
})->with([
    ['admin.orders', [], 'Marketplace order oversight'],
]);

test('shared message placeholders render for customers and vendors', function (callable $makeUser) {
    $user = $makeUser();

    $this->actingAs($user)->get(route('messages.inbox'))
        ->assertOk()
        ->assertSee('Messages inbox')
        ->assertSee('TBD')
        ->assertDontSee('Planned content')
        ->assertSee(route($user->homeRoute()), false);

    $this->actingAs($user)->get(route('messages.conversation', ['conversationReference' => 'sample-thread']))
        ->assertOk()
        ->assertSee('Conversation detail')
        ->assertSee('TBD')
        ->assertDontSee('Linked placeholders')
        ->assertSee(route($user->homeRoute()), false);
})->with([
    'customer' => [
        fn () => User::factory()->create(),
    ],
    'vendor' => [
        function () {
            $user = User::factory()->vendor()->create();
            VendorProfile::factory()->for($user, 'user')->approved()->create();

            return $user;
        },
    ],
]);

test('pending vendor storefront placeholder returns a not found response', function () {
    $user = User::factory()->create();
    $vendorProfile = VendorProfile::factory()->create();

    $this->actingAs($user)->get(route('shop.vendors.show', $vendorProfile))
        ->assertNotFound();
});
