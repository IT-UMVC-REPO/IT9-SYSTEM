<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VendorCustomerStar;
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

<button
    type="button"
    wire:click="{{ $isStarred ? 'unstar' : 'star' }}"
    @if ($isStarred)
        wire:confirm="{{ __('Remove this customer from your valued list?') }}"
    @endif
    wire:loading.attr="disabled"
    class="inline-flex h-10 w-10 items-center justify-center rounded-full border bg-white text-sm shadow-sm transition dark:bg-zinc-900"
    style="{{ $isStarred
        ? 'border-color: var(--brand-300); background-color: color-mix(in oklab, var(--brand-100) 80%, white 20%); color: var(--brand-700);'
        : 'border-color: rgb(231 229 228); color: rgb(120 113 108);' }}"
    title="{{ $isStarred ? __('Unstar customer') : __('Star customer') }}"
    aria-label="{{ $isStarred ? __('Unstar customer') : __('Star customer') }}"
>
    <span wire:loading.remove wire:target="{{ $isStarred ? 'unstar' : 'star' }}">
        <i class="{{ $isStarred ? 'fa-solid' : 'fa-regular' }} fa-star"></i>
    </span>

    <span wire:loading wire:target="{{ $isStarred ? 'unstar' : 'star' }}">
        <i class="fa-solid fa-spinner animate-spin"></i>
    </span>
</button>
