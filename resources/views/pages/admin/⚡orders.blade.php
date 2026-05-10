<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Marketplace Order Oversight')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: '')]
    public string $search = '';

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        return Order::query()
            ->with([
                'customer:id,name',
                'vendor:id,store_name',
                'payment:id,order_id,method,status',
            ])
            ->when(
                filled($this->statusFilter),
                fn ($query) => $query->where('order_status', $this->statusFilter),
            )
            ->when(
                filled($this->search),
                function ($query): void {
                    $searchTerm = trim($this->search);

                    $query->where(function ($builder) use ($searchTerm): void {
                        $builder
                            ->where('id', 'like', '%'.$searchTerm.'%')
                            ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', '%'.$searchTerm.'%'))
                            ->orWhereHas('vendor', fn ($vendorQuery) => $vendorQuery->where('store_name', 'like', '%'.$searchTerm.'%'));
                    });
                },
            )
            ->latest('created_at')
            ->paginate(20);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function statusCounts(): array
    {
        $counts = ['' => Order::query()->count()];

        foreach (OrderStatus::cases() as $status) {
            $counts[$status->value] = Order::query()
                ->where('order_status', $status)
                ->count();
        }

        return $counts;
    }

    public function paginationView(): string
    {
        return 'layouts.app.livewire-paginate';
    }
};
?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <a href="{{ route('admin.dashboard') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-neutral-500 transition hover:text-neutral-900 dark:text-zinc-400 dark:hover:text-zinc-100">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            {{ __('Return to Dashboard') }}
        </a>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Marketplace order oversight') }}</h1>
        <p class="max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Watch customer orders across every stall, filter by fulfillment stage, and review payment context without stepping into vendor workflows.') }}
        </p>
    </section>

    <section class="brand-panel p-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="flex flex-wrap gap-3">
                @foreach ([
                    '' => __('All'),
                    OrderStatus::Pending->value => __('Pending'),
                    OrderStatus::Confirmed->value => __('Confirmed'),
                    OrderStatus::Preparing->value => __('Preparing'),
                    OrderStatus::Ready->value => __('Ready'),
                    OrderStatus::Delivered->value => __('Delivered'),
                    OrderStatus::Cancelled->value => __('Cancelled'),
                ] as $value => $label)
                    <button
                        type="button"
                        wire:click="$set('statusFilter', '{{ $value }}')"
                        class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition-all duration-150 active:scale-[0.97] {{ $statusFilter === $value ? 'text-white' : 'bg-white text-neutral-700 dark:bg-zinc-900 dark:text-zinc-100' }}"
                        style="{{ $statusFilter === $value
                            ? 'border-color: transparent; background-color: var(--brand-600);'
                            : 'border-color: rgb(231 229 228);' }}"
                    >
                        <span>{{ $label }}</span>
                        <span class="rounded-full bg-black/10 px-2 py-0.5 text-xs dark:bg-white/10">{{ $this->statusCounts[$value] }}</span>
                    </button>
                @endforeach
            </div>

            <div class="w-full max-w-md">
                <flux:input
                    wire:model.live.debounce.250ms="search"
                    :label="__('Search orders')"
                    type="search"
                    :placeholder="__('Search by order number, customer, or stall')"
                class="transition-shadow duration-150"
                />
            </div>
        </div>
    </section>

    <section wire:loading.class="opacity-60 blur-[0.5px]" wire:target="statusFilter,search,gotoPage,previousPage,nextPage" class="transition duration-200">
        @if ($this->orders->isNotEmpty())
            <div class="hidden transition-opacity duration-200 lg:block">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Order') }}</flux:table.column>
                        <flux:table.column>{{ __('Customer') }}</flux:table.column>
                        <flux:table.column>{{ __('Vendor') }}</flux:table.column>
                        <flux:table.column>{{ __('Total') }}</flux:table.column>
                        <flux:table.column>{{ __('Payment') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->orders as $order)
                            <flux:table.row :key="$order->id" wire:transition>
                                <flux:table.cell>{{ __('Order #:number', ['number' => str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]) }}</flux:table.cell>
                                <flux:table.cell>{{ $order->customer->name }}</flux:table.cell>
                                <flux:table.cell>{{ $order->vendor->store_name }}</flux:table.cell>
                                <flux:table.cell>{{ $order->formattedTotal() }}</flux:table.cell>
                                <flux:table.cell>
                                    <x-payment-status-badge :status="$order->payment->status" :label="Str::headline($order->payment->method->value).' - '.Str::headline($order->payment->status->value)" />
                                </flux:table.cell>
                                <flux:table.cell><x-order-status-badge :status="$order->order_status" /></flux:table.cell>
                                <flux:table.cell>{{ $order->created_at->format('M j, Y g:i A') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            <div class="grid gap-4 lg:hidden">
                @foreach ($this->orders as $order)
                    <article class="brand-panel suki-reveal p-5" style="transition-delay: {{ min($loop->index * 60, 360) }}ms" wire:key="admin-mobile-order-{{ $order->id }}" wire:transition>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="brand-kicker !mb-0">{{ __('Order #:number', ['number' => str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]) }}</p>
                                <h2 class="mt-3 text-lg font-semibold text-neutral-900 dark:text-zinc-100">{{ $order->customer->name }}</h2>
                                <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">{{ $order->vendor->store_name }}</p>
                            </div>

                            <x-order-status-badge :status="$order->order_status" />
                        </div>

                        <div class="mt-4 grid gap-2 text-sm text-neutral-500 dark:text-zinc-400">
                            <p>{{ $order->formattedTotal() }}</p>
                            <x-payment-status-badge :status="$order->payment->status" :label="Str::headline($order->payment->method->value).' - '.Str::headline($order->payment->status->value)" />
                            <p>{{ $order->created_at->format('M j, Y g:i A') }}</p>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($this->orders->hasPages())
                <div class="mt-8">
                    {{ $this->orders->onEachSide(1)->links(data: ['scrollTo' => 'body']) }}
                </div>
            @endif
        @else
            <div wire:transition class="brand-panel px-6 py-16 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl text-neutral-600 dark:text-zinc-300" style="background-color: color-mix(in oklab, var(--brand-50) 72%, white 28%);">
                    <i class="fa-solid fa-receipt text-xl"></i>
                </span>
                <h2 class="brand-serif mt-5 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('No orders match this view') }}</h2>
                <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                    {{ __('Try a different status or search term to find the order activity you need to inspect.') }}
                </p>
            </div>
        @endif
    </section>
</div>
