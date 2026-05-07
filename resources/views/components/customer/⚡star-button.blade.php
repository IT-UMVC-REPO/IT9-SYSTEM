<?php

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\VendorCustomerStar;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public User $customer;

    public bool $isStarred = false;

    public function mount(User $customer): void
    {
        abort_unless(auth()->check(), 403);

        $customer->loadMissing('vendorProfile:id,user_id,status');

        abort_if($customer->effectiveMarketplaceRole() !== UserRole::Customer, 404);
        abort_if(auth()->user()->effectiveMarketplaceRole() !== UserRole::Vendor, 403);
        abort_if(auth()->id() === $customer->getKey(), 403);

        $this->customer = $customer;
        $this->refreshStarState();
    }

    #[On('customer-stars-updated')]
    public function refreshStarState(): void
    {
        $this->isStarred = VendorCustomerStar::query()
            ->where('vendor_user_id', auth()->id())
            ->where('customer_id', $this->customer->getKey())
            ->exists();
    }

    public function star(): void
    {
        $this->authorizeStarAction();

        DB::transaction(function (): void {
            VendorCustomerStar::query()->firstOrCreate([
                'vendor_user_id' => auth()->id(),
                'customer_id' => $this->customer->getKey(),
            ]);
        });

        $this->isStarred = true;

        AuditLogger::log(AuditEvent::CustomerStarred, "Vendor starred customer #{$this->customer->id}.", $this->customer);

        Flux::toast(variant: 'success', text: __('Customer marked as valued.'));

        $this->dispatch('customer-stars-updated');
    }

    public function unstar(): void
    {
        $this->authorizeStarAction();

        DB::transaction(function (): void {
            VendorCustomerStar::query()
                ->where('vendor_user_id', auth()->id())
                ->where('customer_id', $this->customer->getKey())
                ->delete();
        });

        $this->isStarred = false;

        AuditLogger::log(AuditEvent::CustomerUnstarred, "Vendor unstarred customer #{$this->customer->id}.", $this->customer);

        Flux::toast(variant: 'warning', text: __('Customer removed from valued list.'));

        $this->dispatch('customer-stars-updated');
    }

    private function authorizeStarAction(): void
    {
        abort_unless(auth()->check(), 403);
        abort_if(auth()->user()->effectiveMarketplaceRole() !== UserRole::Vendor, 403);
    }
};
?>

@php($modalName = 'confirm-unstar-customer-'.$customer->getKey())

<div class="inline-flex">
    @if ($isStarred)
        <button
            type="button"
            x-data
            x-on:click="$flux.modal('{{ $modalName }}').show()"
            wire:loading.attr="disabled"
            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[var(--brand-300)] bg-[var(--brand-100)] text-sm text-[var(--brand-700)] shadow-sm transition hover:bg-[var(--brand-50)] disabled:cursor-not-allowed disabled:opacity-60 dark:border-[var(--brand-500)] dark:bg-zinc-800 dark:text-[var(--brand-200)]"
            title="{{ __('Unstar customer') }}"
            aria-label="{{ __('Unstar customer') }}"
        >
            <span wire:loading.remove wire:target="unstar">
                <i class="fa-solid fa-star"></i>
            </span>

            <span wire:loading wire:target="unstar">
                <i class="fa-solid fa-spinner animate-spin"></i>
            </span>
        </button>

        <flux:modal name="{{ $modalName }}" class="max-w-sm">
            <div class="p-6 space-y-4">
                <flux:heading size="lg">{{ __('Remove customer?') }}</flux:heading>
                <flux:text>{{ __('This customer will be removed from your valued list.') }}</flux:text>

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button variant="ghost" x-on:click="$flux.modal('{{ $modalName }}').close()">
                        {{ __('Cancel') }}
                    </flux:button>

                    <flux:button variant="danger" wire:click="unstar">
                        {{ __('Remove') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @else
        <button
            type="button"
            wire:click="star"
            wire:loading.attr="disabled"
            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-stone-200 bg-white text-sm text-stone-500 shadow-sm transition hover:border-stone-300 hover:text-stone-700 disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800"
            title="{{ __('Star customer') }}"
            aria-label="{{ __('Star customer') }}"
        >
            <span wire:loading.remove wire:target="star">
                <i class="fa-regular fa-star"></i>
            </span>

            <span wire:loading wire:target="star">
                <i class="fa-solid fa-spinner animate-spin"></i>
            </span>
        </button>
    @endif
</div>
