<?php

use App\Livewire\ProfilePictureEditor;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function profilePictureDataUrl(string $mime = 'image/png'): string
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg==');

    return sprintf('data:%s;base64,%s', $mime, base64_encode($png));
}

test('profile settings embeds the cropper based profile picture editor', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('profile-picture-editor', false)
        ->assertSee('Change Photo')
        ->assertDontSee('profile-image-upload');
});

test('profile picture editor stores a cropped data url and deletes the old profile image', function (): void {
    Storage::fake('public');

    $user = User::factory()->create([
        'profile_image' => 'profile-images/old-avatar.jpg',
    ]);

    Storage::disk('public')->put($user->profile_image, 'old-avatar');

    Livewire::actingAs($user)
        ->test(ProfilePictureEditor::class)
        ->set('croppedImageData', profilePictureDataUrl())
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('profile-picture-updated');

    $user->refresh();

    expect($user->profile_image)->toStartWith('profile-images/profile_'.$user->getKey().'_')
        ->and($user->profile_image)->toEndWith('.png');

    Storage::disk('public')->assertMissing('profile-images/old-avatar.jpg');
    Storage::disk('public')->assertExists($user->profile_image);
});

test('profile picture editor rejects invalid data urls and oversized decoded payloads', function (): void {
    $user = User::factory()->create();
    $oversized = 'data:image/png;base64,'.base64_encode(str_repeat('a', (3 * 1024 * 1024) + 1));

    Livewire::actingAs($user)
        ->test(ProfilePictureEditor::class)
        ->set('croppedImageData', 'not-an-image')
        ->call('save')
        ->assertHasErrors(['croppedImageData']);

    Livewire::actingAs($user)
        ->test(ProfilePictureEditor::class)
        ->set('croppedImageData', $oversized)
        ->call('save')
        ->assertHasErrors(['croppedImageData']);
});

test('profile image accessor falls back to ui avatars when the local image is missing', function (): void {
    config(['filesystems.disks.public.driver' => 'local']);
    Storage::fake('public');

    $user = User::factory()->make([
        'name' => 'Ana Reyes',
        'profile_image' => 'profile-images/missing-avatar.jpg',
    ]);

    expect($user->profile_image_url)
        ->toStartWith('https://ui-avatars.com/api/')
        ->toContain('Ana+Reyes');
});
