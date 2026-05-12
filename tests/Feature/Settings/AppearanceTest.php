<?php

use App\Models\User;
use Livewire\Livewire;

test('appearance settings page renders', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertSee('Theme preference')
        ->assertSee('Brand color');
});

test('user can save a valid brand color', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.appearance')
        ->set('brand_color', '#7c3aed')
        ->call('saveBrandColor')
        ->assertHasNoErrors();

    expect($user->refresh()->brand_color)->toBe('#7c3aed');
});

test('invalid hex is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.appearance')
        ->set('brand_color', 'notahex')
        ->call('saveBrandColor')
        ->assertHasErrors(['brand_color']);
});

test('brand color can be reset to null', function () {
    $user = User::factory()->create(['brand_color' => '#7c3aed']);

    $this->actingAs($user);

    Livewire::test('pages::settings.appearance')
        ->call('resetBrandColor')
        ->assertHasNoErrors();

    expect($user->refresh()->brand_color)->toBeNull();
});

test('authenticated page head outputs user brand color vars', function () {
    $user = User::factory()->create(['brand_color' => '#e11d48']);

    $this->actingAs($user)
        ->get(route('shop.home'))
        ->assertOk()
        ->assertSee('--brand-600', false)
        ->assertSee('--color-accent', false);
});

test('unauthenticated pages do not output brand color style block', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('--brand-600', false);
});
