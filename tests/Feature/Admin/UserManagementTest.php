<?php

use App\Models\User;
use App\Enums\UserRole; 

beforeEach(function () {
    $this->admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);
});

test('only admins can access the user management page', function () {
    $student = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($student)
        ->get(route('admin.users'))
        ->assertRedirect(); 

    // Admin should be allowed
    $this->actingAs($this->admin)
        ->get(route('admin.users'))
        ->assertOk();
});

test('admin can search and filter users', function () {
    User::factory()->create(['name' => 'Specific User', 'role' => UserRole::Vendor]);

    $this->actingAs($this->admin)
        ->get(route('admin.users', [
            'search' => 'Specific', 
            'role' => UserRole::Vendor->value
        ]))
        ->assertSee('Specific User');
});

test('admin can toggle a user activation status', function () {
    $targetUser = User::factory()->create(['is_active' => true]);

    $this->actingAs($this->admin)
        ->patch(route('admin.users.toggle', $targetUser))
        ->assertRedirect();

    
    expect($targetUser->fresh()->is_active)->toBe(false);
});