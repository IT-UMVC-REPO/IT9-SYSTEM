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

<button
    type="button"
    wire:click="{{ $isFollowing ? 'unfollow' : 'follow' }}"
    @if ($isFollowing)
        wire:confirm="{{ __('Remove this stall from your favourites?') }}"
    @endif
    wire:loading.attr="disabled"
    class="inline-flex h-10 w-10 items-center justify-center rounded-full border bg-white text-sm shadow-sm transition dark:bg-zinc-900"
    style="{{ $isFollowing
        ? 'border-color: var(--brand-300); background-color: color-mix(in oklab, var(--brand-100) 80%, white 20%); color: var(--brand-700);'
        : 'border-color: rgb(231 229 228); color: rgb(120 113 108);' }}"
    title="{{ $isFollowing ? __('Unfollow stall') : __('Follow stall') }}"
    aria-label="{{ $isFollowing ? __('Unfollow stall') : __('Follow stall') }}"
>
    <span wire:loading.remove wire:target="{{ $isFollowing ? 'unfollow' : 'follow' }}">
        <i class="{{ $isFollowing ? 'fa-solid' : 'fa-regular' }} fa-heart"></i>
    </span>

    <span wire:loading wire:target="{{ $isFollowing ? 'unfollow' : 'follow' }}">
        <i class="fa-solid fa-spinner animate-spin"></i>
    </span>
</button>
