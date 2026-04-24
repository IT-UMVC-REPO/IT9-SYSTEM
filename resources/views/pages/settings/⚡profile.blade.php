<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route($user->homeRoute(), absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Flux::toast(text: __('A new verification link has been sent to your email address.'));
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
            <div class="settings-profile-hero">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <span class="flex h-20 w-20 items-center justify-center rounded-full bg-emerald-600 text-2xl font-semibold text-white shadow-sm">
                            {{ $user->initials() }}
                        </span>

                        <div>
                            <h2 class="brand-serif text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ $user->name }}</h2>
                            <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">{{ $user->email }}</p>
                        </div>
                    </div>

                    <span class="settings-role-badge self-start sm:self-center">{{ $roleLabel }}</span>
                </div>
            </div>

            <form wire:submit="updateProfileInformation" class="space-y-6">
                <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

                <div>
                    <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                    @if ($this->hasUnverifiedEmail)
                        <div class="mt-4 rounded-[1.25rem] border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/30 dark:bg-amber-500/10">
                            <flux:text class="text-sm">
                                {{ __('Your email address is unverified.') }}

                                <flux:link class="ml-1 cursor-pointer text-sm" wire:click.prevent="resendVerificationNotification">
                                    {{ __('Click here to re-send the verification email.') }}
                                </flux:link>
                            </flux:text>
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </form>

            @if ($this->showDeleteUser)
                <div class="settings-danger-zone">
                    <livewire:pages::settings.delete-user-form />
                </div>
            @endif
        </x-pages::settings.layout>
    </div>
</section>
