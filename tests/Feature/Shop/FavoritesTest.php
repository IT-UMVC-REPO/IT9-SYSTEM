<?php

use App\Models\Favorite;
use App\Models\User;
use App\Models\VendorProfile;
use Livewire\Livewire;

test('favorites page shows followed vendors', function () {
    $customer = User::factory()->create();
    $otherCustomer = User::factory()->create();
    $followedVendor = VendorProfile::factory()->approved()->create([
        'store_name' => 'Nanay Cora Greens',
    ]);
    $otherVendor = VendorProfile::factory()->approved()->create([
        'store_name' => 'Kuya Ben Seafood',
    ]);

    Favorite::query()->create([
        'customer_id' => $customer->getKey(),
        'vendor_id' => $followedVendor->getKey(),
    ]);

    Favorite::query()->create([
        'customer_id' => $otherCustomer->getKey(),
        'vendor_id' => $otherVendor->getKey(),
    ]);

    $this->actingAs($customer)
        ->get(route('shop.favorites'))
        ->assertOk()
        ->assertSee('Nanay Cora Greens')
        ->assertDontSee('Kuya Ben Seafood');
});

test('follow creates a favorite record', function () {
    $customer = User::factory()->create();
    $vendor = VendorProfile::factory()->approved()->create();

    Livewire::actingAs($customer)
        ->test('vendor.follow-button', ['vendor' => $vendor])
        ->call('follow')
        ->assertDispatched('favorites-updated');

    expect(Favorite::query()->where('customer_id', $customer->getKey())->where('vendor_id', $vendor->getKey())->count())->toBe(1);
});

test('unfollow deletes the favorite record', function () {
    $customer = User::factory()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    $favorite = Favorite::query()->create([
        'customer_id' => $customer->getKey(),
        'vendor_id' => $vendor->getKey(),
    ]);

    Livewire::actingAs($customer)
        ->test('vendor.follow-button', ['vendor' => $vendor])
        ->call('unfollow')
        ->assertDispatched('favorites-updated');

    $this->assertModelMissing($favorite);
});

test('duplicate follow is idempotent', function () {
    $customer = User::factory()->create();
    $vendor = VendorProfile::factory()->approved()->create();

    $component = Livewire::actingAs($customer)
        ->test('vendor.follow-button', ['vendor' => $vendor]);

    $component->call('follow');
    $component->call('follow');

    expect(Favorite::query()->where('customer_id', $customer->getKey())->where('vendor_id', $vendor->getKey())->count())->toBe(1);
});

test('page shows empty state when no favorites exist', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('shop.favorites'))
        ->assertOk()
        ->assertSee('No followed stalls yet')
        ->assertSee('Browse vendors');
});
