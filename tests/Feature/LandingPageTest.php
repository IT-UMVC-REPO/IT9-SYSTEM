<?php

use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;

test('landing page renders successfully', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('<html lang="'.str_replace('_', '-', app()->getLocale()).'" x-cloak>', false)
        ->assertSee('Your Local Market, Delivered')
        ->assertSee('Fresh from the palengke');
});

test('facebook preview crawler receives public social metadata', function () {
    $this->withHeader('User-Agent', 'facebookexternalhit/1.1')
        ->get(route('home'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=300, public')
        ->assertSee('property="og:title" content="Fresh from the Palengke - SukiMarket"', false)
        ->assertSee('property="og:image" content="https://sukimarket.test/imgs/sukiheader.webp"', false)
        ->assertSee('name="twitter:card" content="summary_large_image"', false);
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
        ->assertSee("\u{20B1}89.50");
});

test('landing page shows a clean fallback showcase when no approved vendor is available', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Featured stalls will appear here as listings go live.')
        ->assertSee('Once the marketplace has live storefronts, this space will highlight a real vendor and real products from the catalog.');
});

test('authenticated users see direct links to their portal home on the landing page', function (callable $makeUser, string $expectedRouteName) {
    $user = $makeUser();

    $this->actingAs($user)->get(route('home'))
        ->assertOk()
        ->assertSee(route($expectedRouteName), false);
})->with([
    'customer' => [
        fn () => User::factory()->create(),
        'customer.dashboard',
    ],
    'vendor' => [
        function () {
            $user = User::factory()->vendor()->create();
            VendorProfile::factory()->for($user, 'user')->approved()->create();

            return $user;
        },
        'vendor.dashboard',
    ],
    'admin' => [
        fn () => User::factory()->admin()->create(),
        'admin.dashboard',
    ],
]);
