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
</div>
