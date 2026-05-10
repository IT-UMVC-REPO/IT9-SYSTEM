<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function createAdmin(): void
    {
        $validated = $this->validate([
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
        ]);

        User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::Admin,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->reset(['name', 'email', 'password', 'password_confirmation']);
        $this->dispatch('admin-created');

        Flux::modal('create-admin')->close();
        Flux::toast(variant: 'success', text: __('Admin account created.'));
    }
};
?>

<flux:modal name="create-admin" class="max-h-[90vh] max-w-md overflow-y-auto p-6 sm:p-7">
    <div class="brand-soft-surface flex h-14 w-14 items-center justify-center rounded-full">
        <i class="fa-solid fa-user-shield"></i>
    </div>

    <h2 class="brand-serif mt-5 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Create admin') }}</h2>
    <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
        {{ __('Create a verified administrator account with direct access to the admin portal.') }}
    </p>

    <form wire:submit="createAdmin" class="mt-6 space-y-5">
        <flux:input wire:model="name" :label="__('Name')" type="text" required autocomplete="name" />
        <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />
        <flux:input wire:model="password" :label="__('Password')" type="password" required autocomplete="new-password" />
        <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" />

        <div class="flex justify-end gap-3 pt-2">
            <flux:button type="button" variant="ghost" x-on:click="$flux.modal('create-admin').close()">
                {{ __('Cancel') }}
            </flux:button>

            <button type="submit" wire:loading.attr="disabled" class="brand-button-primary active:scale-[0.96]">
                <span wire:loading.remove wire:target="createAdmin">{{ __('Create admin') }}</span>
                <span wire:loading wire:target="createAdmin">{{ __('Creating...') }}</span>
            </button>
        </div>
    </form>
</flux:modal>
