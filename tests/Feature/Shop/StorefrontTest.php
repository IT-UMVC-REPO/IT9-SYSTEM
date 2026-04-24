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
    $vegetables = Category::factory()->topLevel()->create([
        'name' => 'Vegetables',
        'slug' => 'vegetables',
    ]);
    $category = Category::factory()->childOf($vegetables)->create([
        'name' => 'Leafy Greens',
        'slug' => 'leafy-greens',
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

test('storefront shows only child categories with visible products in the category filter', function () {
    $customer = User::factory()->create();
    $approvedVendor = VendorProfile::factory()->approved()->create();
    $pendingVendor = VendorProfile::factory()->create();
    $vegetables = Category::factory()->topLevel()->create([
        'name' => 'Vegetables',
        'slug' => 'vegetables',
    ]);
    $leafyGreens = Category::factory()->childOf($vegetables)->create([
        'name' => 'Leafy Greens',
        'slug' => 'leafy-greens',
    ]);
    $rootCrops = Category::factory()->childOf($vegetables)->create([
        'name' => 'Root Crops',
        'slug' => 'root-crops',
    ]);
    $seasonalPicks = Category::factory()->childOf($vegetables)->create([
        'name' => 'Seasonal Picks',
        'slug' => 'seasonal-picks',
    ]);

    Product::factory()
        ->for($approvedVendor, 'vendor')
        ->for($leafyGreens)
        ->active()
        ->create();

    Product::factory()
        ->for($approvedVendor, 'vendor')
        ->for($rootCrops)
        ->create();

    Product::factory()
        ->for($pendingVendor, 'vendor')
        ->for($seasonalPicks)
        ->active()
        ->create();

    $response = $this->actingAs($customer)->get(route('shop.home'));

    $response->assertOk()
        ->assertSee($vegetables->name)
        ->assertSee($leafyGreens->name)
        ->assertDontSee($rootCrops->name)
        ->assertDontSee($seasonalPicks->name);
});

test('storefront renders the branded pagination controls', function () {
    $customer = User::factory()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    $vegetables = Category::factory()->topLevel()->create([
        'name' => 'Vegetables',
        'slug' => 'vegetables',
    ]);

    Product::factory()
        ->count(13)
        ->for($vendor, 'vendor')
        ->for($vegetables)
        ->active()
        ->create();

    $response = $this->actingAs($customer)->get(route('shop.home'));

    $response->assertOk()
        ->assertSee('Storefront pagination', false)
        ->assertSee('Browse more listings')
        ->assertSee('Showing 1 to 12 of 13')
        ->assertSee('results');
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
    $vegetables = Category::factory()->topLevel()->create([
        'name' => 'Vegetables',
        'slug' => 'vegetables',
    ]);
    $leafyGreens = Category::factory()->childOf($vegetables)->create([
        'name' => 'Leafy Greens',
        'slug' => 'leafy-greens',
    ]);
    $rootCrops = Category::factory()->childOf($vegetables)->create([
        'name' => 'Root Crops',
        'slug' => 'root-crops',
    ]);
    $fruits = Category::factory()->topLevel()->create([
        'name' => 'Fruits',
        'slug' => 'fruits',
    ]);
    $tropicalFruits = Category::factory()->childOf($fruits)->create([
        'name' => 'Tropical Fruits',
        'slug' => 'tropical-fruits',
    ]);

    $vegetableProduct = Product::factory()
        ->for($vendor, 'vendor')
        ->for($leafyGreens)
        ->active()
        ->create([
            'name' => 'Eggplant',
        ]);
    $secondVegetableProduct = Product::factory()
        ->for($vendor, 'vendor')
        ->for($rootCrops)
        ->active()
        ->create([
            'name' => 'Sweet Potato',
        ]);

    $fruitProduct = Product::factory()
        ->for($vendor, 'vendor')
        ->for($tropicalFruits)
        ->active()
        ->create([
            'name' => 'Mango',
        ]);

    $response = $this->actingAs($customer)->get(route('shop.home', [
        'category' => $vegetables->id,
    ]));

    $response->assertOk()
        ->assertSee($vegetableProduct->name)
        ->assertSee($secondVegetableProduct->name)
        ->assertDontSee($fruitProduct->name);
});

test('storefront leaf category filter narrows the product listing to the selected subcategory', function () {
    $customer = User::factory()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    $vegetables = Category::factory()->topLevel()->create([
        'name' => 'Vegetables',
        'slug' => 'vegetables',
    ]);
    $leafyGreens = Category::factory()->childOf($vegetables)->create([
        'name' => 'Leafy Greens',
        'slug' => 'leafy-greens',
    ]);
    $rootCrops = Category::factory()->childOf($vegetables)->create([
        'name' => 'Root Crops',
        'slug' => 'root-crops',
    ]);

    $leafyProduct = Product::factory()
        ->for($vendor, 'vendor')
        ->for($leafyGreens)
        ->active()
        ->create([
            'name' => 'Pechay',
        ]);
    $rootCropProduct = Product::factory()
        ->for($vendor, 'vendor')
        ->for($rootCrops)
        ->active()
        ->create([
            'name' => 'Cassava',
        ]);

    $response = $this->actingAs($customer)->get(route('shop.home', [
        'category' => $leafyGreens->id,
    ]));

    $response->assertOk()
        ->assertSee($leafyProduct->name)
        ->assertDontSee($rootCropProduct->name);
});

test('customers can open a visible product detail page', function () {
    $customer = User::factory()->create();
    $vendor = VendorProfile::factory()->approved()->create([
        'store_name' => 'Bajada Seafood Stall',
        'store_description' => 'Fresh seafood for your daily meals.',
    ]);
    $seafood = Category::factory()->topLevel()->create([
        'name' => 'Seafood',
        'slug' => 'seafood',
    ]);
    $category = Category::factory()->childOf($seafood)->create([
        'name' => 'Fresh Fish',
        'slug' => 'fresh-fish',
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
