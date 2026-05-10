<?php

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
                    ->with('user:id,name')
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
        <span class="brand-kicker">{{ __('Your Local Stall') }}</span>
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
                    <div class="suki-reveal" style="transition-delay: {{ $loop->index * 80 }}ms" wire:key="favorite-stall-{{ $favorite->id }}" wire:transition>
                        <x-vendor-card :vendor="$vendor" />
                    </div>
                @endif
            @endforeach
        </section>
    @else
        <x-empty-state
            icon="fa-regular fa-heart"
            :heading="__('No followed stalls yet')"
            :body="__('Browse approved vendors and start building your own favorite stall shortlist.')"
            :action-label="__('Browse vendors')"
            :action-route="route('shop.vendors')"
        />
    @endif
</div>
