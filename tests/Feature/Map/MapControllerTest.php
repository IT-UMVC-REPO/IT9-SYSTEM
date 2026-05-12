<?php

use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Support\Str;

test('returns vendor geojson for unauthenticated visitors', function (): void {
    VendorProfile::factory()->approved()->create([
        'lat' => 7.44,
        'lng' => 125.81,
        'store_description' => str_repeat('Fresh produce and seafood from Tagum. ', 8),
    ]);

    $response = $this->getJson('/api/map/vendors')
        ->assertOk()
        ->assertJsonPath('type', 'FeatureCollection')
        ->assertJsonPath('features.0.properties.active_products_count', 0)
        ->assertJsonStructure(['features' => [['type', 'geometry', 'properties']]]);

    expect(Str::length($response->json('features.0.properties.description')))->toBeLessThanOrEqual(123);
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
