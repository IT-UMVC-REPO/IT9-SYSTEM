<?php

use App\Models\User;

test('authenticated users see the branded not found page with a dashboard action', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/admin/daskjjj');

    $response->assertNotFound()
        ->assertSee('This page doesn&#039;t exist', false)
        ->assertSee('Return to dashboard')
        ->assertSee(route('admin.dashboard'), false)
        ->assertSee('No stall here');
});

test('guests see the branded not found page with a login action', function () {
    $response = $this->get('/shop/not-a-real-market-lane');

    $response->assertNotFound()
        ->assertSee('This page doesn&#039;t exist', false)
        ->assertSee('Log in to continue')
        ->assertSee(route('login'), false)
        ->assertSee('Go home');
});
