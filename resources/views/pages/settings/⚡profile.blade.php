<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Storage;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;
    use WithFileUploads;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public ?string $currentProfileImage = null;

    #[Validate('nullable|image|max:2048')]
    public $profileImageUpload = null;

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ?? '';
        $this->address = $user->address ?? '';
        $this->currentProfileImage = $user->profile_image;
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            ...$this->profileRules($user->id),
            'profileImageUpload' => ['nullable', 'image', 'max:2048'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => blank($validated['phone'] ?? null) ? null : $validated['phone'],
            'address' => blank($validated['address'] ?? null) ? null : $validated['address'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($this->profileImageUpload !== null) {
            if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }

            $path = $this->profileImageUpload->store('profile-images', 'public');
            $user->profile_image = $path;
            $this->currentProfileImage = $path;
            $this->profileImageUpload = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));

        $this->redirect(route('profile.edit'), navigate: true);
    }

    public function removeProfileImage(): void
    {
        $user = Auth::user();

        if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
            Storage::disk('public')->delete($user->profile_image);
        }

        $user->forceFill(['profile_image' => null])->save();
        $this->currentProfileImage = null;
        $this->profileImageUpload = null;

        Flux::toast(text: __('Profile photo removed.'));

        $this->redirect(route('profile.edit'), navigate: true);
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route($user->homeRoute(), absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Flux::toast(text: __('A new verification code has been sent to your email address.'));
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full settings-page">
    @php
        $user = auth()->user();
        $roleLabel = match ($user->effectiveMarketplaceRole()) {
            \App\Enums\UserRole::Admin => 'Admin',
            \App\Enums\UserRole::Vendor => 'Vendor',
            \App\Enums\UserRole::Customer => 'Customer',
        };
    @endphp

    <div class="settings-shell mx-auto max-w-[1500px] space-y-8">
        @include('partials.settings-heading')

        <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

        <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your market identity and contact details')">
            <div
                x-data="{
                    previewUrl: null,
                    handleFile(event) {
                        const file = event.target.files[0];

                        if (!file) {
                            return;
                        }

                        if (this.previewUrl) {
                            URL.revokeObjectURL(this.previewUrl);
                        }

                        this.previewUrl = URL.createObjectURL(file);
                    }
                }"
            >

                <form wire:submit="updateProfileInformation" class="space-y-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="relative shrink-0">
                            <template x-if="previewUrl">
                                <img
                                    :src="previewUrl"
                                    alt="Preview"
                                    class="h-16 w-16 rounded-full object-cover shadow-sm ring-2 ring-emerald-400 dark:ring-emerald-500"
                                >
                            </template>
                            <template x-if="!previewUrl">
                                @if ($currentProfileImage)
                                    <img
                                        src="{{ Storage::disk('public')->url($currentProfileImage) }}"
                                        alt="{{ $user->name }}"
                                        class="h-16 w-16 rounded-full object-cover shadow-sm ring-2 ring-stone-200 dark:ring-white/10"
                                    >
                                @else
                                    <span class="brand-logo-badge flex h-16 w-16 items-center justify-center rounded-full text-xl font-semibold shadow-sm">
                                        {{ $user->initials() }}
                                    </span>
                                @endif
                            </template>

                            <div
                                x-show="previewUrl"
                                x-cloak
                                class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500 ring-2 ring-white dark:ring-zinc-900"
                            >
                                <i class="fa-solid fa-check text-[9px] text-white"></i>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Profile photo') }}</p>
                            <p class="text-xs text-neutral-500 dark:text-zinc-400">{{ __('JPG, PNG or GIF. Max 2 MB.') }}</p>

                            <div class="flex flex-wrap items-center gap-2">
                                <label
                                    for="profile-image-upload"
                                    class="brand-button-secondary inline-flex cursor-pointer items-center gap-2 text-xs"
                                >
                                    <i class="fa-solid fa-arrow-up-from-bracket"></i>
                                    <span x-text="previewUrl ? '{{ __('Change selection') }}' : '{{ __('Upload photo') }}'"></span>
                                </label>
                                <input
                                    id="profile-image-upload"
                                    type="file"
                                    wire:model="profileImageUpload"
                                    x-on:change="handleFile($event)"
                                    accept="image/*"
                                    class="sr-only"
                                >

                                @if ($currentProfileImage && ! $profileImageUpload)
                                    <button
                                        type="button"
                                        wire:click="removeProfileImage"
                                        wire:confirm="{{ __('Remove your profile photo?') }}"
                                        class="text-xs font-medium text-rose-500 transition hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300"
                                    >
                                        {{ __('Remove photo') }}
                                    </button>
                                @endif
                            </div>

                            @error('profileImageUpload')
                                <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

                    <div>
                        <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                        @if ($this->hasUnverifiedEmail)
                            <div class="mt-4 rounded-[1.25rem] border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/30 dark:bg-amber-500/10">
                                <flux:text class="text-sm">
                                    {{ __('Your email address is unverified.') }}

                                    <flux:link class="ml-1 cursor-pointer text-sm" wire:click.prevent="resendVerificationNotification">
                                        {{ __('Click here to send a new verification code.') }}
                                    </flux:link>
                                </flux:text>
                            </div>
                        @endif
                    </div>

                    <flux:input
                        wire:model="phone"
                        :label="__('Phone number')"
                        type="tel"
                        autocomplete="tel"
                        placeholder="+63 9XX XXX XXXX"
                    />

                    <flux:textarea
                        wire:model="address"
                        :label="__('Delivery address')"
                        autocomplete="street-address"
                        :placeholder="__('Street, barangay, city, province')"
                        rows="3"
                    />

                    <div class="flex items-center gap-4">
                        <flux:button variant="primary" type="submit" data-test="update-profile-button">
                            {{ __('Save') }}
                        </flux:button>
                    </div>
                </form>
            </div>

            @if ($this->showDeleteUser)
                <div class="settings-danger-zone">
                    <livewire:pages::settings.delete-user-form />
                </div>
            @endif
        </x-pages::settings.layout>
    </div>
</section>
