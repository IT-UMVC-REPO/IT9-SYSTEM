<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;

test('authenticated customers can view the storefront', function () {
    $customer = User::factory()->create();
    $vendor = VendorProfile::factory()->approved()->create([
        'store_name' => 'Nanay Cora Produce',
    ]);
    $category = Category::factory()->create([
        'name' => 'Vegetables',
    ]);
    $product = Product::factory()
        ->for($vendor, 'vendor')
        ->for($category)
        ->active()
        ->create([
            'name' => 'Fresh Okra Bundle',
            'description' => 'Picked this morning from the market.',
            'stock_quantity' => 12,
        ]);

    $response = $this->actingAs($customer)->get(route('shop.home'));

    $response->assertOk()
        ->assertSee('A brighter market floor for your next suki run.')
        ->assertSee($product->name)
        ->assertSee($vendor->store_name)
        ->assertSee($category->name);
});

test('storefront shows only active products from approved vendors', function () {
    $customer = User::factory()->create();
    $visibleVendor = VendorProfile::factory()->approved()->create([
        'store_name' => 'Suki Greens',
    ]);
    $visibleProduct = Product::factory()
        ->for($visibleVendor, 'vendor')
        ->active()
        ->create([
            'name' => 'Market Tomatoes',
        ]);

    $inactiveProduct = Product::factory()
        ->for($visibleVendor, 'vendor')
        ->create([
            'name' => 'Hidden Sitaw',
        ]);

    $pendingVendor = VendorProfile::factory()->create([
        'store_name' => 'Pending Produce',
    ]);
    $pendingProduct = Product::factory()
        ->for($pendingVendor, 'vendor')
        ->active()
        ->create([
            'name' => 'Pending Kalabasa',
        ]);

    $rejectedVendor = VendorProfile::factory()->rejected()->create([
        'store_name' => 'Rejected Harvest',
    ]);
    $rejectedProduct = Product::factory()
        ->for($rejectedVendor, 'vendor')
        ->active()
        ->create([
            'name' => 'Rejected Pechay',
        ]);

    $response = $this->actingAs($customer)->get(route('shop.home'));

    $response->assertOk()
        ->assertSee($visibleProduct->name)
        ->assertDontSee($inactiveProduct->name)
        ->assertDontSee($pendingProduct->name)
        ->assertDontSee($rejectedProduct->name);
});

test('storefront search filters visible products by name and description', function () {
    $customer = User::factory()->create();
    $vendor = VendorProfile::factory()->approved()->create();

    $matchingProduct = Product::factory()
        ->for($vendor, 'vendor')
        ->active()
        ->create([
            'name' => 'Ampalaya',
            'description' => 'Fresh bitter gourd from Davao farms.',
        ]);

    $otherProduct = Product::factory()
        ->for($vendor, 'vendor')
        ->active()
        ->create([
            'name' => 'Bananas',
            'description' => 'Sweet saba for merienda.',
        ]);

    $response = $this->actingAs($customer)->get(route('shop.home', [
        'search' => 'bitter gourd',
    ]));

    $response->assertOk()
        ->assertSee($matchingProduct->name)
        ->assertDontSee($otherProduct->name);
});

test('storefront category filter narrows the product listing', function () {
    $customer = User::factory()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    $vegetables = Category::factory()->create(['name' => 'Vegetables']);
    $fruits = Category::factory()->create(['name' => 'Fruits']);

    $vegetableProduct = Product::factory()
        ->for($vendor, 'vendor')
        ->for($vegetables)
        ->active()
        ->create([
            'name' => 'Eggplant',
        ]);

    $fruitProduct = Product::factory()
        ->for($vendor, 'vendor')
        ->for($fruits)
        ->active()
        ->create([
            'name' => 'Mango',
        ]);

    $response = $this->actingAs($customer)->get(route('shop.home', [
        'category' => $vegetables->id,
    ]));

    $response->assertOk()
        ->assertSee($vegetableProduct->name)
        ->assertDontSee($fruitProduct->name);
});

test('customers can open a visible product detail page', function () {
    $customer = User::factory()->create();
    $vendor = VendorProfile::factory()->approved()->create([
        'store_name' => 'Bajada Seafood Stall',
        'store_description' => 'Fresh seafood for your daily meals.',
    ]);
    $category = Category::factory()->create([
        'name' => 'Seafood',
    ]);
    $product = Product::factory()
        ->for($vendor, 'vendor')
        ->for($category)
        ->active()
        ->create([
            'name' => 'Bangus',
            'description' => 'Cleaned and ready to cook.',
            'stock_quantity' => 8,
        ]);

    $response = $this->actingAs($customer)->get(route('shop.products.show', $product));

    $response->assertOk()
        ->assertSee($product->name)
        ->assertSee($product->description)
        ->assertSee($vendor->store_name)
        ->assertSee($vendor->store_description)
        ->assertSee($category->name)
        ->assertSee('8');
});

test('invisible products return not found on the detail page', function () {
    $customer = User::factory()->create();
    $approvedVendor = VendorProfile::factory()->approved()->create();
    $pendingVendor = VendorProfile::factory()->create();
    $rejectedVendor = VendorProfile::factory()->rejected()->create();

    $hiddenProducts = [
        Product::factory()->for($approvedVendor, 'vendor')->create(),
        Product::factory()->for($pendingVendor, 'vendor')->active()->create(),
        Product::factory()->for($rejectedVendor, 'vendor')->active()->create(),
    ];

    foreach ($hiddenProducts as $product) {
        $this->actingAs($customer)
            ->get(route('shop.products.show', $product))
            ->assertNotFound();
    }
});

test('storefront shows an empty state when no visible products match the filters', function () {
    $customer = User::factory()->create();
    $vendor = VendorProfile::factory()->approved()->create();

    Product::factory()
        ->for($vendor, 'vendor')
        ->active()
        ->create([
            'name' => 'Talong',
            'description' => 'Fresh purple eggplant.',
        ]);

    $response = $this->actingAs($customer)->get(route('shop.home', [
        'search' => 'dragon fruit',
    ]));

    $response->assertOk()
        ->assertSee('No matching market finds yet')
        ->assertDontSee('Talong');
});
