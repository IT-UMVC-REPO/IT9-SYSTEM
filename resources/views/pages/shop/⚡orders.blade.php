<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('My Orders')] class extends Component {
    use WithPagination;

    #[Url(except: 'all')]
    public string $status = 'all';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        return Order::query()
            ->where('customer_id', auth()->id())
            ->with([
                'vendor:id,user_id,store_name,store_image',
                'payment:id,order_id,method,status,reference_number,paid_at',
            ])
            ->withCount('orderItems')
            ->when(
                $this->status !== 'all',
                fn (Builder $query): Builder => match ($this->status) {
                    'pending' => $query->where('order_status', OrderStatus::Pending),
                    'active' => $query->whereIn('order_status', [
                        OrderStatus::Confirmed,
                        OrderStatus::Preparing,
                        OrderStatus::Ready,
                    ]),
                    'completed' => $query->where('order_status', OrderStatus::Delivered),
                    'cancelled' => $query->where('order_status', OrderStatus::Cancelled),
                    default => $query,
                },
            )
            ->latest()
            ->paginate(10);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function statusCounts(): array
    {
        $baseQuery = Order::query()->where('customer_id', auth()->id());

        return [
            'all' => (clone $baseQuery)->count(),
            'pending' => (clone $baseQuery)->where('order_status', OrderStatus::Pending)->count(),
            'active' => (clone $baseQuery)->whereIn('order_status', [
                OrderStatus::Confirmed,
                OrderStatus::Preparing,
                OrderStatus::Ready,
            ])->count(),
            'completed' => (clone $baseQuery)->where('order_status', OrderStatus::Delivered)->count(),
            'cancelled' => (clone $baseQuery)->where('order_status', OrderStatus::Cancelled)->count(),
        ];
    }

    public function paginationView(): string
    {
        return 'layouts.app.livewire-paginate';
    }

    public function statusBadgeClasses(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Pending => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
            OrderStatus::Confirmed => 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-200',
            OrderStatus::Preparing => 'bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-200',
            OrderStatus::Ready => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200',
            OrderStatus::Delivered => 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-200',
            OrderStatus::Cancelled => 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200',
        };
    }

    public function paymentBadgeClasses(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::Pending => 'bg-stone-100 text-stone-700 dark:bg-zinc-700 dark:text-zinc-100',
            PaymentStatus::Paid => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200',
            PaymentStatus::Failed => 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200',
        };
    }

    public function emptyStateHeading(): string
    {
        return match ($this->status) {
            'pending' => 'No pending orders',
            'active' => 'No active orders',
            'completed' => 'No completed orders yet',
            'cancelled' => 'No cancelled orders',
            default => 'No orders yet',
        };
    }

    public function emptyStateBody(): string
    {
        return match ($this->status) {
            'pending' => 'Once you place an order, it will stay here until your vendor confirms it.',
            'active' => 'Confirmed, preparing, and ready orders will appear here while they move through the market.',
            'completed' => 'Delivered orders will show up here after your vendor marks them complete.',
            'cancelled' => 'Cancelled orders will stay here for reference if you need to look back.',
            default => 'Browse the storefront and place your first market order to start tracking it here.',
        };
    }
}; ?>

<section class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4">
        <span class="inline-flex w-fit rounded-full border border-emerald-800/50 bg-emerald-950/40 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-400">
            {{ __('Order tracking') }}
        </span>
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="space-y-2">
                <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('My orders') }}</h1>
                <p class="max-w-2xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
                    {{ __('Review every market run, check payment progress, and jump into the details when you need an update.') }}
                </p>
            </div>

            <p
                class="hidden items-center gap-2 text-xs font-medium text-neutral-500 dark:text-zinc-400 sm:inline-flex"
                wire:loading.flex
                wire:target="status,gotoPage,previousPage,nextPage"
            >
                <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                {{ __('Refreshing orders...') }}
            </p>
        </div>
    </div>

    <div class="brand-panel-muted grid gap-3 rounded-[2rem] p-3 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([
            'all' => 'All',
            'pending' => 'Pending',
            'active' => 'Active',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ] as $value => $label)
            <button
                type="button"
                wire:key="orders-tab-{{ $value }}"
                wire:click="$set('status', '{{ $value }}')"
                wire:loading.attr="disabled"
                class="flex items-center justify-between gap-3 rounded-[1.5rem] px-4 py-3 text-left transition"
                @class([
                    'brand-accent-pill border border-transparent' => $status === $value,
                    'border border-stone-200 bg-white text-neutral-700 hover:border-stone-300 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:border-white/20' => $status !== $value,
                ])
            >
                <span class="font-semibold">{{ __($label) }}</span>
                <span class="rounded-full bg-black/5 px-2.5 py-1 text-xs font-semibold dark:bg-white/10">
                    {{ $this->statusCounts[$value] }}
                </span>
            </button>
        @endforeach
    </div>

    <div
        class="space-y-5 transition duration-200"
        wire:loading.class="opacity-60"
        wire:target="status,gotoPage,previousPage,nextPage"
    >
        @if ($this->orders->isNotEmpty())
            @foreach ($this->orders as $order)
                <article wire:key="customer-order-{{ $order->id }}" class="brand-panel overflow-hidden p-6 sm:p-7">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex min-w-0 gap-4">
                            <div class="h-16 w-16 shrink-0 overflow-hidden rounded-[1.5rem] border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-800">
                                <img
                                    src="{{ $order->vendor->store_image_url }}"
                                    alt="{{ $order->vendor->store_name }}"
                                    class="h-full w-full object-cover"
                                    onerror="this.src='https://placehold.co/320x320/e7e5e4/9ca3af?text=Store'"
                                >
                            </div>

                            <div class="min-w-0 space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="brand-kicker !mb-0">{{ __('Order #:number', ['number' => str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]) }}</p>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $this->statusBadgeClasses($order->order_status) }}">
                                        {{ Str::headline($order->order_status->value) }}
                                    </span>
                                </div>

                                <h2 class="brand-serif text-2xl font-bold text-neutral-900 dark:text-zinc-100">
                                    {{ $order->vendor->store_name }}
                                </h2>

                                <div class="flex flex-wrap items-center gap-3 text-sm text-neutral-500 dark:text-zinc-400">
                                    <span>{{ trans_choice(':count item|:count items', $order->order_items_count, ['count' => $order->order_items_count]) }}</span>
                                    <span aria-hidden="true">-</span>
                                    <span>{{ $order->formattedTotal() }}</span>
                                    <span aria-hidden="true">-</span>
                                    <span>{{ $order->created_at->format('M j, Y g:i A') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col items-start gap-3 lg:items-end">
                            <div class="flex flex-wrap gap-2">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $this->paymentBadgeClasses($order->payment_status) }}">
                                    {{ Str::headline($order->payment->method->value).' - '.Str::headline($order->payment_status->value) }}
                                </span>
                            </div>

                            <a href="{{ route('shop.orders.show', ['orderReference' => $order->id]) }}" class="brand-button-secondary">
                                {{ __('View details') }}
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach

            @if ($this->orders->hasPages())
                <div>
                    {{ $this->orders->onEachSide(1)->links(data: ['scrollTo' => 'body']) }}
                </div>
            @endif
        @else
            <div class="brand-panel px-6 py-14 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-neutral-400 dark:bg-zinc-800 dark:text-zinc-400">
                    <i class="fa-solid fa-basket-shopping text-xl"></i>
                </span>
                <h2 class="brand-serif mt-5 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __($this->emptyStateHeading()) }}</h2>
                <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                    {{ __($this->emptyStateBody()) }}
                </p>
                <a href="{{ route('shop.home') }}" class="brand-button-primary mt-6 inline-flex">
                    <span class="text-accent-foreground">{{ __('Browse the market') }}</span>
                </a>
            </div>
        @endif
    </div>
</section>
