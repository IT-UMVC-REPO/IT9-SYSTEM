<?php

use App\Models\User;

test('authenticated users visiting guest routes are redirected to their portal', function () {
    $user = User::factory()->vendor()->create();

    $response = $this->actingAs($user)->get(route('login'));

    $response->assertRedirect(route('vendor.dashboard', absolute: false));
});

test('users are redirected to their own portal when they hit another role route', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.dashboard'));

    $response->assertRedirect(route('shop.home', absolute: false));
});
