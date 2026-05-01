<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\VendorStatus;
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
    public function mount(): void
    {
        if (! $this->hasApprovedVendorProfile()) {
            $this->redirectRoute('customer.dashboard', navigate: true);
        }
    }

    #[Computed]
    public function vendorProfile(): VendorProfile
    {
        $vendorProfile = auth()->user()->vendorProfile;

        abort_if($vendorProfile === null || $vendorProfile->status !== VendorStatus::Approved, 403);

        return $vendorProfile;
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
            ->where('stock_quantity', '<=', 5)
            ->orderBy('stock_quantity')
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'stock_quantity']);
    }

    private function hasApprovedVendorProfile(): bool
    {
        return auth()->user()->vendorProfile?->status === VendorStatus::Approved;
    }
};
?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="brand-panel p-6 sm:p-8">
        <span class="brand-kicker">{{ __('Vendor home') }}</span>
        <h1 class="brand-serif mt-4 text-4xl font-bold text-neutral-900 dark:text-zinc-100">
            {{ __('Welcome back to :store', ['store' => $this->vendorProfile->store_name]) }}
        </h1>
        <p class="mt-4 max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Check what needs attention, keep new orders moving, and review how your stall is performing today.') }}
        </p>

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('vendor.products.create') }}" wire:navigate class="brand-button-primary">
                <i class="fa-solid fa-plus text-xs"></i>
                {{ __('Add product') }}
            </a>
            <a href="{{ route('vendor.orders') }}" wire:navigate class="brand-button-secondary inline-flex items-center gap-2">
                <i class="fa-solid fa-bag-shopping text-xs"></i>
                {{ __('View all orders') }}
            </a>
            <a href="{{ route('vendor.valued-customers') }}" wire:navigate class="brand-button-secondary">{{ __('Valued customers') }}</a>
            <a href="{{ route('vendor.sales') }}" wire:navigate class="brand-button-secondary">{{ __('Sales overview') }}</a>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->stats as $stat)
            <article class="brand-panel-muted p-5" wire:key="vendor-dashboard-stat-{{ \Illuminate\Support\Str::slug($stat['label']) }}">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ $stat['label'] }}</p>
                <p class="mt-3 text-3xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $stat['value'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="grid gap-8 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
        <div class="brand-panel p-6 sm:p-8">
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
                    <article class="rounded-[1.75rem] border border-stone-200 bg-white/80 p-5 dark:border-white/10 dark:bg-zinc-900/80" wire:key="vendor-dashboard-order-{{ $order->id }}">
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
                                <a href="{{ route('vendor.orders.show', ['orderReference' => $order->id]) }}" wire:navigate class="brand-button-secondary">
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

        <aside class="brand-panel p-6 sm:p-8">
            <span class="brand-kicker">{{ __('Low stock alerts') }}</span>
            <h2 class="brand-serif mt-3 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Restock watch') }}</h2>

            <div class="mt-6 space-y-3">
                @forelse ($this->lowStockProducts as $product)
                    <article class="rounded-[1.5rem] border border-stone-200 bg-white/80 px-4 py-4 dark:border-white/10 dark:bg-zinc-900/80" wire:key="vendor-low-stock-{{ $product->id }}">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $product->name }}</p>
                                <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">
                                    {{ trans_choice(':count unit left|:count units left', $product->stock_quantity, ['count' => $product->stock_quantity]) }}
                                </p>
                            </div>

                            <a href="{{ route('vendor.products.edit', $product) }}" wire:navigate class="brand-button-secondary">
                                {{ __('Edit') }}
                            </a>
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
</div>
