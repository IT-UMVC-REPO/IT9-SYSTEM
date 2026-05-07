<?php

use App\Models\User;
use App\Models\VendorProfile;

test('returns vendor geojson for unauthenticated visitors', function (): void {
    VendorProfile::factory()->approved()->create([
        'lat' => 7.44,
        'lng' => 125.81,
    ]);

    $this->getJson('/api/map/vendors')
        ->assertOk()
        ->assertJsonPath('type', 'FeatureCollection')
        ->assertJsonStructure(['features' => [['type', 'geometry', 'properties']]]);
});

test('returns 403 for customers requesting the customer map endpoint', function (): void {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->getJson('/api/map/customers')
        ->assertForbidden();
});

test('returns customer geojson to an approved vendor', function (): void {
    $vendor = VendorProfile::factory()->approved()->create([
        'lat' => 7.44,
        'lng' => 125.81,
    ]);

    $vendor->user->update([
        'lat' => 7.44,
        'lng' => 125.81,
    ]);

    $this->actingAs($vendor->user)
        ->getJson('/api/map/customers')
        ->assertOk()
        ->assertJsonPath('type', 'FeatureCollection');
});
