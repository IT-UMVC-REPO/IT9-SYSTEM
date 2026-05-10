<?php

use App\Concerns\PasswordValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Security settings')] class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public bool $canManageTwoFactor = false;

    public bool $twoFactorEnabled = false;

    public bool $requiresConfirmation = false;
    public function mount(): void
    {
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            $user = auth()->user();

            $this->twoFactorEnabled = $user->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }
    }
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password updated.'));
    }
    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
    }
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }
}; ?>

<section class="w-full settings-page">
    <div class="settings-shell mx-auto max-w-[1500px] space-y-8">
        @include('partials.settings-heading')

        <flux:heading class="sr-only">{{ __('Security settings') }}</flux:heading>

        <x-pages::settings.layout :heading="__('Security')" :subheading="__('Protect your account and manage sign-in safeguards')">
            <section class="settings-section-card suki-reveal" style="transition-delay: 80ms">
                <div class="flex items-start gap-4">
                    <span class="brand-soft-surface flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl">
                        <i class="fa-solid fa-key text-lg"></i>
                    </span>

                    <div>
                        <h3 class="brand-serif text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Update password') }}</h3>
                        <p class="mt-2 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                            {{ __('Keep your account protected with a long, unique password that is easy to store in a trusted password manager.') }}
                        </p>
                    </div>
                </div>

                <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
                    <flux:input
                        wire:model="current_password"
                        :label="__('Current password')"
                        type="password"
                        required
                        autocomplete="current-password"
                        viewable
                    />
                    <flux:input
                        wire:model="password"
                        :label="__('New password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        viewable
                    />
                    <flux:input
                        wire:model="password_confirmation"
                        :label="__('Confirm password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        viewable
                    />

                    <div class="flex items-center gap-4">
                        <flux:button variant="primary" type="submit" class="transition-all duration-150 active:scale-[0.97]" data-test="update-password-button">
                            {{ __('Save') }}
                        </flux:button>
                    </div>
                </form>
            </section>

            @if ($canManageTwoFactor)
                <section class="settings-section-card suki-reveal" style="transition-delay: 160ms">
                    <div class="flex items-start gap-4">
                        <span class="brand-soft-surface flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl">
                            <i class="fa-solid fa-shield-halved text-lg"></i>
                        </span>

                        <div class="flex-1">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h3 class="brand-serif text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Two-factor authentication') }}</h3>
                                    <p class="mt-2 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                                        {{ __('Add an extra layer of sign-in protection so your LocalPalengke account is harder to access without your device.') }}
                                    </p>
                                </div>

                                <span class="settings-role-badge self-start">{{ $twoFactorEnabled ? __('Enabled') : __('Not enabled') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col space-y-6 text-sm" wire:cloak>
                        @if ($twoFactorEnabled)
                            <div wire:transition class="space-y-4">
                                <p class="text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                                    {{ __('You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                                </p>

                                <div class="flex justify-start">
                                    <flux:button
                                        variant="danger"
                                        wire:click="disable"
                                    >
                                        {{ __('Disable 2FA') }}
                                    </flux:button>
                                </div>

                                <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                            </div>
                        @else
                            <div wire:transition class="space-y-4">
                                <p class="text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                                    {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                                </p>

                                <flux:modal.trigger name="two-factor-setup-modal">
                                    <flux:button
                                        variant="primary"
                                        wire:click="$dispatch('start-two-factor-setup')"
                                        class="transition-all duration-150 active:scale-[0.97]"
                                    >
                                        {{ __('Enable 2FA') }}
                                    </flux:button>
                                </flux:modal.trigger>

                                <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
                            </div>
                        @endif
                    </div>
                </section>
            @endif
        </x-pages::settings.layout>
    </div>
</section>
