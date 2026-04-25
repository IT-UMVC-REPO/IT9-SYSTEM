<?php

use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('profile.edit'))->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can update phone and address from profile settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('phone', '+63 917 000 0000')
        ->set('address', 'Blk 3 Lot 5, Mahogany St, Davao City')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->phone)->toBe('+63 917 000 0000')
        ->and($user->address)->toBe('Blk 3 Lot 5, Mahogany St, Davao City');
});

test('user can clear phone and address by saving empty values', function () {
    $user = User::factory()->create([
        'phone' => '+63 900 111 2222',
        'address' => 'Some Street, Some City',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('phone', '')
        ->set('address', '')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->phone)->toBeNull()
        ->and($user->address)->toBeNull();
});

test('user can upload and replace their profile image from profile settings', function () {
    Storage::fake('public');

    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aF9sAAAAASUVORK5CYII='
    );

    $user = User::factory()->create([
        'profile_image' => 'profile-images/old-avatar.jpg',
    ]);

    Storage::disk('public')->put($user->profile_image, 'old-avatar');

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('profileImageUpload', UploadedFile::fake()->createWithContent('new-avatar.png', $png))
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->profile_image)->not->toBe('profile-images/old-avatar.jpg')
        ->and($user->profile_image)->toStartWith('profile-images/');

    Storage::disk('public')->assertMissing('profile-images/old-avatar.jpg');
    Storage::disk('public')->assertExists($user->profile_image);
});

test('user can remove their profile image from profile settings', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'profile_image' => 'profile-images/avatar-to-remove.jpg',
    ]);

    Storage::disk('public')->put($user->profile_image, 'avatar');

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->call('removeProfileImage')
        ->assertHasNoErrors();

    expect($user->refresh()->profile_image)->toBeNull();
    Storage::disk('public')->assertMissing('profile-images/avatar-to-remove.jpg');
});

test('user avatar component shows profile image when available and initials otherwise', function () {
    $userWithImage = User::factory()->make([
        'name' => 'Maria Santos',
        'profile_image' => 'profile-images/avatar.jpg',
    ]);

    $userWithoutImage = User::factory()->make([
        'name' => 'Juan Dela Cruz',
        'profile_image' => null,
    ]);

    $withImage = Blade::render('<x-user-avatar :user="$user" size="lg" />', [
        'user' => $userWithImage,
    ]);

    $withoutImage = Blade::render('<x-user-avatar :user="$user" size="lg" />', [
        'user' => $userWithoutImage,
    ]);

    expect($withImage)->toContain('<img')
        ->and($withImage)->toContain(Storage::disk('public')->url('profile-images/avatar.jpg'))
        ->and($withoutImage)->toContain($userWithoutImage->initials())
        ->and($withoutImage)->toContain('<span');
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});

test('verified users are redirected back to their portal home when requesting another verification email', function () {
    $user = User::factory()->vendor()->create();
    VendorProfile::factory()->for($user, 'user')->approved()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->call('resendVerificationNotification')
        ->assertRedirect(route('vendor.dashboard', absolute: false));
});
