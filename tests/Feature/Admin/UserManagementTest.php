<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VendorProfile;
use Livewire\Livewire;

test('admins see the user management page and all users', function () {
    $admin = User::factory()->admin()->create([
        'name' => 'Admin Lead',
    ]);
    $customer = User::factory()->create([
        'name' => 'Cora Customer',
        'email' => 'cora@example.test',
    ]);
    $vendorUser = User::factory()->vendor()->create([
        'name' => 'Vicky Vendor',
        'email' => 'vicky@example.test',
    ]);

    VendorProfile::factory()->for($vendorUser, 'user')->approved()->create([
        'store_name' => 'Vicky Greens',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users'))
        ->assertOk()
        ->assertSee('User management')
        ->assertSee($customer->name)
        ->assertSee($vendorUser->name)
        ->assertSee('Vicky Greens');
});

test('user search narrows results by name and email', function () {
    $admin = User::factory()->admin()->create();
    $matchingUser = User::factory()->create([
        'name' => 'Lita Mercado',
        'email' => 'lita@example.test',
    ]);
    $otherUser = User::factory()->create([
        'name' => 'Mario Santos',
        'email' => 'mario@example.test',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users', ['search' => 'Lita']))
        ->assertOk()
        ->assertSee($matchingUser->name)
        ->assertDontSee($otherUser->name);

    $this->actingAs($admin)
        ->get(route('admin.users', ['search' => 'mario@example.test']))
        ->assertOk()
        ->assertSee($otherUser->name)
        ->assertDontSee($matchingUser->name);
});

test('role filter narrows the user list', function () {
    $admin = User::factory()->admin()->create();
    $vendor = User::factory()->vendor()->create([
        'name' => 'Vendor Mila',
    ]);
    $customer = User::factory()->create([
        'name' => 'Customer Rina',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users', ['roleFilter' => UserRole::Vendor->value]))
        ->assertOk()
        ->assertSee($vendor->name)
        ->assertDontSee($customer->name);
});

test('admins can deactivate accounts and inactive users can no longer log in', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create([
        'email' => 'deactivate-me@example.test',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('toggleActiveStatus', $user->id);

    expect($user->fresh()->is_active)->toBeFalse();

    $this->post(route('logout'));
    $this->assertGuest();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('admins can not deactivate themselves', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('toggleActiveStatus', $admin->id);

    expect($admin->fresh()->is_active)->toBeTrue();
});

test('non admin users are redirected away from user management', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.users'))
        ->assertRedirect(route('customer.dashboard'));
});
