<?php

use App\Concerns\HasRiderGuard;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Rider Deliveries')] class extends Component
{
    use HasRiderGuard;
    use WithPagination;

    #[Computed]
    public function deliveries(): LengthAwarePaginator
    {
        return Order::query()
            ->where('rider_id', auth()->id())
            ->whereIn('order_status', [OrderStatus::PickedUp, OrderStatus::OutForDelivery])
            ->with(['vendor:id,store_name,vendor_address', 'customer:id,name,address'])
            ->withCount('orderItems')
            ->latest('updated_at')
            ->paginate(12);
    }

    public function paginationView(): string
    {
        return 'layouts.app.livewire-paginate';
    }
}; ?>

<section class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4">
        <a href="{{ route('rider.dashboard') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-neutral-500 transition hover:text-neutral-900 dark:text-zinc-400 dark:hover:text-zinc-100">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            {{ __('Back to dashboard') }}
        </a>
        <span class="brand-kicker">{{ __('Deliveries') }}</span>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Active deliveries') }}</h1>
        <p class="max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Open assigned deliveries, review pickup and drop-off details, and update the customer-facing status.') }}
        </p>
    </div>

    @if ($this->deliveries->isNotEmpty())
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($this->deliveries as $order)
                <article class="brand-panel p-5" wire:key="rider-delivery-{{ $order->id }}">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="brand-kicker !mb-0">{{ __('Order #:number', ['number' => str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]) }}</p>
                            <h2 class="mt-3 text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $order->customer->name }}</h2>
                            <p class="mt-2 text-sm leading-6 text-neutral-500 dark:text-zinc-400">{{ $order->customer->address ?: $order->delivery_address }}</p>
                        </div>
                        <x-order-status-badge :status="$order->order_status" />
                    </div>

                    <div class="mt-5 grid gap-2 text-sm text-neutral-500 dark:text-zinc-400">
                        <p>{{ __('Pickup: :vendor', ['vendor' => $order->vendor->store_name]) }}</p>
                        <p>{{ trans_choice(':count item|:count items', $order->order_items_count, ['count' => $order->order_items_count]) }}</p>
                    </div>

                    <a href="{{ route('rider.deliveries.show', ['orderReference' => $order->id]) }}" wire:navigate class="brand-button-secondary mt-5 w-full transition-all duration-150 active:scale-[0.96]">
                        {{ __('Open delivery') }}
                    </a>
                </article>
            @endforeach
        </div>

        @if ($this->deliveries->hasPages())
            <div>{{ $this->deliveries->onEachSide(1)->links() }}</div>
        @endif
    @else
        <div class="brand-panel px-6 py-14 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-neutral-400 dark:bg-zinc-800 dark:text-zinc-400">
                <i class="fa-solid fa-box-open text-xl"></i>
            </span>
            <h2 class="brand-serif mt-5 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('No active deliveries') }}</h2>
            <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">{{ __('Accepted delivery offers will appear here when you are assigned to an order.') }}</p>
        </div>
    @endif
</section>
