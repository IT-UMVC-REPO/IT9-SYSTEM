<?php

use App\Enums\AuditEvent;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Order Detail')] class extends Component {
    public int $orderId;

    public function mount(string $orderReference): void
    {
        $order = Order::query()
            ->select('id', 'customer_id')
            ->findOrFail((int) $orderReference);

        abort_if($order->customer_id !== auth()->id(), 403);

        $this->orderId = $order->getKey();
    }

    public function cancelOrder(): void
    {
        $customer = auth()->user();

        try {
            $order = DB::transaction(function () use ($customer): Order {
                $order = Order::query()
                    ->with([
                        'vendor:id,user_id,store_name',
                        'payment:id,order_id,method,status',
                    ])
                    ->lockForUpdate()
                    ->findOrFail($this->orderId);

                abort_if($order->customer_id !== $customer->getKey(), 403);

                if ($order->order_status === OrderStatus::Cancelled) {
                    throw new RuntimeException('already-cancelled');
                }

                if ($order->order_status !== OrderStatus::Pending) {
                    throw new RuntimeException('not-cancellable');
                }

                $order->order_status = OrderStatus::Cancelled;

                if ($order->payment_method === PaymentMethod::Cod && $order->payment_status === PaymentStatus::Pending) {
                    $order->payment_status = PaymentStatus::Failed;
                    $order->payment?->update(['status' => PaymentStatus::Failed]);
                }

                $order->save();

                $order->orderItems()->with('product')->get()->each(function (OrderItem $item): void {
                    if ($item->product !== null) {
                        $item->product->increment('stock_quantity', $item->quantity);
                    }
                });

                AuditLogger::log(AuditEvent::OrderCancelled, "Order #{$order->id} cancelled.", $order);

                return $order->fresh([
                    'vendor:id,user_id,store_name',
                    'payment:id,order_id,method,status',
                ]);
            }, attempts: 5);
        } catch (RuntimeException $exception) {
            $message = match ($exception->getMessage()) {
                'already-cancelled' => __('This order has already been cancelled.'),
                'not-cancellable' => __('Only pending orders can be cancelled.'),
                default => throw $exception,
            };

            Flux::toast(variant: 'warning', text: $message);

            return;
        }

        SendOrderNotificationJob::dispatch(
            orderId: $order->getKey(),
            userId: $order->vendor->user_id,
            title: 'Order cancelled',
            message: 'Order #'.$order->getKey().' was cancelled by the customer.',
            type: NotificationType::OrderUpdate,
            broadcastOrderStatus: true,
        );

        Flux::toast(variant: 'success', text: __('Your order has been cancelled.'));
    }

    #[Computed]
    public function order(): Order
    {
        return Order::query()
            ->with([
                'orderItems.product.category',
                'orderItems.product.vendor',
                'payment',
                'customer:id,name,address,lat,lng',
                'vendor:id,user_id,store_name,vendor_address,lat,lng',
                'vendor.user:id,name',
                'messages' => fn ($query) => $query
                    ->with('sender:id,name')
                    ->latest('created_at')
                    ->limit(5),
            ])
            ->findOrFail($this->orderId);
    }

    /**
     * @return array<int, array{value: string, label: string, state: string}>
     */
    #[Computed]
    public function timelineSteps(): array
    {
        $order = $this->order;
        $statuses = [
            OrderStatus::Pending,
            OrderStatus::Confirmed,
            OrderStatus::Preparing,
            OrderStatus::Ready,
            OrderStatus::Delivered,
        ];

        if ($order->order_status === OrderStatus::Cancelled) {
            return collect($statuses)
                ->map(fn (OrderStatus $status): array => [
                    'value' => $status->value,
                    'label' => Str::headline($status->value),
                    'state' => $status === OrderStatus::Pending ? 'complete' : 'future',
                ])
                ->push([
                    'value' => OrderStatus::Cancelled->value,
                    'label' => 'Cancelled',
                    'state' => 'current-cancelled',
                ])
                ->all();
        }

        $currentIndex = array_search($order->order_status, $statuses, true);

        return collect($statuses)
            ->map(function (OrderStatus $status, int $index) use ($currentIndex): array {
                $state = match (true) {
                    $index < $currentIndex => 'complete',
                    $index === $currentIndex => 'current',
                    default => 'future',
                };

                return [
                    'value' => $status->value,
                    'label' => Str::headline($status->value),
                    'state' => $state,
                ];
            })
            ->all();
    }

    #[Computed]
    public function suggestedProducts()
    {
        $order = $this->order;
        $vendorId = $order->vendor_id;
        $categoryIds = $order->orderItems->pluck('product.category_id')->unique()->toArray();
        $orderProductIds = $order->orderItems->pluck('product_id')->toArray();

        $products = Product::query()
            ->visibleToCustomers()
            ->where('vendor_id', $vendorId)
            ->whereNotIn('id', $orderProductIds)
            ->inRandomOrder()
            ->limit(4)
            ->get();

        if ($products->count() < 4) {
            $extraProducts = Product::query()
                ->visibleToCustomers()
                ->whereIn('category_id', $categoryIds)
                ->whereNotIn('id', array_merge($orderProductIds, $products->pluck('id')->toArray()))
                ->inRandomOrder()
                ->limit(4 - $products->count())
                ->get();

            $products = $products->concat($extraProducts);
        }

        return $products;
    }

    public function maskedReference(?string $reference): ?string
    {
        if (blank($reference)) {
            return null;
        }

        return str_repeat('*', max(strlen($reference) - 6, 0)).substr($reference, -6);
    }

    public function stepMarkerClasses(string $state): string
    {
        return match ($state) {
            'complete' => 'border-transparent bg-[var(--brand-600)] text-white',
            'current' => 'border-transparent bg-[var(--brand-600)] text-white ring-4 ring-[color-mix(in_oklab,var(--brand-200),white_40%)] dark:ring-[color-mix(in_oklab,var(--brand-800),black_10%)]',
            'current-cancelled' => 'border-transparent bg-rose-500 text-white ring-4 ring-rose-200 dark:ring-rose-500/20',
            default => 'border-stone-200 bg-white text-stone-400 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-500',
        };
    }
}; ?>

<section
    x-data="{
        init() {
            if (!window.Echo || !@js(filled(config('broadcasting.connections.reverb.key')))) {
                return;
            }

            window.Echo.private('order.{{ $this->order->id }}')
                .listen('.OrderStatusUpdated', () => {
                    $wire.$refresh();
                });
        }
    }"
    x-init="init()"
    class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8"
>
    <div class="flex flex-col gap-4">
        <a href="{{ route('shop.orders') }}" wire:navigate class="mb-2 inline-flex items-center gap-2 text-sm font-semibold text-neutral-500 brand-hover-text dark:text-zinc-400">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            {{ __('Back to orders') }}
        </a>
        <span class="inline-flex w-fit rounded-full border border-[oklch(from_var(--brand-400)_l_c_h_/_0.32)] bg-[oklch(from_var(--brand-100)_l_c_h_/_0.6)] px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-[var(--brand-800)] dark:border-[oklch(from_var(--brand-500)_l_c_h_/_0.25)] dark:bg-[oklch(from_var(--brand-500)_l_c_h_/_0.15)] dark:text-[var(--brand-300)]">
            {{ __('Order tracker') }}
        </span>
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="space-y-2">
                <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">
                    {{ __('Order #:number', ['number' => str_pad((string) $this->order->id, 6, '0', STR_PAD_LEFT)]) }}
                </h1>
                <p class="max-w-2xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
                    {{ __('Track your order progress, review each item, and reach the vendor if you need help before delivery.') }}
                </p>
            </div>

            <x-order-status-badge :status="$this->order->order_status" />
        </div>
    </div>

    <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-6">
            <section class="brand-panel p-6 sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-2">
                        <p class="brand-kicker !mb-0">{{ __('Placed :time', ['time' => $this->order->created_at->format('M j, Y g:i A')]) }}</p>
                        <h2 class="brand-serif text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ $this->order->vendor->store_name }}</h2>
                        <a href="{{ route('shop.vendors.show', $this->order->vendor) }}" class="brand-hover-text inline-flex items-center gap-2 text-sm font-medium text-neutral-500 dark:text-zinc-400">
                            {{ __('Visit vendor storefront') }}
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>

                    <div class="rounded-[1.5rem] border border-stone-200 bg-stone-50 px-4 py-3 text-right dark:border-white/10 dark:bg-zinc-800">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Order total') }}</p>
                        <p class="mt-1 text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $this->order->formattedTotal() }}</p>
                    </div>
                </div>
            </section>

            <section class="brand-panel overflow-hidden">
                <div class="border-b border-stone-200 px-6 py-5 dark:border-white/10 sm:px-8">
                    <h2 class="brand-serif text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Line items') }}</h2>
                </div>

                <div class="divide-y divide-stone-200 dark:divide-white/10">
                    @foreach ($this->order->orderItems as $item)
                        <div wire:key="order-detail-item-{{ $item->id }}" wire:transition class="suki-reveal flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-8" style="transition-delay: {{ $loop->index * 80 }}ms">
                            <div class="flex min-w-0 items-center gap-4">
                                <div class="h-16 w-16 shrink-0 overflow-hidden rounded-[1.25rem] border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-800">
                                    <img src="{{ $item->product->image }}" alt="{{ $item->product->name }}" class="h-full w-full object-cover" loading="lazy">
                                </div>

                                <div class="min-w-0">
                                    <p class="truncate text-lg font-semibold text-neutral-900 dark:text-zinc-100">{{ $item->product->name }}</p>
                                    <p class="text-sm text-neutral-500 dark:text-zinc-400">{{ $item->product->category->name }}</p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-5 text-sm text-neutral-500 dark:text-zinc-400">
                                <span>
                                    {{ $item->quantity }} {{ $item->unit->abbreviation() }}
                                    @if ($item->product?->convertedQuantityLabel($item->quantity))
                                        ({{ __(':converted total', ['converted' => $item->product->convertedQuantityLabel($item->quantity)]) }})
                                    @endif
                                </span>
                                <span>{{ $item->unit->priceLabel($item->unit_price) }}</span>
                                <span class="font-semibold text-neutral-900 dark:text-zinc-100">
                                    {{ $item->lineTotal() }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="brand-panel-muted p-6">
                    <p class="brand-kicker !mb-0">{{ __('Delivery address') }}</p>
                    <p class="mt-3 text-sm leading-7 text-neutral-700 dark:text-zinc-300">{{ $this->order->delivery_address }}</p>
                </div>

                <div class="brand-panel-muted p-6">
                    <p class="brand-kicker !mb-0">{{ __('Notes') }}</p>
                    <p class="mt-3 text-sm leading-7 text-neutral-700 dark:text-zinc-300">
                        {{ $this->order->notes ?: __('No notes were added to this order.') }}
                    </p>
                </div>
            </section>

            <x-order-location-map
                :customer="$this->order->customer"
                :vendor-profile="$this->order->vendor"
                height="250px"
                customer-label="Delivery Point"
                vendor-label="Vendor Stall"
                vendor-marker="vendorOrange"
            />

            @if ($this->suggestedProducts->isNotEmpty())
                <section class="space-y-5">
                    <div class="flex items-center justify-between gap-3 pt-4">
                        <div>
                            <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('More products you might like') }}</h2>
                        </div>
                    </div>

                    <div class="grid gap-4 grid-cols-2">
                        @foreach ($this->suggestedProducts as $product)
                            <a href="{{ route('shop.products.show', $product) }}" wire:navigate class="brand-panel suki-reveal group flex items-center gap-4 overflow-hidden p-3 transition hover:scale-[1.02] hover:shadow-lg dark:bg-zinc-900/50" style="transition-delay: {{ min($loop->index * 80, 320) }}ms">
                                <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl">
                                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-110" loading="lazy">
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-400 dark:text-zinc-500">{{ $product->category->name }}</p>
                                    <h3 class="mt-0.5 truncate text-sm font-bold text-neutral-900 dark:text-zinc-100 group-hover:text-[var(--brand-600)]">{{ $product->name }}</h3>
                                    <p class="mt-1 text-sm font-bold text-[var(--brand-600)]">{{ $product->priceWithUnit() }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <aside class="space-y-6 xl:sticky xl:top-24 xl:self-start">
            <section class="brand-panel space-y-5 p-6">
                <div>
                    <p class="brand-kicker !mb-0">{{ __('Status timeline') }}</p>
                    <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Order progress') }}</h2>
                </div>

                <ol class="space-y-4">
                    @foreach ($this->timelineSteps as $step)
                        <li wire:key="order-step-{{ $step['value'] }}" wire:transition class="suki-reveal flex items-start gap-4" style="transition-delay: {{ $loop->index * 80 }}ms">
                            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border text-xs font-semibold transition-transform duration-300 {{ $this->stepMarkerClasses($step['state']) }}">
                                @if ($step['state'] === 'current-cancelled')
                                    <i class="fa-solid fa-xmark"></i>
                                @else
                                    {{ $loop->iteration }}
                                @endif
                            </span>
                            <div>
                                <p class="font-semibold text-neutral-900 dark:text-zinc-100">{{ __($step['label']) }}</p>
                                <p class="text-sm text-neutral-500 dark:text-zinc-400">
                                    @if ($step['state'] === 'complete')
                                        {{ __('Completed') }}
                                    @elseif ($step['state'] === 'current')
                                        {{ __('Current stage') }}
                                    @elseif ($step['state'] === 'current-cancelled')
                                        {{ __('This order was cancelled before fulfillment.') }}
                                    @else
                                        {{ __('Waiting ahead') }}
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ol>

                @if ($this->order->estimated_delivery_at)
                    <div wire:transition class="rounded-[1.5rem] border border-stone-200 bg-stone-50 p-4 text-sm dark:border-white/10 dark:bg-zinc-800">
                        <p class="font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Estimated delivery') }}</p>
                        <p class="mt-1 text-neutral-500 dark:text-zinc-400">{{ $this->order->estimated_delivery_at->format('M j, Y g:i A') }}</p>
                        @if ($this->order->delay_note)
                            <p class="mt-2 text-xs text-amber-700 dark:text-amber-300"><i class="fa-solid fa-clock-rotate-left mr-1"></i>{{ $this->order->delay_note }}</p>
                        @endif
                    </div>
                @endif
            </section>

            <section class="brand-panel space-y-5 p-6">
                <div>
                    <p class="brand-kicker !mb-0">{{ __('Payment') }}</p>
                    <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Payment details') }}</h2>
                </div>

                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-neutral-500 dark:text-zinc-400">{{ __('Method') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-zinc-100">{{ Str::headline($this->order->payment->method->value) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-neutral-500 dark:text-zinc-400">{{ __('Status') }}</span>
                        <x-payment-status-badge :status="$this->order->payment_status" />
                    </div>
                    @if ($this->maskedReference($this->order->payment->reference_number))
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-neutral-500 dark:text-zinc-400">{{ __('Reference') }}</span>
                            <span class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $this->maskedReference($this->order->payment->reference_number) }}</span>
                        </div>
                    @endif
                    @if ($this->order->payment->paid_at)
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-neutral-500 dark:text-zinc-400">{{ __('Paid at') }}</span>
                            <span class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $this->order->payment->paid_at->format('M j, Y g:i A') }}</span>
                        </div>
                    @endif
                </div>

                <a
                    href="{{ route('messages.conversation', ['conversationReference' => $this->order->vendor->user->id, 'order' => $this->order->id]) }}"
                    class="brand-button-secondary active:scale-[0.96] w-full"
                >
                    {{ __('Message vendor') }}
                </a>
                <p class="mt-2 text-center text-xs text-neutral-400 dark:text-zinc-500">
                    {{ __('View full message history in your inbox') }}
                </p>

                @if ($this->order->order_status === OrderStatus::Pending)
                    <button
                        type="button"
                        x-data
                        x-on:click="$flux.modal('cancel-order').show()"
                        wire:loading.attr="disabled"
                        class="w-full rounded-[1.25rem] border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 transition hover:border-rose-300 hover:bg-rose-100 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200 dark:hover:border-rose-500/30"
                    >
                        {{ __('Cancel order') }}
                    </button>

                    <flux:modal name="cancel-order" class="max-w-sm">
                        <div class="p-6 space-y-4">
                            <flux:heading size="lg">{{ __('Cancel order?') }}</flux:heading>
                            <flux:text>{{ __('This will stop the order before the vendor confirms it.') }}</flux:text>

                            <div class="flex justify-end gap-3 pt-2">
                                <flux:button variant="ghost" x-on:click="$flux.modal('cancel-order').close()">
                                    {{ __('Keep order') }}
                                </flux:button>

                                <flux:button variant="danger" wire:click="cancelOrder" x-on:click="$flux.modal('cancel-order').close()">
                                    {{ __('Cancel order') }}
                                </flux:button>
                            </div>
                        </div>
                    </flux:modal>
                @endif
            </section>
        </aside>
    </div>
</section>
