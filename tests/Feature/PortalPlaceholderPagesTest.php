<?php

use App\Models\User;
use App\Models\VendorProfile;

test('customer placeholder pages render minimal tbd shells', function (string $routeName, array $parameters, string $heading) {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route($routeName, $parameters));

    $response->assertOk()
        ->assertSee($heading)
        ->assertSee('TBD')
        ->assertDontSee('Planned content')
        ->assertDontSee('Linked placeholders');
})->with([
    ['customer.dashboard', [], 'Customer dashboard'],
    ['shop.cart', [], 'Cart'],
    ['shop.checkout', [], 'Checkout'],
    ['shop.orders', [], 'Order history'],
    ['shop.orders.show', ['orderReference' => 'sample-order'], 'Order detail'],
    ['shop.favorites', [], 'Favourites'],
    ['vendor.registration', [], 'Vendor registration'],
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
    ['vendor.products', [], 'Product management'],
    ['vendor.products.create', [], 'Create product'],
    ['vendor.products.edit', ['productReference' => 'sample-product'], 'Edit product'],
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
    ['admin.dashboard', [], 'Admin dashboard'],
    ['admin.vendors', [], 'Vendor approvals'],
    ['admin.vendors.show', ['vendorReference' => 'sample-vendor'], 'Vendor review detail'],
    ['admin.users', [], 'User management'],
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
