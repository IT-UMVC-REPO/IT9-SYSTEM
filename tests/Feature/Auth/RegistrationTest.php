<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('customer.dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Customer)
        ->and($user->is_active)->toBeTrue()
        ->and($user->phone)->toBeNull()
        ->and($user->address)->toBeNull()
        ->and($user->profile_image)->toBeNull()
        ->and($user->vendorProfile)->toBeNull();
});

test('new users are redirected to their intended page after registration', function () {
    $this->get(route('shop.cart'))
        ->assertRedirect(route('login'));

    $response = $this->post(route('register.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('shop.cart', absolute: false));

    $this->assertAuthenticated();
});
