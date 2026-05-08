<?php

use App\Enums\TagumCoordinate;

test('tagum coordinates expose a curated named place dataset', function (): void {
    $places = TagumCoordinate::namedPlaces();

    expect($places)->toBeArray()
        ->and(count($places))->toBeGreaterThanOrEqual(60)
        ->and(collect($places)->pluck('address')->unique()->count())->toBe(count($places))
        ->and(collect($places)->pluck('address')->all())->toContain(
            'Tagum City Public Market, Magugpo Poblacion, Tagum City',
            'Bincungan Barangay Center, Tagum City',
            'Dilawan Road, Magugpo East, Tagum City',
        );

    foreach ($places as $place) {
        expect($place)->toHaveKeys(['lat', 'lng', 'address']);
    }
});

test('random place returns coordinates and matching vendor address', function (): void {
    $place = TagumCoordinate::randomPlace();

    expect($place)->toHaveKeys(['lat', 'lng', 'address', 'vendor_address'])
        ->and($place['vendor_address'])->toBe($place['address'])
        ->and(TagumCoordinate::random())->toHaveKeys(['lat', 'lng']);
});
