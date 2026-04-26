<?php

use App\Enums\NotificationType;
use App\Enums\VendorStatus;
use App\Models\Notification;
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

    Livewire::actingAs($customer)
        ->test('pages::vendor.registration')
        ->set('store_name', 'Nanay Tess Greens')
        ->set('store_description', 'Fresh vegetables and market staples every morning.')
        ->set('storeImageUpload', UploadedFile::fake()->createWithContent('stall.png', vendorRegistrationPngFixture()))
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

    $notification = Notification::query()
        ->where('user_id', $customer->getKey())
        ->latest('id')
        ->first();

    expect($notification)->not->toBeNull();
    expect($notification->title)->toBe('Application submitted');
    expect($notification->message)->toBe('Your vendor application has been received and is under review.');
    expect($notification->type)->toBe(NotificationType::System);
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

    $vendor = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendor, 'user')->rejected()->create([
        'store_name' => 'Old Stall Name',
        'store_description' => 'Old description.',
        'store_image' => 'store-images/old-stall.jpg',
        'rejection_reason' => 'Please upload a clearer storefront photo and expand your description.',
    ]);

    Livewire::actingAs($vendor)
        ->test('pages::vendor.registration')
        ->assertSee('Please upload a clearer storefront photo and expand your description.')
        ->call('beginReapplication')
        ->assertSee('Refresh your vendor application')
        ->set('store_name', 'Bagong Ani Market')
        ->set('store_description', 'Updated store details with a clearer market focus.')
        ->set('storeImageUpload', UploadedFile::fake()->createWithContent('new-stall.png', vendorRegistrationPngFixture()))
        ->call('submit')
        ->assertRedirect(route('vendor.registration'));

    $vendorProfile->refresh();

    expect($vendorProfile->status)->toBe(VendorStatus::Pending);
    expect($vendorProfile->rejection_reason)->toBeNull();
    expect($vendorProfile->approved_at)->toBeNull();
    expect($vendorProfile->store_name)->toBe('Bagong Ani Market');

    Storage::disk('public')->assertMissing('store-images/old-stall.jpg');
    Storage::disk('public')->assertExists($vendorProfile->getRawOriginal('store_image'));
});
