<?php

use App\Concerns\ProfileValidationRules;
use App\Enums\AuditEvent;
use App\Enums\TagumCoordinate;
use App\Enums\UserRole;
use App\Services\AuditLogger;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Storage;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;
    use WithFileUploads;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public string $store_name = '';
    public string $store_description = '';
    public string $vendor_address = '';
    public ?float $lat = null;
    public ?float $lng = null;
    public ?string $currentProfileImage = null;

    public $profileImageUpload = null;

    public function mount(): void
    {
        $user = Auth::user()->loadMissing('vendorProfile');

        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ?? '';
        $this->address = $user->address ?? '';
        $this->lat = $user->lat === null ? null : (float) $user->lat;
        $this->lng = $user->lng === null ? null : (float) $user->lng;
        $this->currentProfileImage = $user->profile_image;

        if ($user->effectiveMarketplaceRole() === UserRole::Vendor && $user->vendorProfile !== null) {
            $this->store_name = $user->vendorProfile->store_name;
            $this->store_description = $user->vendorProfile->store_description ?? '';
            $this->vendor_address = $user->vendorProfile->vendor_address ?? '';
        }
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user()->loadMissing('vendorProfile');
        $isVendor = $user->effectiveMarketplaceRole() === UserRole::Vendor;

        $rules = [
            ...$this->profileRules($user->id),
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'profileImageUpload' => ['nullable', 'image', 'max:2048'],
        ];

        if ($isVendor) {
            $rules = [
                ...$rules,
                'store_name' => ['required', 'string', 'max:120'],
                'store_description' => ['nullable', 'string', 'max:800'],
                'vendor_address' => ['nullable', 'string', 'max:500'],
            ];
        }

        $validated = $this->validate($rules);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => blank($validated['phone'] ?? null) ? null : $validated['phone'],
            'address' => blank($validated['address'] ?? null) ? null : $validated['address'],
            'lat' => ($validated['lat'] ?? null) === null ? null : (float) $validated['lat'],
            'lng' => ($validated['lng'] ?? null) === null ? null : (float) $validated['lng'],
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

        if ($isVendor && $user->vendorProfile !== null) {
            $vendorAddress = blank($validated['vendor_address'] ?? null) ? null : $validated['vendor_address'];
            $vendorProfileAttributes = [
                'store_name' => $validated['store_name'],
                'store_description' => blank($validated['store_description'] ?? null) ? null : $validated['store_description'],
                'vendor_address' => $vendorAddress,
            ];

            if ($user->vendorProfile->vendor_address !== $vendorAddress) {
                $vendorProfileAttributes = [
                    ...$vendorProfileAttributes,
                    ...(filled($vendorAddress) ? TagumCoordinate::random() : ['lat' => null, 'lng' => null]),
                ];
            }

            $user->vendorProfile->update($vendorProfileAttributes);
        }

        AuditLogger::log(AuditEvent::UserProfileUpdated, auth()->user()->name.' updated their profile.', auth()->user());

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
    public function isVendor(): bool
    {
        return Auth::user()->effectiveMarketplaceRole() === UserRole::Vendor;
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
                                    x-transition:enter="transition ease-out duration-300"
                                    x-transition:enter-start="opacity-0 scale-90"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="h-16 w-16 rounded-full object-cover shadow-sm ring-2 ring-emerald-400 dark:ring-emerald-500"
                                >
                            </template>
                            <template x-if="!previewUrl">
                                @if ($currentProfileImage)
                                    <img
                                        src="{{ Storage::disk('public')->url($currentProfileImage) }}"
                                        alt="{{ $user->name }}"
                                        onerror="this.onerror=null; this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden'); this.nextElementSibling.classList.add('flex');"
                                        loading="lazy"
                                        class="h-16 w-16 rounded-full object-cover shadow-sm ring-2 ring-stone-200 dark:ring-white/10"
                                    >
                                    <span class="brand-logo-badge hidden h-16 w-16 items-center justify-center rounded-full text-xl font-semibold shadow-sm">
                                        {{ $user->initials() }}
                                    </span>
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
                                    class="brand-button-secondary inline-flex cursor-pointer items-center gap-2 text-xs transition-all duration-150 active:scale-[0.96]"
                                >
                                    <i class="fa-solid fa-arrow-up-from-bracket"></i>
                                    <span x-text="previewUrl ? @js(__('Change selection')) : @js(__('Upload photo'))"></span>
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
                                        x-data
                                        x-on:click="$flux.modal('remove-profile-photo').show()"
                                        class="text-xs font-medium text-rose-500 transition-all duration-150 hover:text-rose-700 active:scale-[0.97] dark:text-rose-400 dark:hover:text-rose-300"
                                    >
                                        {{ __('Remove photo') }}
                                    </button>

                                    <x-confirmation-modal
                                        name="remove-profile-photo"
                                        :heading="__('Remove photo?')"
                                        :body="__('Your profile will use the default avatar until you upload a new photo.')"
                                        :confirm-label="__('Remove')"
                                        confirm-action="removeProfileImage"
                                    />
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
                        x-on:input.debounce.600ms="window.dispatchEvent(new CustomEvent('profile-address-updated', { detail: $event.target.value }))"
                    />

                    <section
                        class="brand-panel p-5 sm:p-6"
                        x-data="sukiProfileMap({
                            wire: $wire,
                            mapId: 'profile-location-map',
                            lat: @js($lat),
                            lng: @js($lng),
                            address: @js($address),
                            center: {
                                lat: @js(TagumCoordinate::CENTER_LAT),
                                lng: @js(TagumCoordinate::CENTER_LNG),
                            },
                        })"
                        x-init="init()"
                    >
                        <div class="flex items-start gap-3">
                            <span class="brand-soft-surface flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl">
                                <i class="fa-solid fa-location-dot"></i>
                            </span>

                            <div>
                                <span class="brand-kicker">{{ __('Your location on the map') }}</span>
                                <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                                    {{ __('Drag the marker or type your address above - the pin will update automatically.') }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 overflow-hidden rounded-2xl border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-900">
                            <div id="profile-location-map" wire:ignore class="h-[300px] w-full"></div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm font-semibold text-neutral-600 dark:text-zinc-300">
                            <span>{{ __('Lat:') }} <span x-text="formattedLat"></span></span>
                            <span>{{ __('Lng:') }} <span x-text="formattedLng"></span></span>
                        </div>

                    </section>

                    @if ($this->isVendor)
                        <div x-data x-show="true" x-transition:enter="transition ease-out duration-350" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="border-t border-stone-200 pt-6 dark:border-white/10">
                            <div class="flex items-start gap-3">
                                <span class="brand-soft-surface flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl">
                                    <i class="fa-solid fa-store"></i>
                                </span>

                                <div>
                                    <h2 class="brand-serif text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Vendor stall info') }}</h2>
                                    <p class="mt-2 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                                        {{ __('Keep your public storefront name, description, and stall address current for shoppers.') }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-6 grid gap-6">
                                <flux:input
                                    wire:model="store_name"
                                    :label="__('Store name')"
                                    type="text"
                                    required
                                    maxlength="120"
                                />

                                <flux:textarea
                                    wire:model="store_description"
                                    :label="__('Store description')"
                                    rows="3"
                                    maxlength="800"
                                />

                                <flux:textarea
                                    wire:model="vendor_address"
                                    :label="__('Vendor stall address')"
                                    rows="2"
                                    maxlength="500"
                                />
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center gap-4">
                        <flux:button variant="primary" type="submit" class="transition-all duration-150 active:scale-[0.97]" data-test="update-profile-button">
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
