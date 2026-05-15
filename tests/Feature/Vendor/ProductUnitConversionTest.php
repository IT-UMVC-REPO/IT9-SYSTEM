<?php

use App\Enums\ProductStatus;
use App\Enums\ProductUnit;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function productUnitConversionPngFixture(): string
{
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg==',
    );
}

function productUnitConversionVendor(): array
{
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();

    return [$vendorUser, $vendorProfile];
}

function productUnitConversionCategory(): Category
{
    $suffix = fake()->unique()->numerify('######');

    $parentCategory = Category::factory()->topLevel()->create([
        'name' => 'Rice',
        'slug' => 'rice-parent-'.$suffix,
    ]);

    return Category::factory()->childOf($parentCategory)->create([
        'name' => 'Local Rice',
        'slug' => 'rice-leaf-'.$suffix,
    ]);
}

test('product can be saved with unit conversion fields set', function () {
    Storage::fake('public');

    [$vendorUser, $vendorProfile] = productUnitConversionVendor();
    $category = productUnitConversionCategory();

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.product-create')
        ->set('name', 'Dinorado Sack')
        ->set('description', 'Freshly milled rice packed for family meals.')
        ->set('price', '850.00')
        ->set('stock_quantity', '10')
        ->set('categoryId', (string) $category->getKey())
        ->set('unit', ProductUnit::Sack->value)
        ->set('showUnitConversion', true)
        ->set('conversion_unit', 'kg')
        ->set('conversion_unit_quantity', '25')
        ->set('status', ProductStatus::Active->value)
        ->set('productImageUpload', UploadedFile::fake()->createWithContent('rice.png', productUnitConversionPngFixture()))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('vendor.products'));

    $product = Product::query()
        ->where('vendor_id', $vendorProfile->getKey())
        ->where('name', 'Dinorado Sack')
        ->firstOrFail();

    expect($product->conversion_unit)->toBe(ProductUnit::Kilogram);
    expect((float) $product->conversion_unit_quantity)->toBe(25.0);
    expect($product->conversionFor(1)?->displayString)->toBe('1 sack = 25 kg');
});

test('product can be saved with unit conversion fields left null', function () {
    Storage::fake('public');

    [$vendorUser, $vendorProfile] = productUnitConversionVendor();
    $category = productUnitConversionCategory();

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.product-create')
        ->set('name', 'Loose Rice')
        ->set('description', 'Everyday rice sold by the kilo.')
        ->set('price', '65.00')
        ->set('stock_quantity', '50')
        ->set('categoryId', (string) $category->getKey())
        ->set('unit', ProductUnit::Kilogram->value)
        ->set('status', ProductStatus::Active->value)
        ->set('productImageUpload', UploadedFile::fake()->createWithContent('rice.png', productUnitConversionPngFixture()))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('vendor.products'));

    $product = Product::query()
        ->where('vendor_id', $vendorProfile->getKey())
        ->where('name', 'Loose Rice')
        ->firstOrFail();

    expect($product->conversion_unit)->toBeNull();
    expect($product->conversion_unit_quantity)->toBeNull();
    expect($product->conversionFor(1))->toBeNull();
});

test('product can be saved with count based unit conversion', function () {
    Storage::fake('public');

    [$vendorUser, $vendorProfile] = productUnitConversionVendor();
    $category = productUnitConversionCategory();

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.product-create')
        ->set('name', 'Egg Dozen')
        ->set('description', 'Farm eggs counted per dozen for daily market buyers.')
        ->set('price', '120.00')
        ->set('stock_quantity', '8')
        ->set('categoryId', (string) $category->getKey())
        ->set('unit', ProductUnit::Dozen->value)
        ->set('showUnitConversion', true)
        ->set('conversion_unit', 'piece')
        ->set('conversion_unit_quantity', '12')
        ->set('status', ProductStatus::Active->value)
        ->set('productImageUpload', UploadedFile::fake()->createWithContent('eggs.png', productUnitConversionPngFixture()))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('vendor.products'));

    $product = Product::query()
        ->where('vendor_id', $vendorProfile->getKey())
        ->where('name', 'Egg Dozen')
        ->firstOrFail();

    expect($product->conversion_unit)->toBe(ProductUnit::Piece);
    expect((float) $product->conversion_unit_quantity)->toBe(12.0);
    expect($product->conversionFor(1)?->displayString)->toBe('1 dozen = 12 pieces');
});

test('conversion quantity is required when conversion unit is present', function () {
    Storage::fake('public');

    [$vendorUser] = productUnitConversionVendor();
    $category = productUnitConversionCategory();

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.product-create')
        ->set('name', 'Dinorado Sack')
        ->set('description', 'Freshly milled rice packed for family meals.')
        ->set('price', '850.00')
        ->set('stock_quantity', '10')
        ->set('categoryId', (string) $category->getKey())
        ->set('unit', ProductUnit::Sack->value)
        ->set('showUnitConversion', true)
        ->set('conversion_unit', 'kg')
        ->set('conversion_unit_quantity', '')
        ->set('status', ProductStatus::Active->value)
        ->set('productImageUpload', UploadedFile::fake()->createWithContent('rice.png', productUnitConversionPngFixture()))
        ->call('save')
        ->assertHasErrors(['conversion_unit_quantity' => 'required_with']);
});

test('conversion unit must be one of the allowed values', function () {
    Storage::fake('public');

    [$vendorUser] = productUnitConversionVendor();
    $category = productUnitConversionCategory();

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.product-create')
        ->set('name', 'Dinorado Sack')
        ->set('description', 'Freshly milled rice packed for family meals.')
        ->set('price', '850.00')
        ->set('stock_quantity', '10')
        ->set('categoryId', (string) $category->getKey())
        ->set('unit', ProductUnit::Sack->value)
        ->set('showUnitConversion', true)
        ->set('conversion_unit', 'crate')
        ->set('conversion_unit_quantity', '25')
        ->set('status', ProductStatus::Active->value)
        ->set('productImageUpload', UploadedFile::fake()->createWithContent('rice.png', productUnitConversionPngFixture()))
        ->call('save')
        ->assertHasErrors('conversion_unit');
});

test('conversion quantity cannot exceed the realistic maximum', function () {
    Storage::fake('public');

    [$vendorUser] = productUnitConversionVendor();
    $category = productUnitConversionCategory();

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.product-create')
        ->set('name', 'Dinorado Sack')
        ->set('description', 'Freshly milled rice packed for family meals.')
        ->set('price', '850.00')
        ->set('stock_quantity', '10')
        ->set('categoryId', (string) $category->getKey())
        ->set('unit', ProductUnit::Sack->value)
        ->set('showUnitConversion', true)
        ->set('conversion_unit', 'kg')
        ->set('conversion_unit_quantity', '100000')
        ->set('status', ProductStatus::Active->value)
        ->set('productImageUpload', UploadedFile::fake()->createWithContent('rice.png', productUnitConversionPngFixture()))
        ->call('save')
        ->assertHasErrors(['conversion_unit_quantity' => 'max'])
        ->assertSee('Conversion quantity cannot exceed 99,999.');
});

test('large conversion preview stays hidden until the quantity is realistic', function () {
    [$vendorUser] = productUnitConversionVendor();
    $category = productUnitConversionCategory();

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.product-create')
        ->set('categoryId', (string) $category->getKey())
        ->set('unit', ProductUnit::Sack->value)
        ->set('showUnitConversion', true)
        ->set('conversion_unit', 'kg')
        ->set('conversion_unit_quantity', '100000000000000000000')
        ->assertSee('Choose a conversion unit and quantity to preview the shopper-facing conversion.');
});

test('storefront product detail shows conversion string when set', function () {
    [$vendorUser, $vendorProfile] = productUnitConversionVendor();
    $category = productUnitConversionCategory();
    $customer = User::factory()->create();

    $product = Product::factory()->for($vendorProfile, 'vendor')->for($category)->active()->create([
        'name' => 'Dinorado Sack',
        'price' => 850,
        'unit' => ProductUnit::Sack,
        'conversion_unit' => 'kg',
        'conversion_unit_quantity' => 25,
    ]);

    $this->actingAs($customer)
        ->get(route('shop.products.show', $product))
        ->assertOk()
        ->assertSee('1 sack = 25 kg')
        ->assertSee('₱34.00 / kg');
});

test('storefront product detail does not show conversion string when null', function () {
    [, $vendorProfile] = productUnitConversionVendor();
    $category = productUnitConversionCategory();
    $customer = User::factory()->create();

    $product = Product::factory()->for($vendorProfile, 'vendor')->for($category)->active()->create([
        'name' => 'Loose Rice',
        'price' => 65,
        'unit' => ProductUnit::Kilogram,
        'conversion_unit' => null,
        'conversion_unit_quantity' => null,
    ]);

    $this->actingAs($customer)
        ->get(route('shop.products.show', $product))
        ->assertOk()
        ->assertDontSee('Unit info')
        ->assertDontSee('1 kilogram =');
});
