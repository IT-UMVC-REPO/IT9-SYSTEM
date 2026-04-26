<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use Livewire\Livewire;

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
        ->assertDontSee('<html lang="'.str_replace('_', '-', app()->getLocale()).'" x-cloak>', false)
        ->assertSee('A brighter market floor for your next suki run.')
        ->assertSee('wire:model.live.debounce.250ms="search"', false)
        ->assertDontSee('class="brand-button-primary w-full">Search', false)
        ->assertSee(route('shop.vendors'), false)
        ->assertSee(route('shop.vendors.show', $vendor), false)
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

test('live storefront search filters visible products by name and description', function () {
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

    Livewire::actingAs($customer)
        ->test('pages::shop.catalog-browser')
        ->set('search', 'bitter gourd')
        ->assertSee($matchingProduct->name)
        ->assertDontSee($otherProduct->name);
});

test('live storefront category filter narrows the product listing', function () {
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

    Livewire::actingAs($customer)
        ->test('pages::shop.catalog-browser')
        ->set('selectedCategory', (string) $vegetables->id)
        ->assertSee($vegetableProduct->name)
        ->assertSee($secondVegetableProduct->name)
        ->assertDontSee($fruitProduct->name);
});

test('live storefront max price filter narrows the product listing', function () {
    $customer = User::factory()->create();
    $vendor = VendorProfile::factory()->approved()->create();

    $budgetProduct = Product::factory()
        ->for($vendor, 'vendor')
        ->active()
        ->create([
            'name' => 'Budget Talong',
            'price' => 75,
        ]);

    $premiumProduct = Product::factory()
        ->for($vendor, 'vendor')
        ->active()
        ->create([
            'name' => 'Premium Tuna',
            'price' => 250,
        ]);

    Livewire::actingAs($customer)
        ->test('pages::shop.catalog-browser')
        ->set('maxPrice', '100')
        ->assertSee($budgetProduct->name)
        ->assertDontSee($premiumProduct->name);
});

test('live storefront sort orders products', function (string $sort, array $expectedOrder) {
    $customer = User::factory()->create();
    $vendor = VendorProfile::factory()->approved()->create();

    Product::factory()
        ->for($vendor, 'vendor')
        ->active()
        ->create([
            'name' => 'Market Ampalaya',
            'price' => 90,
        ]);

    Product::factory()
        ->for($vendor, 'vendor')
        ->active()
        ->create([
            'name' => 'Budget Talong',
            'price' => 45,
        ]);

    Product::factory()
        ->for($vendor, 'vendor')
        ->active()
        ->create([
            'name' => 'Premium Salmon',
            'price' => 280,
        ]);

    Livewire::actingAs($customer)
        ->test('pages::shop.catalog-browser')
        ->set('sort', $sort)
        ->assertSeeInOrder($expectedOrder);
})->with([
    'price ascending' => ['price_asc', ['Budget Talong', 'Market Ampalaya', 'Premium Salmon']],
    'price descending' => ['price_desc', ['Premium Salmon', 'Market Ampalaya', 'Budget Talong']],
    'name ascending' => ['name_asc', ['Budget Talong', 'Market Ampalaya', 'Premium Salmon']],
]);

test('live storefront shows an empty state when no visible products match the filters', function () {
    $customer = User::factory()->create();
    $vendor = VendorProfile::factory()->approved()->create();

    Product::factory()
        ->for($vendor, 'vendor')
        ->active()
        ->create([
            'name' => 'Talong',
            'description' => 'Fresh purple eggplant.',
        ]);

    Livewire::actingAs($customer)
        ->test('pages::shop.catalog-browser')
        ->set('search', 'dragon fruit')
        ->assertSee('No matching market finds yet')
        ->assertDontSee('Talong');
});

test('live storefront clear filters restores the default catalog state', function () {
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
    $seafood = Category::factory()->topLevel()->create([
        'name' => 'Seafood',
        'slug' => 'seafood',
    ]);
    $freshFish = Category::factory()->childOf($seafood)->create([
        'name' => 'Fresh Fish',
        'slug' => 'fresh-fish',
    ]);

    $filteredProduct = Product::factory()
        ->for($vendor, 'vendor')
        ->for($leafyGreens)
        ->active()
        ->create([
            'name' => 'Pechay',
            'price' => 40,
        ]);

    $otherProduct = Product::factory()
        ->for($vendor, 'vendor')
        ->for($freshFish)
        ->active()
        ->create([
            'name' => 'Bangus',
            'price' => 180,
        ]);

    Livewire::actingAs($customer)
        ->test('pages::shop.catalog-browser')
        ->set([
            'search' => 'Pechay',
            'selectedCategory' => (string) $vegetables->id,
            'maxPrice' => '50',
            'sort' => 'name_asc',
        ])
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('selectedCategory', '')
        ->assertSet('maxPrice', '')
        ->assertSet('sort', '')
        ->assertSee($filteredProduct->name)
        ->assertSee($otherProduct->name);
});

test('live storefront resets pagination when filters change from page two', function () {
    $customer = User::factory()->create();
    $vendor = VendorProfile::factory()->approved()->create();

    Product::factory()
        ->for($vendor, 'vendor')
        ->active()
        ->create([
            'name' => '00 Ampalaya Special',
        ]);

    Product::factory()
        ->count(13)
        ->for($vendor, 'vendor')
        ->active()
        ->sequence(fn ($sequence) => [
            'name' => 'Regular Product '.str_pad((string) ($sequence->index + 1), 2, '0', STR_PAD_LEFT),
        ])
        ->create();

    Livewire::actingAs($customer)
        ->withQueryParams([
            'page' => 2,
            'sort' => 'name_asc',
        ])
        ->test('pages::shop.catalog-browser')
        ->assertDontSee('00 Ampalaya Special')
        ->set('search', 'Ampalaya')
        ->assertSee('00 Ampalaya Special');
});

test('live storefront hydrates filters from url query parameters', function () {
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
    $fruits = Category::factory()->topLevel()->create([
        'name' => 'Fruits',
        'slug' => 'fruits',
    ]);
    $tropicalFruits = Category::factory()->childOf($fruits)->create([
        'name' => 'Tropical Fruits',
        'slug' => 'tropical-fruits',
    ]);

    $matchingProduct = Product::factory()
        ->for($vendor, 'vendor')
        ->for($leafyGreens)
        ->active()
        ->create([
            'name' => 'Fresh Ampalaya',
            'price' => 80,
        ]);

    $filteredOutProduct = Product::factory()
        ->for($vendor, 'vendor')
        ->for($tropicalFruits)
        ->active()
        ->create([
            'name' => 'Fresh Mango',
            'price' => 150,
        ]);

    Livewire::actingAs($customer)
        ->withQueryParams([
            'search' => 'Fresh',
            'category' => (string) $vegetables->id,
            'max_price' => '100',
            'sort' => 'name_asc',
        ])
        ->test('pages::shop.catalog-browser')
        ->assertSet('search', 'Fresh')
        ->assertSet('selectedCategory', (string) $vegetables->id)
        ->assertSet('maxPrice', '100')
        ->assertSet('sort', 'name_asc')
        ->assertSee($matchingProduct->name)
        ->assertDontSee($filteredOutProduct->name);
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
        ->assertSee('8')
        ->assertSee('Bring this stall to your cart')
        ->assertSee('Message vendor')
        ->assertSee('Cash on delivery')
        ->assertSee('GCash & Maya')
        ->assertSee('Vendor support');
});

test('sold out product detail keeps support actions while replacing purchase controls', function () {
    $customer = User::factory()->create();
    $vendor = VendorProfile::factory()->approved()->create([
        'store_name' => 'Nanay Tess Fish Stall',
        'store_description' => 'Fresh catch and ready-to-cook seafood.',
    ]);
    $seafood = Category::factory()->topLevel()->create([
        'name' => 'Seafood',
        'slug' => 'seafood',
    ]);
    $category = Category::factory()->childOf($seafood)->create([
        'name' => 'Shellfish',
        'slug' => 'shellfish',
    ]);
    $product = Product::factory()
        ->for($vendor, 'vendor')
        ->for($category)
        ->active()
        ->create([
            'name' => 'Fresh Mussels',
            'stock_quantity' => 0,
        ]);

    $response = $this->actingAs($customer)->get(route('shop.products.show', $product));

    $response->assertOk()
        ->assertSee('Bring this stall to your cart')
        ->assertSee('Sold out &mdash; check back soon', false)
        ->assertSee('Browse similar '.$category->name)
        ->assertDontSee('Add to cart')
        ->assertDontSee('Buy now')
        ->assertSee('Message vendor')
        ->assertSee('Cash on delivery')
        ->assertSee('GCash & Maya')
        ->assertSee('Vendor support');
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

test('approved vendor storefront page renders vendor details and products', function () {
    $customer = User::factory()->create();
    $vendor = VendorProfile::factory()->approved()->create([
        'store_name' => 'Mercado Fresh Catch',
        'store_description' => 'Seafood and fresh market staples every morning.',
    ]);
    $seafood = Category::factory()->topLevel()->create([
        'name' => 'Seafood',
        'slug' => 'seafood',
    ]);
    $fish = Category::factory()->childOf($seafood)->create([
        'name' => 'Fresh Fish',
        'slug' => 'fresh-fish',
    ]);
    $product = Product::factory()
        ->for($vendor, 'vendor')
        ->for($fish)
        ->active()
        ->create([
            'name' => 'Blue Marlin Steak',
        ]);

    $this->actingAs($customer)
        ->get(route('shop.vendors.show', $vendor))
        ->assertOk()
        ->assertSee($vendor->store_name)
        ->assertSee($vendor->store_description)
        ->assertSee($product->name)
        ->assertSee('Message vendor')
        ->assertSee('Available products');
});

test('pending vendor storefront page returns not found', function () {
    $customer = User::factory()->create();
    $vendor = VendorProfile::factory()->create([
        'store_name' => 'Still Pending Stall',
    ]);

    $this->actingAs($customer)
        ->get(route('shop.vendors.show', $vendor))
        ->assertNotFound();
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
