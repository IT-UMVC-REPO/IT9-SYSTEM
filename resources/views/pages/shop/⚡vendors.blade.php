<?php

use App\Models\VendorProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Market Stalls')] class extends Component
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

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="brand-panel overflow-hidden p-6 sm:p-8">
        <span class="brand-kicker">{{ __('Vendor discovery') }}</span>
        <div class="mt-4 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-end">
            <div>
                <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Browse market stalls') }}</h1>
                <p class="mt-4 max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
                    {{ __('Explore approved wet-market stalls, compare what each vendor carries, and follow the ones you want to revisit later.') }}
                </p>
            </div>

            <flux:input
                wire:model.live.debounce.250ms="search"
                :label="__('Search stalls')"
                type="search"
                :placeholder="__('Search by stall name or description')"
            />
        </div>
    </section>

    <section
        class="transition duration-200"
        wire:loading.class="opacity-60"
        wire:target="search,gotoPage,previousPage,nextPage"
    >
        @if ($this->vendors->isNotEmpty())
            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->vendors as $vendor)
                    <article wire:key="market-stall-{{ $vendor->id }}" class="brand-panel relative flex h-full flex-col overflow-hidden p-5">
                        <livewire:vendor.follow-button :vendor="$vendor" :overlay="true" :key="'vendor-directory-follow-button-'.$vendor->id" />

                        <div class="overflow-hidden rounded-[1.75rem] bg-stone-100 dark:bg-zinc-800">
                            <img
                                src="{{ $vendor->store_image_url }}"
                                alt="{{ $vendor->store_name }}"
                                class="aspect-[5/4] w-full object-cover"
                            >
                        </div>

                        <div class="flex flex-1 flex-col pt-5">
                            <div class="min-w-0 pr-10">
                                <p class="brand-kicker !mb-0">{{ __('Approved stall') }}</p>
                                <h2 class="mt-3 truncate text-2xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $vendor->store_name }}</h2>
                            </div>

                            <p class="mt-4 line-clamp-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                                {{ \Illuminate\Support\Str::limit($vendor->store_description, 80) }}
                            </p>

                            <div class="mt-5 flex flex-wrap items-center gap-3">
                                <span class="brand-badge">
                                    {{ trans_choice(':count active listing|:count active listings', $vendor->active_products_count, ['count' => $vendor->active_products_count]) }}
                                </span>
                                <span class="text-sm text-neutral-500 dark:text-zinc-400">{{ $vendor->user->name }}</span>
                            </div>

                            <a href="{{ route('shop.vendors.show', $vendor) }}" wire:navigate class="brand-button-secondary mt-auto">
                                {{ __('Visit stall') }}
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($this->vendors->hasPages())
                <div class="mt-8">
                    {{ $this->vendors->onEachSide(1)->links(data: ['scrollTo' => 'body']) }}
                </div>
            @endif
        @else
            <div class="brand-panel px-6 py-16 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl text-neutral-600 dark:text-zinc-300" style="background-color: color-mix(in oklab, var(--brand-50) 72%, white 28%);">
                    <i class="fa-solid fa-store-slash text-xl"></i>
                </span>
                <h2 class="brand-serif mt-5 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('No approved stalls found') }}</h2>
                <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                    {{ __('Try a different search term or come back after more vendors finish approval.') }}
                </p>
            </div>
        @endif
    </section>
</div>
