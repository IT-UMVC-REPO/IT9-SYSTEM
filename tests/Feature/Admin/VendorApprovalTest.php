<?php

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use Livewire\Livewire;

test('admins can view the vendor approval list', function () {
    $admin = User::factory()->admin()->create();
    $vendor = VendorProfile::factory()->create([
        'store_name' => 'Nanay Cora Produce',
        'status' => VendorStatus::Pending,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.vendors'))
        ->assertOk()
        ->assertSee('Vendor applications')
        ->assertSee($vendor->store_name);
});

test('pending tab shows only pending vendors', function () {
    $admin = User::factory()->admin()->create();

    $pendingVendor = VendorProfile::factory()->create([
        'store_name' => 'Pending Harvest',
        'status' => VendorStatus::Pending,
    ]);

    $approvedVendor = VendorProfile::factory()->approved()->create([
        'store_name' => 'Approved Harvest',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.vendors', ['status' => VendorStatus::Pending->value]))
        ->assertOk()
        ->assertSee($pendingVendor->store_name)
        ->assertDontSee($approvedVendor->store_name);
});

test('admin vendor search filters by store name', function () {
    $admin = User::factory()->admin()->create();

    $matchingVendor = VendorProfile::factory()->create([
        'store_name' => 'Golden Pechay',
    ]);

    $otherVendor = VendorProfile::factory()->create([
        'store_name' => 'Sea Breeze Stall',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.vendors', ['search' => 'Pechay']))
        ->assertOk()
        ->assertSee($matchingVendor->store_name)
        ->assertDontSee($otherVendor->store_name);
});

test('pending vendor review shows submitted sample products', function () {
    $admin = User::factory()->admin()->create();
    $vendorUser = User::factory()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->create([
        'store_name' => 'Proposed Palengke Stall',
        'status' => VendorStatus::Pending,
    ]);
    $category = Category::factory()->standalone()->create([
        'name' => 'Vegetables',
        'slug' => 'vegetables',
    ]);

    Product::factory()->for($vendorProfile, 'vendor')->for($category)->create([
        'name' => 'Sample Ampalaya Bundle',
        'price' => 95.50,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.vendors.show', $vendorProfile))
        ->assertOk()
        ->assertSee('Sample products')
        ->assertSee('Proposed catalog')
        ->assertSee('Approve vendor')
        ->assertSee('Reject vendor')
        ->assertSee('Reject application')
        ->assertSee('Reason for rejection')
        ->assertSee('focus:border-rose-500 focus:ring-rose-500', false)
        ->assertDontSee('focus:ring-emerald-500', false)
        ->assertSee('Sample Ampalaya Bundle')
        ->assertSee("\u{20B1}95.50");
});

test('approve sets correct fields, creates a notification, and updates the user role', function () {
    $admin = User::factory()->admin()->create();
    $vendorUser = User::factory()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->create([
        'status' => VendorStatus::Pending,
        'approved_at' => null,
        'rejection_reason' => null,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.vendor-detail', ['vendorProfile' => $vendorProfile])
        ->call('approve')
        ->assertRedirect(route('admin.vendors'));

    $vendorProfile->refresh();
    $vendorUser->refresh();

    expect($vendorProfile->status)->toBe(VendorStatus::Approved);
    expect($vendorProfile->approved_at)->not->toBeNull();
    expect($vendorUser->role)->toBe(UserRole::Vendor);

    $notification = Notification::query()
        ->where('user_id', $vendorUser->getKey())
        ->latest('id')
        ->first();

    expect($notification)->not->toBeNull();
    expect($notification->title)->toBe('Your store was approved!');
    expect($notification->type)->toBe(NotificationType::System);
});

test('reject requires a reason and creates a rejection notification', function () {
    $admin = User::factory()->admin()->create();
    $vendorUser = User::factory()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->create([
        'status' => VendorStatus::Pending,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.vendor-detail', ['vendorProfile' => $vendorProfile])
        ->call('reject')
        ->assertHasErrors(['rejection_reason' => 'required']);

    Livewire::actingAs($admin)
        ->test('pages::admin.vendor-detail', ['vendorProfile' => $vendorProfile])
        ->set('rejection_reason', 'Please provide more detail about your storefront and upload a clearer image.')
        ->call('reject')
        ->assertRedirect(route('admin.vendors'));

    $vendorProfile->refresh();

    expect($vendorProfile->status)->toBe(VendorStatus::Rejected);
    expect($vendorProfile->approved_at)->toBeNull();
    expect($vendorProfile->rejection_reason)->toContain('clearer image');

    $notification = Notification::query()
        ->where('user_id', $vendorUser->getKey())
        ->latest('id')
        ->first();

    expect($notification)->not->toBeNull();
    expect($notification->title)->toBe('Application not approved');
    expect($notification->message)->toContain('clearer image');
});

test('admins can revoke an approved vendor', function () {
    $admin = User::factory()->admin()->create();
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.vendor-detail', ['vendorProfile' => $vendorProfile])
        ->call('revokeApproval')
        ->assertRedirect(route('admin.vendors'));

    $vendorProfile->refresh();
    $vendorUser->refresh();

    expect($vendorProfile->status)->toBe(VendorStatus::Pending);
    expect($vendorProfile->approved_at)->toBeNull();
    expect($vendorUser->role)->toBe(UserRole::Customer);
});

test('non admin users are redirected away from admin vendor pages', function () {
    $customer = User::factory()->create();
    $vendorProfile = VendorProfile::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.vendors'))
        ->assertRedirect(route('customer.dashboard'));

    $this->actingAs($customer)
        ->get(route('admin.vendors.show', $vendorProfile))
        ->assertRedirect(route('customer.dashboard'));
});
