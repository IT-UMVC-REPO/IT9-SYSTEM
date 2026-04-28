<?php

use App\Enums\UserRole;
use Livewire\Component;

new class extends Component
{
    public bool $fullWidth = false;

    public function switchToCustomer(): void
    {
        $user = auth()->user();

        if ($user === null || $user->role !== UserRole::Vendor || ! $user->hasApprovedVendorProfile()) {
            return;
        }

        session(['marketplace_mode' => 'customer']);

        $this->redirectRoute('customer.dashboard', navigate: true);
    }

    public function switchToVendor(): void
    {
        $user = auth()->user();

        if ($user === null || $user->role !== UserRole::Vendor || ! $user->hasApprovedVendorProfile()) {
            return;
        }

        session()->forget('marketplace_mode');

        $this->redirectRoute('vendor.dashboard', navigate: true);
    }
};
?>

@php($user = auth()->user())
@php($canToggleMarketplaceMode = $user?->role === UserRole::Vendor && $user->hasApprovedVendorProfile())
@php($widthClasses = $fullWidth ? 'w-full justify-center' : 'justify-center')

<div class="contents">
    @if ($canToggleMarketplaceMode)
        @if (session('marketplace_mode') === 'customer')
            <button
                type="button"
                wire:click="switchToVendor"
                class="{{ $widthClasses }} inline-flex items-center gap-2 rounded-full border border-[var(--brand-200)] bg-[var(--brand-50)] px-3 py-1.5 text-xs font-semibold text-[var(--brand-700)] transition hover:border-[var(--brand-300)] hover:bg-[color:color-mix(in_oklab,var(--brand-50),white_18%)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand-500)] focus-visible:ring-offset-2 dark:border-[var(--brand-500)] dark:bg-zinc-800 dark:text-[var(--brand-200)] dark:hover:bg-zinc-700"
                title="{{ __('Switch back to vendor mode') }}"
            >
                <i class="fa-solid fa-shop text-[10px]"></i>
                {{ __('Back to vendor') }}
            </button>
        @else
            <button
                type="button"
                wire:click="switchToCustomer"
                class="{{ $widthClasses }} inline-flex items-center gap-2 rounded-full border border-stone-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-600 transition hover:border-stone-300 hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand-500)] focus-visible:ring-offset-2 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                title="{{ __('Switch to customer mode') }}"
            >
                <i class="fa-solid fa-basket-shopping text-[10px]"></i>
                {{ __('Customer mode') }}
            </button>
        @endif
    @endif
</div>
