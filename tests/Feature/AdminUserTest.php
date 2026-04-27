<?php

use App\Models\User;
use App\Enums\UserRole;

test('admin can filter users by search and role', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $target = User::factory()->create(['name' => 'John Doe', 'role' => UserRole::Vendor]);

    $this->actingAs($admin)
        ->get(route('admin.users', ['search' => 'John', 'role' => 'vendor']))
        ->assertStatus(200)
        ->assertSee('John Doe');
});

test('admin can toggle activation status', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.users.toggle', $user));

    expect($user->fresh()->is_active)->toBeFalse();
});