<?php

use App\Models\VendorProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Browse Vendors & Find Stalls')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function vendors(): LengthAwarePaginator
    {
        return VendorProfile::query()
            ->approved()
            ->with(['user:id,name'])
            ->withCount([
                'products as active_products_count' => fn ($query) => $query->active(),
            ])
            ->when(
                filled($this->search),
                function ($query): void {
                    $searchTerm = trim($this->search);

                    $query->where(function ($builder) use ($searchTerm): void {
                        $builder
                            ->where('store_name', 'like', '%'.$searchTerm.'%')
                            ->orWhere('store_description', 'like', '%'.$searchTerm.'%');
                    });
                },
            )
            ->latest('approved_at')
            ->paginate(12);
    }

    public function paginationView(): string
    {
        return 'layouts.app.livewire-paginate';
    }
};
?>

<div
    x-data="{
        showMap: true,
        selectedVendor: null,
        activeProductSingular: @js(__('active product')),
        activeProductPlural: @js(__('active products')),
        toggleMap() {
            this.showMap = ! this.showMap;
            this.$nextTick(() => window.dispatchEvent(new CustomEvent('vendor-map-resize')));
        },
        truncateDescription(value) {
            const text = String(value ?? '');

            return text.length > 120 ? `${text.slice(0, 117)}...` : text;
        },
        activeProductLabel() {
            const count = Number(this.selectedVendor?.active_products_count ?? 0);
            const noun = count === 1 ? this.activeProductSingular : this.activeProductPlural;

            return `${count.toLocaleString()} ${noun}`;
        },
    }"
    x-on:vendor-selected.window="selectedVendor = $event.detail"
    x-on:keydown.escape.window="selectedVendor = null"
    class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8"
>
    <section class="brand-panel overflow-hidden p-6 sm:p-8">
        <span class="brand-kicker">{{ __('Discover Stalls') }}</span>
        <div class="mt-4 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,26rem)] lg:items-end">
            <div>
                <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Browse Vendors & Find Stalls') }}</h1>
                <p class="mt-4 max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
                    {{ __('Explore approved wet-market stalls, compare what each vendor carries, and use the map to find nearby suki spots around Tagum City.') }}
                </p>
            </div>

            <div class="grid gap-4">
                <button
                    type="button"
                    x-on:click="toggleMap()"
                    class="brand-button-secondary w-full justify-center"
                >
                    <i class="fa-solid fa-map-location-dot text-xs"></i>
                    <span x-show="! showMap">{{ __('Show Map') }}</span>
                    <span x-show="showMap">{{ __('Hide Map') }}</span>
                </button>

                <flux:input
                    wire:model.live.debounce.250ms="search"
                    :label="__('Search stalls')"
                    type="search"
                    :placeholder="__('Search by stall name or description')"
                />
            </div>
        </div>
    </section>

    <section
        x-cloak
        x-show="showMap"
        x-transition.opacity
        class="brand-panel overflow-hidden p-4 transition sm:p-5"
    >
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="brand-kicker !mb-0">{{ __('Find stalls') }}</p>
                <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Tagum vendor map') }}</h2>
            </div>
        </div>

        <x-vendor-location-map browse height="400px" map-id="shop-vendors-map" />
    </section>

    <section
        class="transition duration-200"
        wire:loading.class="opacity-60"
        wire:target="search,gotoPage,previousPage,nextPage"
    >
        @if ($this->vendors->isNotEmpty())
            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->vendors as $vendor)
                    <x-vendor-card :vendor="$vendor" wire:key="market-stall-{{ $vendor->id }}" />
                @endforeach
            </div>

            @if ($this->vendors->hasPages())
                <div class="mt-8">
                    {{ $this->vendors->onEachSide(1)->links(data: ['scrollTo' => 'body']) }}
                </div>
            @endif
        @else
            <x-empty-state
                icon="fa-solid fa-store-slash"
                :heading="__('No approved stalls found')"
                :body="__('Try a different search term or come back after more vendors finish approval.')"
            />
        @endif
    </section>

    <div
        x-cloak
        x-show="selectedVendor"
        x-transition.opacity
        class="fixed inset-0 z-[90] flex items-center justify-center bg-neutral-950/55 px-4 py-6 backdrop-blur-sm"
    >
        <section
            x-show="selectedVendor"
            x-transition.scale.origin.center
            x-on:click.outside="selectedVendor = null"
            class="w-full max-w-md overflow-hidden rounded-[1.75rem] border border-white/40 bg-white shadow-2xl dark:border-white/10 dark:bg-zinc-900"
        >
            <div class="flex items-start gap-4 border-b border-stone-200 p-5 dark:border-white/10">
                <img
                    x-bind:src="selectedVendor?.image || 'https://placehold.co/160x160/e7e5e4/9ca3af?text=Store'"
                    x-bind:alt="selectedVendor?.name || @js(__('Vendor stall'))"
                    class="h-16 w-16 shrink-0 rounded-2xl object-cover"
                >

                <div class="min-w-0 flex-1">
                    <p class="brand-kicker !mb-0">{{ __('Selected stall') }}</p>
                    <h3 class="mt-2 text-xl font-bold text-neutral-900 dark:text-zinc-100" x-text="selectedVendor?.name"></h3>
                    <p class="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-neutral-400 dark:text-zinc-500" x-text="activeProductLabel()"></p>
                </div>

                <button
                    type="button"
                    x-on:click="selectedVendor = null"
                    class="brand-button-secondary inline-flex h-9 w-9 items-center justify-center p-0"
                    aria-label="{{ __('Close stall details') }}"
                >
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <div class="space-y-5 p-5">
                <p class="text-sm leading-7 text-neutral-600 dark:text-zinc-300" x-text="truncateDescription(selectedVendor?.description)"></p>
                <p class="text-sm font-medium text-neutral-500 dark:text-zinc-400" x-text="selectedVendor?.address"></p>

                <a
                    x-bind:href="selectedVendor?.profileUrl || '#'"
                    wire:navigate
                    class="brand-button-primary w-full"
                >
                    {{ __('Visit stall') }}
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </section>
    </div>
</div>
