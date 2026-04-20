<?php

use App\Models\Product;
use App\Models\VendorProfile;

test('landing page renders successfully', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Digital palengke')
        ->assertSee('Fresh from the palengke');
});

test('landing page shows a real approved vendor showcase when active listings exist', function () {
    $vendor = VendorProfile::factory()->approved()->create([
        'store_name' => 'Nanay Pilar Produce',
        'store_description' => 'Fresh greens and daily market staples.',
    ]);

    $product = Product::factory()
        ->for($vendor, 'vendor')
        ->active()
        ->create([
            'name' => 'Morning Okra',
            'price' => 89.50,
        ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Featured market stall')
        ->assertSee($vendor->store_name)
        ->assertSee($product->name)
        ->assertSee('PHP 89.50');
});

test('landing page shows a clean fallback showcase when no approved vendor is available', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Featured stalls will appear here as listings go live.')
        ->assertSee('Once the marketplace has live storefronts, this space will highlight a real vendor and real products from the catalog.');
});
