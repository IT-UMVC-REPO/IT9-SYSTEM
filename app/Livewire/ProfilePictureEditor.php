<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

class ProfilePictureEditor extends Component
{
    public string $croppedImageData = '';

    public function save(): void
    {
        $this->resetErrorBag('croppedImageData');

        $parsedImage = $this->parseCroppedImage();

        if ($parsedImage === null) {
            return;
        }

        [$binary, $extension] = $parsedImage;
        $user = Auth::user();
        $path = sprintf('profile-images/profile_%s_%s.%s', $user->getKey(), now()->timestamp, $extension);

        try {
            Storage::disk('public')->put($path, $binary);

            $oldPath = $user->getRawOriginal('profile_image');

            if (
                filled($oldPath)
                && ! Str::startsWith($oldPath, ['http://', 'https://', '//'])
                && Storage::disk('public')->exists($oldPath)
            ) {
                Storage::disk('public')->delete($oldPath);
            }

            $user->forceFill(['profile_image' => $path])->save();
            $user->refresh();
            $this->croppedImageData = '';

            session()->flash('status', __('Profile picture updated successfully.'));
            $this->dispatch('profile-picture-updated', url: $user->profile_image_url);
        } catch (Throwable) {
            $this->addError('croppedImageData', __('We could not save your profile picture. Please try again.'));
        }
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function parseCroppedImage(): ?array
    {
        if (! preg_match('/^data:(image\/(?:jpeg|png|webp));base64,(.+)$/', $this->croppedImageData, $matches)) {
            $this->addError('croppedImageData', __('Use a valid cropped JPEG, PNG, or WebP image.'));

            return null;
        }

        $mime = $matches[1];
        $base64 = $matches[2];
        $binary = base64_decode($base64, true);

        if ($binary === false) {
            $this->addError('croppedImageData', __('Use a valid cropped image.'));

            return null;
        }

        if (strlen($binary) > 3 * 1024 * 1024) {
            $this->addError('croppedImageData', __('Profile pictures must be 3 MB or smaller.'));

            return null;
        }

        if (getimagesizefromstring($binary) === false) {
            $this->addError('croppedImageData', __('Use a valid image file.'));

            return null;
        }

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        };

        return [$binary, $extension];
    }

    public function render(): View
    {
        return view('livewire.profile-picture-editor');
    }
}
