<?php

use App\Enums\NotificationType;
use App\Enums\ProductStatus;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function vendorRegistrationPngFixture(): string
{
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg==',
    );
}

test('guests are redirected to login when opening vendor registration', function () {
    $this->get(route('vendor.registration'))
        ->assertRedirect(route('login'));
});

test('authenticated customers can see the vendor registration form', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('vendor.registration'))
        ->assertOk()
        ->assertSee('Open your stall on SukiMarket')
        ->assertSee('Sample products')
        ->assertSee('Show the admin what your stall plans to sell')
        ->assertSee('This draft stays off the storefront until approval.')
        ->assertSee('Submit application');
});

test('approved vendors are redirected to the vendor dashboard', function () {
    $vendor = User::factory()->vendor()->create();
    VendorProfile::factory()->for($vendor, 'user')->approved()->create();

    $this->actingAs($vendor)
        ->get(route('vendor.registration'))
        ->assertRedirect(route('vendor.dashboard'));
});

test('submitting valid data creates a pending vendor profile, stores the image, and creates a notification', function () {
    Storage::fake('public');

    $customer = User::factory()->create();
    $category = Category::factory()->standalone()->create([
        'name' => 'Vegetables',
        'slug' => 'vegetables',
    ]);

    Livewire::actingAs($customer)
        ->test('pages::vendor.registration')
        ->set('store_name', 'Nanay Tess Greens')
        ->set('store_description', 'Fresh vegetables and market staples every morning.')
        ->set('storeImageUpload', UploadedFile::fake()->createWithContent('stall.png', vendorRegistrationPngFixture()))
        ->set('sampleProducts.0.name', 'Fresh Okra Bundle')
        ->set('sampleProducts.0.description', 'Fresh okra packed for the morning market crowd.')
        ->set('sampleProducts.0.price', '95.50')
        ->set('sampleProducts.0.stock_quantity', '12')
        ->set('sampleProducts.0.categoryId', (string) $category->getKey())
        ->set('sampleProductUploads.0', UploadedFile::fake()->createWithContent('okra.png', vendorRegistrationPngFixture()))
        ->call('submit')
        ->assertRedirect(route('vendor.registration'));

    $vendorProfile = VendorProfile::query()
        ->where('user_id', $customer->getKey())
        ->first();

    expect($vendorProfile)->not->toBeNull();
    expect($vendorProfile->status)->toBe(VendorStatus::Pending);
    expect($vendorProfile->rejection_reason)->toBeNull();
    expect($vendorProfile->approved_at)->toBeNull();

    Storage::disk('public')->assertExists($vendorProfile->getRawOriginal('store_image'));

    $product = Product::query()
        ->where('vendor_id', $vendorProfile->getKey())
        ->where('name', 'Fresh Okra Bundle')
        ->first();

    expect($product)->not->toBeNull();
    expect($product->status)->toBe(ProductStatus::Inactive);
    expect($product->category_id)->toBe($category->getKey());

    Storage::disk('public')->assertExists($product->getRawOriginal('image'));

    $notification = Notification::query()
        ->where('user_id', $customer->getKey())
        ->latest('id')
        ->first();

    expect($notification)->not->toBeNull();
    expect($notification->title)->toBe('Application submitted');
    expect($notification->message)->toBe('Your vendor application has been received and is under review.');
    expect($notification->type)->toBe(NotificationType::System);
});

test('vendor address is saved during registration', function () {
    Storage::fake('public');

    $customer = User::factory()->create();
    $category = Category::factory()->standalone()->create([
        'name' => 'Vegetables',
        'slug' => 'vegetables-address',
    ]);

    Livewire::actingAs($customer)
        ->test('pages::vendor.registration')
        ->set('store_name', 'Stall with Address')
        ->set('store_description', 'Fresh goods every morning.')
        ->set('vendor_address', 'Stall 12, Bankerohan Market, Davao City')
        ->set('storeImageUpload', UploadedFile::fake()->createWithContent('stall.png', vendorRegistrationPngFixture()))
        ->set('sampleProducts.0.name', 'Fresh Sayote')
        ->set('sampleProducts.0.description', 'Morning market stock.')
        ->set('sampleProducts.0.price', '55.00')
        ->set('sampleProducts.0.stock_quantity', '7')
        ->set('sampleProducts.0.categoryId', (string) $category->getKey())
        ->set('sampleProductUploads.0', UploadedFile::fake()->createWithContent('sayote.png', vendorRegistrationPngFixture()))
        ->call('submit');

    $vendorProfile = VendorProfile::query()->where('user_id', $customer->getKey())->first();

    expect($vendorProfile)->not->toBeNull()
        ->and($vendorProfile->vendor_address)->toBe('Stall 12, Bankerohan Market, Davao City');
});

test('vendor address is optional during registration', function () {
    Storage::fake('public');

    $customer = User::factory()->create();
    $category = Category::factory()->standalone()->create([
        'name' => 'Fruits',
        'slug' => 'fruits-address',
    ]);

    Livewire::actingAs($customer)
        ->test('pages::vendor.registration')
        ->set('store_name', 'No Address Stall')
        ->set('store_description', 'Simple description.')
        ->set('storeImageUpload', UploadedFile::fake()->createWithContent('stall.png', vendorRegistrationPngFixture()))
        ->set('sampleProducts.0.name', 'Sweet Mango')
        ->set('sampleProducts.0.description', 'Freshly delivered this morning.')
        ->set('sampleProducts.0.price', '95.00')
        ->set('sampleProducts.0.stock_quantity', '8')
        ->set('sampleProducts.0.categoryId', (string) $category->getKey())
        ->set('sampleProductUploads.0', UploadedFile::fake()->createWithContent('mango.png', vendorRegistrationPngFixture()))
        ->call('submit');

    $vendorProfile = VendorProfile::query()->where('user_id', $customer->getKey())->first();

    expect($vendorProfile)->not->toBeNull()
        ->and($vendorProfile->vendor_address)->toBeNull();
});

test('missing sample product image validation uses customer-facing copy', function () {
    $customer = User::factory()->create();
    $category = Category::factory()->standalone()->create([
        'name' => 'Vegetables',
        'slug' => 'vegetables-image-warning',
    ]);

    Livewire::actingAs($customer)
        ->test('pages::vendor.registration')
        ->set('store_name', 'Photo Check Stall')
        ->set('store_description', 'Fresh goods with clear photos for review.')
        ->set('storeImageUpload', UploadedFile::fake()->createWithContent('stall.png', vendorRegistrationPngFixture()))
        ->set('sampleProducts.0.name', 'Fresh Okra Bundle')
        ->set('sampleProducts.0.description', 'Fresh okra packed for the morning market crowd.')
        ->set('sampleProducts.0.price', '95.50')
        ->set('sampleProducts.0.stock_quantity', '12')
        ->set('sampleProducts.0.categoryId', (string) $category->getKey())
        ->call('submit')
        ->assertHasErrors(['sampleProductUploads.0' => 'required'])
        ->assertSee('Upload a photo for sample product #1 before submitting.')
        ->assertDontSee('sample product uploads.0');
});

test('customers with a pending vendor profile see the holding state instead of the form', function () {
    $customer = User::factory()->create();

    VendorProfile::factory()->for($customer, 'user')->create([
        'store_name' => 'Market Harvest',
        'status' => VendorStatus::Pending,
    ]);

    $this->actingAs($customer)
        ->get(route('vendor.registration'))
        ->assertOk()
        ->assertSee('Your application is being reviewed')
        ->assertSee('Market Harvest')
        ->assertDontSee('Submit application');
});

test('rejected vendors can reopen the form and reapply', function () {
    Storage::fake('public');

    Storage::disk('public')->put('store-images/old-stall.jpg', 'old-image');
    Storage::disk('public')->put('product-images/old-product-1.jpg', 'old-product-1');
    Storage::disk('public')->put('product-images/old-product-2.jpg', 'old-product-2');

    $vendor = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendor, 'user')->rejected()->create([
        'store_name' => 'Old Stall Name',
        'store_description' => 'Old description.',
        'store_image' => 'store-images/old-stall.jpg',
        'rejection_reason' => 'Please upload a clearer storefront photo and expand your description.',
    ]);
    $oldCategory = Category::factory()->standalone()->create([
        'name' => 'Seafood',
        'slug' => 'seafood',
    ]);
    $newCategory = Category::factory()->standalone()->create([
        'name' => 'Vegetables',
        'slug' => 'vegetables',
    ]);
    $existingProduct = Product::factory()->for($vendorProfile, 'vendor')->for($oldCategory)->create([
        'name' => 'Old Galunggong Tray',
        'description' => 'Older product copy.',
        'image' => 'product-images/old-product-1.jpg',
        'status' => ProductStatus::Inactive,
    ]);
    $removedProduct = Product::factory()->for($vendorProfile, 'vendor')->for($oldCategory)->create([
        'name' => 'To Be Removed',
        'image' => 'product-images/old-product-2.jpg',
        'status' => ProductStatus::Inactive,
    ]);

    Livewire::actingAs($vendor)
        ->test('pages::vendor.registration')
        ->assertSee('Please upload a clearer storefront photo and expand your description.')
        ->call('beginReapplication')
        ->assertSee('Refresh your vendor application')
        ->assertSee('Submit reapplication')
        ->assertSet('sampleProducts.0.name', 'Old Galunggong Tray')
        ->assertSet('sampleProducts.1.name', 'To Be Removed')
        ->call('removeSampleProduct', 1)
        ->set('store_name', 'Bagong Ani Market')
        ->set('store_description', 'Updated store details with a clearer market focus.')
        ->set('storeImageUpload', UploadedFile::fake()->createWithContent('new-stall.png', vendorRegistrationPngFixture()))
        ->set('sampleProducts.0.name', 'Updated Galunggong Tray')
        ->set('sampleProducts.0.description', 'Updated seafood copy with clearer details.')
        ->set('sampleProducts.0.price', '210.00')
        ->set('sampleProducts.0.stock_quantity', '9')
        ->set('sampleProducts.0.categoryId', (string) $oldCategory->getKey())
        ->call('addSampleProduct')
        ->set('sampleProducts.1.name', 'Fresh Pechay Bundle')
        ->set('sampleProducts.1.description', 'Leafy greens packed for same-day market runs.')
        ->set('sampleProducts.1.price', '85.00')
        ->set('sampleProducts.1.stock_quantity', '14')
        ->set('sampleProducts.1.categoryId', (string) $newCategory->getKey())
        ->set('sampleProductUploads.1', UploadedFile::fake()->createWithContent('pechay.png', vendorRegistrationPngFixture()))
        ->call('submit')
        ->assertRedirect(route('vendor.registration'));

    $vendorProfile->refresh();
    $existingProduct->refresh();

    expect($vendorProfile->status)->toBe(VendorStatus::Pending);
    expect($vendorProfile->rejection_reason)->toBeNull();
    expect($vendorProfile->approved_at)->toBeNull();
    expect($vendorProfile->store_name)->toBe('Bagong Ani Market');

    expect($existingProduct->name)->toBe('Updated Galunggong Tray');
    expect($existingProduct->status)->toBe(ProductStatus::Inactive);
    expect($existingProduct->getRawOriginal('image'))->toBe('product-images/old-product-1.jpg');
    expect($existingProduct->category_id)->toBe($oldCategory->getKey());

    $newProduct = Product::query()
        ->where('vendor_id', $vendorProfile->getKey())
        ->where('name', 'Fresh Pechay Bundle')
        ->first();

    expect($newProduct)->not->toBeNull();
    expect($newProduct->status)->toBe(ProductStatus::Inactive);
    expect($newProduct->category_id)->toBe($newCategory->getKey());

    $this->assertModelMissing($removedProduct);

    Storage::disk('public')->assertMissing('store-images/old-stall.jpg');
    Storage::disk('public')->assertExists($vendorProfile->getRawOriginal('store_image'));
    Storage::disk('public')->assertExists('product-images/old-product-1.jpg');
    Storage::disk('public')->assertMissing('product-images/old-product-2.jpg');
    Storage::disk('public')->assertExists($newProduct->getRawOriginal('image'));
});
