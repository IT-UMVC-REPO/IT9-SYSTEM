<?php

use App\Concerns\HasVendorGuard;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\VendorProfile;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Vendor Dashboard')] class extends Component
{
    use HasVendorGuard;

    #[Computed]
    public function vendorProfile(): VendorProfile
    {
        return $this->approvedVendorProfile();
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    #[Computed]
    public function stats(): array
    {
        $vendorId = $this->vendorProfile->getKey();

        return [
            [
                'label' => __('Total products'),
                'value' => number_format(Product::query()->forVendor($vendorId)->count()),
            ],
            [
                'label' => __('Active products'),
                'value' => number_format(Product::query()->forVendor($vendorId)->active()->count()),
            ],
            [
                'label' => __('Pending orders'),
                'value' => number_format(Order::query()->where('vendor_id', $vendorId)->where('order_status', OrderStatus::Pending)->count()),
            ],
            [
                'label' => __('Total revenue'),
                'value' => __('₱:amount', [
                    'amount' => number_format((float) Payment::query()
                        ->where('status', PaymentStatus::Paid)
                        ->whereHas('order', fn ($query) => $query->where('vendor_id', $vendorId))
                        ->sum('amount'), 2),
                ]),
            ],
        ];
    }

    #[Computed]
    public function recentOrders(): Collection
    {
        return Order::query()
            ->where('vendor_id', $this->vendorProfile->getKey())
            ->with([
                'customer:id,name',
                'payment:id,order_id,method,status',
            ])
            ->withCount('orderItems')
            ->latest('created_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function lowStockProducts(): Collection
    {
        return Product::query()
            ->forVendor($this->vendorProfile->getKey())
            ->active()
            ->where('stock_quantity', '<=', 10)
            ->orderBy('stock_quantity')
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'stock_quantity', 'unit']);
    }

};
?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="brand-panel suki-reveal p-6 sm:p-8" style="transition-delay: 0ms">
        <h1 class="brand-serif suki-reveal text-4xl font-bold text-neutral-900 dark:text-zinc-100" style="transition-delay: 0ms">
            {{ __('Welcome back to :store', ['store' => $this->vendorProfile->store_name]) }}
        </h1>
        <p class="suki-reveal mt-4 max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400" style="transition-delay: 80ms">
            {{ __('Check what needs attention, keep new orders moving, and review how your stall is performing today.') }}
        </p>

        <div class="suki-reveal mt-6 flex flex-wrap gap-3" style="transition-delay: 160ms">
            <a href="{{ route('vendor.products.create') }}" wire:navigate class="brand-button-primary active:scale-[0.96]">
                <i class="fa-solid fa-plus text-xs"></i>
                {{ __('Add product') }}
            </a>
            <a href="{{ route('vendor.orders') }}" wire:navigate class="brand-button-secondary active:scale-[0.96] inline-flex items-center gap-2">
                <i class="fa-solid fa-bag-shopping text-xs"></i>
                {{ __('View all orders') }}
            </a>
            <a href="{{ route('vendor.valued-customers') }}" wire:navigate class="brand-button-secondary active:scale-[0.96]">{{ __('Valued customers') }}</a>
            <a href="{{ route('vendor.sales') }}" wire:navigate class="brand-button-secondary active:scale-[0.96]">{{ __('Sales overview') }}</a>
        </div>
    </section>

    <section class="brand-panel suki-reveal p-6" style="transition-delay: 100ms">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-3 border-b border-stone-200 pb-5 dark:border-white/10">
            @foreach ($this->stats as $stat)
                <div class="suki-reveal flex items-baseline gap-2" wire:key="vendor-dashboard-stat-{{ \Illuminate\Support\Str::slug($stat['label']) }}" style="transition-delay: {{ $loop->index * 60 }}ms">
                    <span class="text-2xl font-bold tabular-nums text-neutral-900 dark:text-zinc-100">{{ $stat['value'] }}</span>
                    <span class="text-sm font-medium text-neutral-500 dark:text-zinc-400">{{ $stat['label'] }}</span>
                </div>
                @if (! $loop->last)
                    <div class="h-6 w-px bg-stone-200 dark:bg-white/10"></div>
                @endif
            @endforeach
        </div>
    </section>

    <section class="grid gap-8 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
        <div class="brand-panel suki-reveal p-6 sm:p-8" style="transition-delay: 180ms">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <span class="brand-kicker">{{ __('Recent orders') }}</span>
                    <h2 class="brand-serif mt-3 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Latest customer orders') }}</h2>
                </div>

                <a href="{{ route('vendor.orders') }}" wire:navigate class="text-sm font-semibold" style="color: var(--brand-700);">
                    {{ __('See queue') }}
                </a>
            </div>

            <div class="mt-6 space-y-4">
                @forelse ($this->recentOrders as $order)
                    <article class="rounded-[1.75rem] border border-stone-200 bg-white/80 p-5 dark:border-white/10 dark:bg-zinc-900/80" wire:key="vendor-dashboard-order-{{ $order->id }}" wire:transition>
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="brand-kicker !mb-0">{{ __('Order #:number', ['number' => str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]) }}</p>
                                <h3 class="mt-3 text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $order->customer->name }}</h3>
                                <p class="mt-2 text-sm text-neutral-500 dark:text-zinc-400">
                                    {{ trans_choice(':count item|:count items', $order->order_items_count, ['count' => $order->order_items_count]) }}
                                    <span aria-hidden="true">-</span>
                                    {{ $order->created_at->format('M j, Y g:i A') }}
                                </p>
                            </div>

                            <div class="flex flex-col items-start gap-3 sm:items-end">
                                <span class="brand-badge">{{ \Illuminate\Support\Str::headline($order->order_status->value) }}</span>
                                <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ $order->formattedTotal() }}</p>
                                <a href="{{ route('vendor.orders.show', ['orderReference' => $order->id]) }}" wire:navigate class="brand-button-secondary active:scale-[0.96]">
                                    {{ __('View order') }}
                                </a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-[1.75rem] border border-dashed border-stone-200 px-6 py-12 text-center dark:border-white/10">
                        <span class="brand-kicker">{{ __('No orders yet') }}</span>
                        <p class="mt-4 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                            {{ __('New customer orders will appear here as soon as your stall starts receiving them.') }}
                        </p>
                    </div>
                @endforelse
            </div>
        </div>

        <aside class="brand-panel suki-reveal p-6 sm:p-8" style="transition-delay: 240ms">
            <span class="brand-kicker">{{ __('Low stock alerts') }}</span>
            <h2 class="brand-serif mt-3 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Restock watch') }}</h2>

            <div class="mt-6 space-y-3">
                @forelse ($this->lowStockProducts as $product)
                    <article class="rounded-[1.5rem] border border-stone-200 bg-white/80 px-4 py-4 dark:border-white/10 dark:bg-zinc-900/80" wire:key="vendor-low-stock-{{ $product->id }}" wire:transition>
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $product->name }}</p>
                                <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">
                                    {{ __(':stock remaining', ['stock' => $product->unitLabel()]) }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <a href="{{ route('vendor.products') }}" wire:navigate class="brand-button-secondary active:scale-[0.96] text-sm px-3 py-2">
                                    {{ __('Edit') }}
                                </a>
                                <a href="{{ route('vendor.products') }}" wire:navigate class="brand-button-secondary active:scale-[0.96] text-sm px-3 py-2">
                                    {{ __('Restock') }}
                                </a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-[1.75rem] border border-dashed border-stone-200 px-6 py-12 text-center dark:border-white/10">
                        <p class="text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                            {{ __('No low-stock alerts right now. Your active catalogue has comfortable inventory levels.') }}
                        </p>
                    </div>
                @endforelse
            </div>
        </aside>
    </section>

    <section class="brand-panel suki-reveal p-6 sm:p-8" style="transition-delay: 300ms">
        <div class="flex items-center justify-between gap-4">
            <div>
                <span class="brand-kicker">{{ __('Customer map') }}</span>
                <h2 class="brand-serif mt-3 text-3xl font-bold text-neutral-900 dark:text-zinc-100">
                    {{ __('Where your suki buyers are') }}
                </h2>
            </div>
        </div>

        <div
            x-data="sukiVendorMap()"
            x-init="initMap('suki-vendor-map-dashboard')"
            class="mt-6"
        >
            <div class="overflow-hidden rounded-2xl">
                <div id="suki-vendor-map-dashboard" class="h-[400px] w-full"></div>
            </div>

            <div class="mt-4 flex gap-3" x-cloak>
                <button
                    type="button"
                    x-on:click="toggleCustomers()"
                    x-bind:class="showCustomers ? 'brand-button-primary active:scale-[0.96]' : 'brand-button-secondary active:scale-[0.96]'"
                    class="rounded-xl text-sm"
                >
                    <span x-text="showCustomers ? @js(__('Hide customers')) : @js(__('Show my customers'))"></span>
                </button>
            </div>
        </div>
    </section>
</div>
