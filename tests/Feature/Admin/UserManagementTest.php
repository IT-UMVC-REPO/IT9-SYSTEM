<?php

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Report;
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
        ->assertSee('User management sections')
        ->assertSee(route('admin.reports'), false)
        ->assertDontSee(route('admin.orders'), false)
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

test('make admin command creates a verified active administrator', function () {
    $this->artisan('make:admin', [
        'name' => 'Ana Admin',
        'email' => 'ana.admin@example.test',
        'password' => 'password',
    ])
        ->expectsOutputToContain('Admin account created')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'ana.admin@example.test')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('Ana Admin')
        ->and($admin->role)->toBe(UserRole::Admin)
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and($admin->homeRoute())->toBe('admin.dashboard');
});

test('admins can create another admin from the user management modal', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.create-admin-modal')
        ->set('name', 'Modal Admin')
        ->set('email', 'modal.admin@example.test')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('createAdmin')
        ->assertHasNoErrors()
        ->assertDispatched('admin-created');

    $createdAdmin = User::query()->where('email', 'modal.admin@example.test')->first();

    expect($createdAdmin)->not->toBeNull()
        ->and($createdAdmin->role)->toBe(UserRole::Admin)
        ->and($createdAdmin->is_active)->toBeTrue()
        ->and($createdAdmin->email_verified_at)->not->toBeNull();
});

test('admin user list shows actionable vendor and report markers', function () {
    $admin = User::factory()->admin()->create();
    $approvedVendorUser = User::factory()->vendor()->create([
        'name' => 'Approved Vendor',
    ]);
    $pendingVendorUser = User::factory()->vendor()->create([
        'name' => 'Pending Vendor',
    ]);
    $rejectedVendorUser = User::factory()->vendor()->create([
        'name' => 'Rejected Vendor',
    ]);
    $watchlistedCustomer = User::factory()->create([
        'name' => 'Watchlisted Customer',
    ]);

    VendorProfile::factory()->for($approvedVendorUser, 'user')->approved()->create();
    VendorProfile::factory()->for($pendingVendorUser, 'user')->create();
    VendorProfile::factory()->for($rejectedVendorUser, 'user')->rejected()->create();
    Report::factory()->open()->create([
        'reporter_id' => $admin->getKey(),
        'reported_user_id' => $watchlistedCustomer->getKey(),
        'status' => ReportStatus::Open,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users'))
        ->assertOk()
        ->assertSee('Approved Vendor')
        ->assertSee('Pending')
        ->assertSee('Rejected')
        ->assertSee('Watchlist')
        ->assertDontSee('Vendor: Approved');
});

test('admin users are gracefully redirected away from shop-only routes', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('shop.cart'))
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('toast.warning');
});

test('non admin users are redirected away from user management', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.users'))
        ->assertRedirect(route('customer.dashboard'));
});
