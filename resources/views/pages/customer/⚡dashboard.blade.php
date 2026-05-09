<?php

use App\Models\CartItem;
use App\Models\Favorite;
use App\Models\Notification;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Customer Dashboard')] class extends Component
{
    #[Computed]
    public function greeting(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour < 12 => __('Good morning'),
            $hour < 18 => __('Good afternoon'),
            default => __('Good evening'),
        };
    }

    #[Computed]
    public function recentOrders(): Collection
    {
        return Order::query()
            ->where('customer_id', auth()->id())
            ->with([
                'vendor:id,user_id,store_name,store_image',
                'payment:id,order_id,method,status',
            ])
            ->withCount('orderItems')
            ->latest('created_at')
            ->limit(3)
            ->get();
    }

    /**
     * @return array{count: int, subtotal: float}
     */
    #[Computed]
    public function cartSummary(): array
    {
        $items = CartItem::query()
            ->whereHas('cart', fn (Builder $builder): Builder => $builder->where('customer_id', auth()->id()))
            ->with('product:id,price')
            ->get();

        return [
            'count' => (int) $items->sum('quantity'),
            'subtotal' => (float) $items->sum(fn (CartItem $item): float => $item->quantity * (float) ($item->product?->price ?? 0)),
        ];
    }

    #[Computed]
    public function favoriteVendors(): Collection
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
            ->limit(4)
            ->get();
    }

    #[Computed]
    public function unreadNotificationCount(): int
    {
        return Notification::query()
            ->where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();
    }

    /**
     * @return array<int, array{label: string, value: string, note: string}>
     */
    #[Computed]
    public function stats(): array
    {
        $orderCount = Order::query()
            ->where('customer_id', auth()->id())
            ->count();

        $favoriteCount = Favorite::query()
            ->where('customer_id', auth()->id())
            ->count();

        return [
            [
                'label' => __('Orders placed'),
                'value' => number_format($orderCount),
                'note' => __('Across every market run'),
            ],
            [
                'label' => __('Cart items'),
                'value' => number_format($this->cartSummary['count']),
                'note' => __('Ready for checkout'),
            ],
            [
                'label' => __('Favourite stalls'),
                'value' => number_format($favoriteCount),
                'note' => __('Saved for later'),
            ],
            [
                'label' => __('Unread updates'),
                'value' => number_format($this->unreadNotificationCount),
                'note' => __('Waiting in your bell'),
            ],
        ];
    }

};
?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="brand-panel suki-reveal p-6 sm:p-8" style="transition-delay: 0ms">
            <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">
                {{ $this->greeting }}, {{ auth()->user()->name }}
            </h1>
            <p class="mt-4 max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
                {{ __('Pick up where you left off, revisit trusted stalls, and keep an eye on your latest market orders from one place.') }}
            </p>

            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('shop.home') }}" wire:navigate class="brand-button-primary inline-flex items-center gap-2">
                    <i class="fa-solid fa-store text-xs"></i>
                    {{ __('Browse storefront') }}
                </a>

                <a href="{{ route('shop.orders') }}" wire:navigate class="brand-button-secondary inline-flex items-center gap-2">
                    <i class="fa-solid fa-bag-shopping text-xs"></i>
                    {{ __('View all orders') }}
                </a>
            </div>
        </div>

        <aside class="brand-panel-muted suki-reveal p-6" style="transition-delay: 80ms">
            <p class="brand-kicker !mb-0">{{ __('Checkout progress') }}</p>
            <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Resume checkout') }}</h2>

            @if ($this->cartSummary['count'] > 0)
                <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                    {{ trans_choice(':count item is still waiting in your cart.|:count items are still waiting in your cart.', $this->cartSummary['count'], ['count' => $this->cartSummary['count']]) }}
                </p>
                <p class="mt-4 text-2xl font-semibold text-neutral-900 dark:text-zinc-100">
                    {{ __('₱:amount', ['amount' => number_format($this->cartSummary['subtotal'], 2)]) }}
                </p>
                <a href="{{ route('shop.checkout') }}" wire:navigate class="brand-button-primary mt-5 w-full">
                    {{ __('Continue to checkout') }}
                </a>
            @else
                <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                    {{ __('Your cart is empty right now. Browse the market to start your next suki run.') }}
                </p>
                <a href="{{ route('shop.home') }}" wire:navigate class="brand-button-secondary mt-5 w-full">
                    {{ __('Browse the market') }}
                </a>
            @endif
        </aside>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->stats as $stat)
            <article class="brand-panel-muted suki-reveal p-5" wire:key="customer-dashboard-stat-{{ Str::slug($stat['label']) }}" style="transition-delay: {{ $loop->index * 60 }}ms">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">
                    {{ $stat['label'] }}
                </p>
                <p class="mt-3 text-3xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $stat['value'] }}</p>
                <p class="mt-2 text-sm text-neutral-500 dark:text-zinc-400">{{ $stat['note'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="grid gap-8 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
        <div class="brand-panel p-6 sm:p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <span class="brand-kicker">{{ __('Latest orders') }}</span>
                    <h2 class="brand-serif mt-3 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Recent market runs') }}</h2>
                </div>

                <a href="{{ route('shop.orders') }}" wire:navigate class="text-sm font-semibold" style="color: var(--brand-700);">
                    {{ __('See all') }}
                </a>
            </div>

            <div class="mt-6 space-y-4">
                @forelse ($this->recentOrders as $order)
                    <article class="suki-reveal rounded-[1.75rem] border border-stone-200 bg-white/80 p-5 dark:border-white/10 dark:bg-zinc-900/80" wire:key="customer-dashboard-order-{{ $order->id }}" style="transition-delay: {{ min($loop->index * 60, 360) }}ms">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="brand-kicker !mb-0">{{ __('Order #:number', ['number' => str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]) }}</p>
                                <h3 class="mt-3 text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $order->vendor->store_name }}</h3>
                                <p class="mt-2 text-sm text-neutral-500 dark:text-zinc-400">
                                    {{ trans_choice(':count item|:count items', $order->order_items_count, ['count' => $order->order_items_count]) }}
                                    <span aria-hidden="true">-</span>
                                    {{ $order->created_at->format('M j, Y g:i A') }}
                                </p>
                            </div>

                            <div class="flex flex-col items-start gap-3 sm:items-end">
                                <x-order-status-badge :status="$order->order_status" />
                                <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ $order->formattedTotal() }}</p>
                                <a href="{{ route('shop.orders.show', ['orderReference' => $order->id]) }}" wire:navigate class="brand-button-secondary">
                                    {{ __('View order') }}
                                </a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-[1.75rem] border border-dashed border-stone-200 px-6 py-12 text-center dark:border-white/10">
                        <span class="brand-kicker">{{ __('No orders yet') }}</span>
                        <p class="mt-4 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                            {{ __('Once you place your first order, the latest updates will show up here.') }}
                        </p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="brand-panel p-6 sm:p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <span class="brand-kicker">{{ __('Suki system') }}</span>
                    <h2 class="brand-serif mt-3 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Favourite stalls') }}</h2>
                </div>

                <a href="{{ route('shop.favorites') }}" wire:navigate class="text-sm font-semibold" style="color: var(--brand-700);">
                    {{ __('View all') }}
                </a>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                @forelse ($this->favoriteVendors as $favorite)
                    @php($vendor = $favorite->vendor)
                    @if ($vendor !== null)
                        <div class="suki-reveal" style="transition-delay: {{ $loop->index * 80 }}ms">
                            <x-vendor-card :vendor="$vendor" :compact="true" wire:key="customer-dashboard-favourite-{{ $favorite->id }}" />
                        </div>
                    @endif
                @empty
                    <x-empty-state
                        class="sm:col-span-2 xl:col-span-1"
                        icon="fa-regular fa-heart"
                        :heading="__('No favourites yet')"
                        :body="__('Follow a few trusted stalls to keep them close on your dashboard.')"
                    />
                @endforelse
            </div>
        </div>
    </section>
</div>
