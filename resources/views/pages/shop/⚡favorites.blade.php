<?php

use App\Enums\VendorStatus;
use App\Models\Favorite;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Favourites')] class extends Component
{
    #[On('favorites-updated')]
    public function refreshFavourites(): void
    {
        unset($this->favorites);
    }

    #[Computed]
    public function favorites(): Collection
    {
        return Favorite::query()
            ->where('customer_id', auth()->id())
            ->with([
                'vendor' => fn ($query) => $query
                    ->select(['id', 'user_id', 'store_name', 'store_description', 'store_image', 'status', 'approved_at'])
                    ->withCount([
                        'products as active_products_count' => fn ($productQuery) => $productQuery->active(),
                    ]),
            ])
            ->latest('created_at')
            ->get();
    }
};
?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <span class="brand-kicker">{{ __('Suki system') }}</span>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Your followed stalls') }}</h1>
        <p class="max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Keep trusted stalls close, drop the ones you no longer need, and jump straight back into the storefront when you are ready to shop.') }}
        </p>
    </section>

    @if ($this->favorites->isNotEmpty())
        <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->favorites as $favorite)
                @php($vendor = $favorite->vendor)

                @if ($vendor !== null)
                    <article
                        wire:key="favorite-stall-{{ $favorite->id }}"
                        class="brand-panel flex h-full flex-col gap-5 p-5 transition {{ $vendor->status !== VendorStatus::Approved ? 'opacity-70' : '' }}"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <img
                                    src="{{ $vendor->store_image_url }}"
                                    alt="{{ $vendor->store_name }}"
                                    class="h-16 w-16 rounded-[1.5rem] object-cover"
                                >

                                <div class="min-w-0">
                                    <h2 class="truncate text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $vendor->store_name }}</h2>
                                    <p class="mt-2 line-clamp-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                                        {{ $vendor->store_description }}
                                    </p>
                                </div>
                            </div>

                            <livewire:vendor.follow-button :vendor="$vendor" :key="'favorites-follow-button-'.$favorite->id" />
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <span class="brand-badge">
                                {{ trans_choice(':count active listing|:count active listings', $vendor->active_products_count, ['count' => $vendor->active_products_count]) }}
                            </span>

                            @if ($vendor->status !== VendorStatus::Approved)
                                <span class="brand-badge">{{ __('No longer active') }}</span>
                            @endif
                        </div>

                        @if ($vendor->status === VendorStatus::Approved)
                            <a href="{{ route('shop.vendors.show', $vendor) }}" wire:navigate class="brand-button-secondary mt-auto">
                                {{ __('Visit stall') }}
                            </a>
                        @endif
                    </article>
                @endif
            @endforeach
        </section>
    @else
        <section class="brand-panel px-6 py-16 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl text-neutral-600 dark:text-zinc-300" style="background-color: color-mix(in oklab, var(--brand-50) 72%, white 28%);">
                <i class="fa-regular fa-heart text-xl"></i>
            </span>
            <h2 class="brand-serif mt-5 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('No followed stalls yet') }}</h2>
            <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                {{ __('Browse approved vendors and start building your own suki shortlist.') }}
            </p>
            <a href="{{ route('shop.vendors') }}" wire:navigate class="brand-button-primary mt-6">
                {{ __('Browse vendors') }}
            </a>
        </section>
    @endif
</div>
