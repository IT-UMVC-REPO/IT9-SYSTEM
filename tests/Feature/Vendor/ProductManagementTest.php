<?php

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function productManagementPngFixture(): string
{
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg==',
    );
}

test('vendors can view only their own products', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();

    $otherVendorUser = User::factory()->vendor()->create();
    $otherVendorProfile = VendorProfile::factory()->for($otherVendorUser, 'user')->approved()->create();

    $ownProduct = Product::factory()->for($vendorProfile, 'vendor')->active()->create([
        'name' => 'Fresh Ampalaya',
    ]);

    $otherProduct = Product::factory()->for($otherVendorProfile, 'vendor')->active()->create([
        'name' => 'Other Vendor Product',
    ]);

    $this->actingAs($vendorUser)
        ->get(route('vendor.products'))
        ->assertOk()
        ->assertSee($ownProduct->name)
        ->assertDontSee($otherProduct->name);
});

test('vendor product search filters by name', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();

    $matchingProduct = Product::factory()->for($vendorProfile, 'vendor')->active()->create([
        'name' => 'Budget Talong',
    ]);

    $otherProduct = Product::factory()->for($vendorProfile, 'vendor')->active()->create([
        'name' => 'Premium Salmon',
    ]);

    $this->actingAs($vendorUser)
        ->get(route('vendor.products', ['search' => 'Talong']))
        ->assertOk()
        ->assertSee($matchingProduct->name)
        ->assertDontSee($otherProduct->name);
});

test('vendor status toggle works', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();

    $product = Product::factory()->for($vendorProfile, 'vendor')->create([
        'status' => ProductStatus::Inactive,
    ]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.products')
        ->call('toggleStatus', $product->getKey());

    expect($product->fresh()->status)->toBe(ProductStatus::Active);
});

test('vendors cannot access another vendors edit page', function () {
    $vendorUser = User::factory()->vendor()->create();
    VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();

    $otherVendorUser = User::factory()->vendor()->create();
    $otherVendorProfile = VendorProfile::factory()->for($otherVendorUser, 'user')->approved()->create();
    $otherProduct = Product::factory()->for($otherVendorProfile, 'vendor')->active()->create();

    $this->actingAs($vendorUser)
        ->get(route('vendor.products.edit', $otherProduct))
        ->assertForbidden();
});

test('vendors can create products with an uploaded image', function () {
    Storage::fake('public');

    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();
    $parentCategory = Category::factory()->topLevel()->create([
        'name' => 'Vegetables',
        'slug' => 'vegetables',
    ]);
    $leafCategory = Category::factory()->childOf($parentCategory)->create([
        'name' => 'Leafy Greens',
        'slug' => 'leafy-greens',
    ]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.product-create')
        ->set('name', 'Pechay Bundle')
        ->set('description', 'Fresh pechay packed this morning.')
        ->set('price', '95.50')
        ->set('stock_quantity', '12')
        ->set('categoryId', (string) $leafCategory->getKey())
        ->set('status', ProductStatus::Active->value)
        ->set('productImageUpload', UploadedFile::fake()->createWithContent('pechay.png', productManagementPngFixture()))
        ->call('save')
        ->assertRedirect(route('vendor.products'));

    $product = Product::query()
        ->where('vendor_id', $vendorProfile->getKey())
        ->where('name', 'Pechay Bundle')
        ->first();

    expect($product)->not->toBeNull();
    expect($product->status)->toBe(ProductStatus::Active);
    expect($product->category_id)->toBe($leafCategory->getKey());

    Storage::disk('public')->assertExists($product->getRawOriginal('image'));
});

test('edit form pre-populates and updates a product', function () {
    Storage::fake('public');

    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();
    $parentCategory = Category::factory()->topLevel()->create([
        'name' => 'Seafood',
        'slug' => 'seafood',
    ]);
    $leafCategory = Category::factory()->childOf($parentCategory)->create([
        'name' => 'Fresh Fish',
        'slug' => 'fresh-fish',
    ]);
    $newLeafCategory = Category::factory()->childOf($parentCategory)->create([
        'name' => 'Shellfish',
        'slug' => 'shellfish',
    ]);

    Storage::disk('public')->put('product-images/old.png', 'old-image');

    $product = Product::factory()->for($vendorProfile, 'vendor')->for($leafCategory)->create([
        'name' => 'Bangus',
        'description' => 'Fresh milkfish.',
        'image' => 'product-images/old.png',
        'status' => ProductStatus::Inactive,
    ]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.product-edit', ['product' => $product])
        ->assertSet('name', 'Bangus')
        ->assertSet('categoryId', (string) $leafCategory->getKey())
        ->set('name', 'Bangus Supreme')
        ->set('description', 'Fresh bangus cleaned and ready to cook.')
        ->set('price', '245.00')
        ->set('stock_quantity', '7')
        ->set('categoryId', (string) $newLeafCategory->getKey())
        ->set('status', ProductStatus::Active->value)
        ->set('productImageUpload', UploadedFile::fake()->createWithContent('bangus.png', productManagementPngFixture()))
        ->call('update')
        ->assertRedirect(route('vendor.products'));

    $product->refresh();

    expect($product->name)->toBe('Bangus Supreme');
    expect($product->category_id)->toBe($newLeafCategory->getKey());
    expect($product->status)->toBe(ProductStatus::Active);

    Storage::disk('public')->assertMissing('product-images/old.png');
    Storage::disk('public')->assertExists($product->getRawOriginal('image'));
});

test('vendors can delete a product', function () {
    Storage::fake('public');

    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();

    Storage::disk('public')->put('product-images/delete-me.png', 'image');

    $product = Product::factory()->for($vendorProfile, 'vendor')->create([
        'image' => 'product-images/delete-me.png',
    ]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.product-edit', ['product' => $product])
        ->call('deleteListing')
        ->assertRedirect(route('vendor.products'));

    $this->assertModelMissing($product);
    Storage::disk('public')->assertMissing('product-images/delete-me.png');
});

test('vendor category filter works', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();
    $parentCategory = Category::factory()->topLevel()->create([
        'name' => 'Fruits',
        'slug' => 'fruits',
    ]);
    $leafCategory = Category::factory()->childOf($parentCategory)->create([
        'name' => 'Tropical Fruits',
        'slug' => 'tropical-fruits',
    ]);
    $otherLeafCategory = Category::factory()->childOf($parentCategory)->create([
        'name' => 'Citrus Fruits',
        'slug' => 'citrus-fruits',
    ]);

    $matchingProduct = Product::factory()->for($vendorProfile, 'vendor')->for($leafCategory)->active()->create([
        'name' => 'Mango Basket',
    ]);
    $otherProduct = Product::factory()->for($vendorProfile, 'vendor')->for($otherLeafCategory)->active()->create([
        'name' => 'Calamansi Pack',
    ]);

    $this->actingAs($vendorUser)
        ->get(route('vendor.products', ['category' => $leafCategory->getKey()]))
        ->assertOk()
        ->assertSee($matchingProduct->name)
        ->assertDontSee($otherProduct->name);
});

test('vendor products page shows the out of stock count', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();

    Product::factory()->for($vendorProfile, 'vendor')->active()->create([
        'stock_quantity' => 0,
    ]);

    $this->actingAs($vendorUser)
        ->get(route('vendor.products'))
        ->assertOk()
        ->assertSee('Out of stock')
        ->assertSee('1');
});
