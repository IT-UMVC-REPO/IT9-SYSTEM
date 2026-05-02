<?php

use App\Enums\UserRole;
use App\Models\Favorite;
use App\Models\VendorProfile;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public VendorProfile $vendor;

    public bool $isFollowing = false;

    public bool $overlay = false;

    public function mount(VendorProfile $vendor): void
    {
        abort_unless(auth()->check(), 403);
        abort_if(auth()->user()->effectiveMarketplaceRole() === UserRole::Admin, 403);

        $this->vendor = $vendor;
        $this->refreshFollowState();
    }

    #[On('favorites-updated')]
    public function refreshFollowState(): void
    {
        $this->isFollowing = Favorite::query()
            ->where('customer_id', auth()->id())
            ->where('vendor_id', $this->vendor->getKey())
            ->exists();
    }

    public function follow(): void
    {
        $this->authorizeFollowAction();

        DB::transaction(function (): void {
            Favorite::query()->firstOrCreate([
                'customer_id' => auth()->id(),
                'vendor_id' => $this->vendor->getKey(),
            ]);
        });

        $this->isFollowing = true;

        Flux::toast(variant: 'success', text: __('Stall added to favourites.'));

        $this->dispatch('favorites-updated');
    }

    public function unfollow(): void
    {
        $this->authorizeFollowAction();

        DB::transaction(function (): void {
            Favorite::query()
                ->where('customer_id', auth()->id())
                ->where('vendor_id', $this->vendor->getKey())
                ->delete();
        });

        $this->isFollowing = false;

        Flux::toast(variant: 'warning', text: __('Stall removed from favourites.'));

        $this->dispatch('favorites-updated');
    }

    private function authorizeFollowAction(): void
    {
        abort_unless(auth()->check(), 403);
        abort_if(auth()->user()->effectiveMarketplaceRole() === UserRole::Admin, 403);
    }
};
?>

@php($modalName = 'confirm-unfollow-stall-'.$vendor->getKey())

<div class="{{ $overlay ? 'absolute right-3 top-3 z-10' : 'inline-flex' }}">
    @if ($isFollowing)
        <button
            type="button"
            x-data
            x-on:click="$flux.modal('{{ $modalName }}').show()"
            wire:loading.attr="disabled"
            class="{{ $overlay ? 'h-8 w-8' : 'h-10 w-10' }} flex items-center justify-center rounded-full bg-emerald-600 text-white shadow-sm transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60"
            title="{{ __('Unfollow stall') }}"
            aria-label="{{ __('Unfollow stall') }}"
        >
            <span wire:loading.remove wire:target="unfollow">
                <svg class="{{ $overlay ? 'h-4 w-4' : 'h-5 w-5' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="m9.653 16.915-.005-.003-.019-.01a20.759 20.759 0 0 1-1.162-.682 22.045 22.045 0 0 1-2.582-1.9C3.941 12.647 2 10.352 2 7.5A4.5 4.5 0 0 1 6.5 3c1.626 0 2.858.817 3.5 1.74A4.186 4.186 0 0 1 13.5 3 4.5 4.5 0 0 1 18 7.5c0 2.852-1.941 5.147-3.885 6.82a22.045 22.045 0 0 1-3.744 2.582l-.019.01-.005.003h-.002a.75.75 0 0 1-.69 0h-.002Z" />
                </svg>
            </span>

            <span wire:loading wire:target="unfollow">
                <i class="fa-solid fa-spinner animate-spin"></i>
            </span>
        </button>

        <x-confirmation-modal
            :name="$modalName"
            :heading="__('Remove stall?')"
            :body="__('This stall will be removed from your favourites.')"
            :confirm-label="__('Remove')"
            confirm-action="unfollow"
        />
    @else
        <button
            type="button"
            wire:click="follow"
            wire:loading.attr="disabled"
            class="{{ $overlay ? 'h-8 w-8' : 'h-10 w-10' }} flex items-center justify-center rounded-full bg-zinc-800 text-zinc-400 shadow-sm transition hover:bg-zinc-700 disabled:cursor-not-allowed disabled:opacity-60"
            title="{{ __('Follow stall') }}"
            aria-label="{{ __('Follow stall') }}"
        >
            <span wire:loading.remove wire:target="follow">
                <svg class="{{ $overlay ? 'h-4 w-4' : 'h-5 w-5' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.933 0-3.595 1.126-4.312 2.733-.717-1.607-2.379-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                </svg>
            </span>

            <span wire:loading wire:target="follow">
                <i class="fa-solid fa-spinner animate-spin"></i>
            </span>
        </button>
    @endif
</div>
