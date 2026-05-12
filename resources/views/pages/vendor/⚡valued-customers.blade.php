<?php

use App\Concerns\HasVendorGuard;
use App\Models\Order;
use App\Models\VendorCustomerStar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Valued Customers')] class extends Component
{
    use HasVendorGuard;
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function stars(): LengthAwarePaginator
    {
        return VendorCustomerStar::query()
            ->where('vendor_user_id', auth()->id())
            ->with('customer:id,name,email,address,created_at,profile_image')
            ->when(
                filled($this->search),
                function ($query): void {
                    $searchTerm = trim($this->search);

                    $query->whereHas('customer', function ($customerQuery) use ($searchTerm): void {
                        $customerQuery
                            ->where('name', 'like', '%'.$searchTerm.'%')
                            ->orWhere('email', 'like', '%'.$searchTerm.'%');
                    });
                },
            )
            ->latest('created_at')
            ->paginate(12);
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function sharedOrderCounts(): array
    {
        $customerIds = collect($this->stars->items())
            ->pluck('customer_id')
            ->all();

        if ($customerIds === []) {
            return [];
        }

        return Order::query()
            ->where('vendor_id', $this->vendorId())
            ->whereIn('customer_id', $customerIds)
            ->selectRaw('customer_id, COUNT(*) as aggregate')
            ->groupBy('customer_id')
            ->pluck('aggregate', 'customer_id')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }

    public function paginationView(): string
    {
        return 'layouts.app.livewire-paginate';
    }

    private function vendorId(): int
    {
        return $this->approvedVendorProfile()->getKey();
    }
};
?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <span class="brand-kicker">{{ __('Relationship tools') }}</span>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Valued customers') }}</h1>
        <p class="max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Keep track of customers you have starred, revisit their profiles quickly, and continue building repeat relationships.') }}
        </p>
    </section>

    <section class="brand-panel suki-reveal p-6">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
            <flux:input
                wire:model.live.debounce.250ms="search"
                :label="__('Search valued customers')"
                type="search"
                :placeholder="__('Search by customer name or email')"
            />

            <p class="text-sm font-semibold text-neutral-500 dark:text-zinc-400">
                {{ trans_choice(':count customer starred|:count customers starred', $this->stars->total(), ['count' => $this->stars->total()]) }}
            </p>
        </div>
    </section>

    <section
        class="transition duration-200"
        wire:loading.class="opacity-60 blur-[0.5px]"
        wire:target="search,gotoPage,previousPage,nextPage"
    >
        @if ($this->stars->isNotEmpty())
            <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->stars as $star)
                    @php($customer = $star->customer)
                    <article class="brand-panel suki-reveal p-5" style="transition-delay: {{ min($loop->index * 60, 360) }}ms" wire:key="valued-customer-{{ $star->id }}" wire:transition>
                        <div class="flex items-start gap-4">
                            <x-user-avatar :user="$customer" size="md" />

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h2 class="truncate text-lg font-semibold text-neutral-900 dark:text-zinc-100">{{ $customer->name }}</h2>
                                        <p class="truncate text-sm text-neutral-500 dark:text-zinc-400">{{ $customer->email }}</p>
                                    </div>

                                    <livewire:customer.star-button :customer="$customer" :key="'valued-star-'.$star->id" />
                                </div>

                                <p class="mt-3 text-sm text-neutral-600 dark:text-zinc-300">
                                    {{ $customer->address ?: __('Address not provided') }}
                                </p>

                                <div class="mt-4 flex flex-wrap items-center gap-3 text-xs font-semibold uppercase tracking-[0.18em] text-neutral-500 dark:text-zinc-400">
                                    <span>
                                        {{ trans_choice(':count order with your stall|:count orders with your stall', $this->sharedOrderCounts[$customer->id] ?? 0, ['count' => $this->sharedOrderCounts[$customer->id] ?? 0]) }}
                                    </span>
                                    <span aria-hidden="true">•</span>
                                    <span>{{ __('Starred :date', ['date' => $star->created_at->format('M j, Y')]) }}</span>
                                </div>

                                <div class="mt-5 flex gap-2">
                                    <a href="{{ route('shop.customers.show', $customer) }}" wire:navigate class="brand-button-secondary w-full text-center transition-all duration-150 active:scale-[0.96]">
                                        {{ __('Open profile') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($this->stars->hasPages())
                <div class="mt-8">
                    {{ $this->stars->onEachSide(1)->links(data: ['scrollTo' => 'body']) }}
                </div>
            @endif
        @else
            <div class="brand-panel px-6 py-16 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl text-neutral-600 dark:text-zinc-300" style="background-color: color-mix(in oklab, var(--brand-50) 72%, white 28%);">
                    <i class="fa-regular fa-star text-xl"></i>
                </span>
                <h2 class="brand-serif mt-5 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('No valued customers yet') }}</h2>
                <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                    {{ __('When you star customers from their profile page, they will appear here for quick follow-up.') }}
                </p>
            </div>
        @endif
    </section>
</div>
