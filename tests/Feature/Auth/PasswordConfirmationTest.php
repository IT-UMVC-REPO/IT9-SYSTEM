<?php

use App\Models\User;

test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.confirm'));

    $response->assertOk();
});

test('users can confirm their password and continue to protected settings pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertRedirect(route('password.confirm'));

    $this->post(route('password.confirm.store'), [
        'password' => 'password',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit', absolute: false));

    $this->get(route('security.edit'))
        ->assertOk();
});

test('users can not confirm their password with invalid credentials', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertRedirect(route('password.confirm'));

    $this->post(route('password.confirm.store'), [
        'password' => 'wrong-password',
    ])
        ->assertSessionHasErrors(['password'])
        ->assertRedirect(route('security.edit', absolute: false));

    $this->get(route('security.edit'))
        ->assertRedirect(route('password.confirm'));
});
