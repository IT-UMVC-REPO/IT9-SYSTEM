<?php

use App\Enums\AuditEvent;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\VendorStatus;
use App\Events\OrderStatusUpdated;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Vendor Order Detail')] class extends Component
{
    public int $orderId;

    public ?string $estimated_delivery_at = null;

    public string $delay_note = '';

    public function mount(string $orderReference): void
    {
        if (! $this->hasApprovedVendorProfile()) {
            $this->redirectRoute('customer.dashboard', navigate: true);

            return;
        }

        $order = Order::query()
            ->where('vendor_id', $this->vendorId())
            ->findOrFail((int) $orderReference);

        $this->orderId = $order->getKey();
        $this->hydrateDeliveryEstimateForm($order);
    }

    public function advanceStatus(): void
    {
        $currentOrder = $this->order;

        $nextStatus = match ($currentOrder->order_status) {
            OrderStatus::Pending => OrderStatus::Confirmed,
            OrderStatus::Confirmed => OrderStatus::Preparing,
            OrderStatus::Preparing => OrderStatus::Ready,
            OrderStatus::Ready => $currentOrder->is_self_pickup ? OrderStatus::Delivered : null,
            default => null,
        };

        if ($nextStatus === null) {
            Flux::toast(variant: 'warning', text: __('This order has already reached its final state.'));

            return;
        }

        $updatedOrder = DB::transaction(function () use ($nextStatus): Order {
            $order = Order::query()
                ->with('payment')
                ->lockForUpdate()
                ->where('vendor_id', $this->vendorId())
                ->findOrFail($this->orderId);

            $updates = ['order_status' => $nextStatus];

            if ($nextStatus === OrderStatus::Delivered && $order->is_self_pickup) {
                $updates['payment_status'] = PaymentStatus::Paid;
            }

            $order->forceFill($updates)->save();

            if ($nextStatus === OrderStatus::Delivered && $order->is_self_pickup) {
                $order->payment?->update([
                    'status' => PaymentStatus::Paid,
                    'paid_at' => now(),
                ]);
            }

            AuditLogger::log(
                match ($order->order_status) {
                    OrderStatus::Confirmed => AuditEvent::OrderConfirmed,
                    OrderStatus::Preparing => AuditEvent::OrderPreparing,
                    OrderStatus::Ready => AuditEvent::OrderReady,
                    OrderStatus::Delivered => AuditEvent::OrderDelivered,
                    default => AuditEvent::OrderCancelled,
                },
                "Order #{$order->id} status changed to '{$order->order_status->value}' by vendor.",
                $order,
            );

            return $order;
        });

        SendOrderNotificationJob::dispatch(
            orderId: $updatedOrder->getKey(),
            userId: $updatedOrder->customer_id,
            title: $updatedOrder->is_self_pickup && $nextStatus === OrderStatus::Delivered
                ? 'Order collected'
                : 'Order '.Str::headline($nextStatus->value),
            message: $updatedOrder->is_self_pickup && $nextStatus === OrderStatus::Delivered
                ? 'Your order #'.$this->orderId.' has been collected. Thank you!'
                : 'Your order #'.$this->orderId.' is now '.Str::lower(Str::headline($nextStatus->value)).'.',
            type: NotificationType::OrderUpdate,
            broadcastOrderStatus: true,
        );

        unset($this->order);

        Flux::toast(variant: 'success', text: __('Order status updated.'));
    }

    public function cancelOrder(): void
    {
        $currentOrder = $this->order;

        if ($currentOrder->order_status !== OrderStatus::Pending) {
            Flux::toast(variant: 'warning', text: __('This order cannot be cancelled at this stage.'));

            return;
        }

        DB::transaction(function (): void {
            $order = Order::query()
                ->with('payment')
                ->where('vendor_id', $this->vendorId())
                ->findOrFail($this->orderId)
                ->forceFill(['order_status' => OrderStatus::Cancelled]);

            $order->save();

            $order->orderItems()->with('product')->get()->each(function (OrderItem $item): void {
                if ($item->product !== null) {
                    $item->product->increment('stock_quantity', $item->quantity);
                }
            });

            AuditLogger::log(AuditEvent::OrderCancelled, "Order #{$order->id} cancelled.", $order);
        });

        SendOrderNotificationJob::dispatch(
            orderId: $this->orderId,
            userId: $currentOrder->customer_id,
            title: 'Order cancelled',
            message: 'Your order #'.$this->orderId.' was cancelled by the vendor.',
            type: NotificationType::OrderUpdate,
            broadcastOrderStatus: true,
        );

        unset($this->order);

        Flux::toast(variant: 'warning', text: __('Order cancelled.'));
    }

    public function saveDeliveryEstimate(): void
    {
        $validated = $this->validate([
            'estimated_delivery_at' => ['nullable', 'date'],
            'delay_note' => ['nullable', 'string', 'max:200'],
        ]);

        $order = DB::transaction(function () use ($validated): Order {
            $order = Order::query()
                ->where('vendor_id', $this->vendorId())
                ->lockForUpdate()
                ->findOrFail($this->orderId);

            abort_unless(in_array($order->order_status, [OrderStatus::Confirmed, OrderStatus::Preparing], true), 403);

            $order->forceFill([
                'estimated_delivery_at' => blank($validated['estimated_delivery_at'] ?? null)
                    ? null
                    : Carbon::parse($validated['estimated_delivery_at']),
                'delay_note' => blank($validated['delay_note'] ?? null) ? null : $validated['delay_note'],
            ])->save();

            return $order;
        });

        event(new OrderStatusUpdated($order));

        unset($this->order);
        $this->hydrateDeliveryEstimateForm($order);

        Flux::toast(variant: 'success', text: __('Delivery estimate saved.'));
    }

    #[Computed]
    public function order(): Order
    {
        return Order::query()
            ->where('vendor_id', $this->vendorId())
            ->with([
                'customer:id,name,address,lat,lng',
                'vendor:id,user_id,store_name,vendor_address,lat,lng',
                'rider:id,name,phone',
                'rider.riderProfile:id,user_id,vehicle_type,plate_number,contact_number,status,is_available,current_lat,current_lng',
                'payment',
                'orderItems.product.category',
            ])
            ->findOrFail($this->orderId);
    }

    #[Computed]
    public function nextStatus(): ?OrderStatus
    {
        return match ($this->order->order_status) {
            OrderStatus::Pending => OrderStatus::Confirmed,
            OrderStatus::Confirmed => OrderStatus::Preparing,
            OrderStatus::Preparing => OrderStatus::Ready,
            OrderStatus::Ready => $this->order->is_self_pickup ? OrderStatus::Delivered : null,
            default => null,
        };
    }

    private function vendorId(): int
    {
        return auth()->user()->vendorProfile->getKey();
    }

    private function hasApprovedVendorProfile(): bool
    {
        return auth()->user()->vendorProfile?->status === VendorStatus::Approved;
    }

    private function hydrateDeliveryEstimateForm(Order $order): void
    {
        $this->estimated_delivery_at = $order->estimated_delivery_at?->format('Y-m-d\TH:i');
        $this->delay_note = $order->delay_note ?? '';
    }
};
?>

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
        <a href="{{ route('vendor.orders') }}" wire:navigate class="mb-2 inline-flex items-center gap-2 text-sm font-semibold text-neutral-500 brand-hover-text dark:text-zinc-400">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            {{ __('Back to order queue') }}
        </a>
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">
                    {{ __('Order #:number', ['number' => str_pad((string) $this->order->id, 6, '0', STR_PAD_LEFT)]) }}
                </h1>
                <p class="mt-3 max-w-2xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
                    {{ __('Review line items, move the order through fulfilment, and keep the customer informed as the stall prepares the request.') }}
                </p>
            </div>

            <span class="brand-badge">{{ Str::headline($this->order->order_status->value) }}</span>
        </div>
    </div>

    <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            <section class="brand-panel p-6 sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <span class="brand-kicker !mb-0">{{ __('Customer') }}</span>
                        <h2 class="mt-3 text-2xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $this->order->customer->name }}</h2>
                        <p class="mt-2 text-sm text-neutral-500 dark:text-zinc-400">{{ $this->order->created_at->format('M j, Y g:i A') }}</p>
                    </div>

                    <div class="rounded-[1.5rem] border border-stone-200 bg-stone-50 px-4 py-3 dark:border-white/10 dark:bg-zinc-800">
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
                        <div class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-8" wire:key="vendor-order-item-{{ $item->id }}">
                            <div class="min-w-0">
                                <p class="text-lg font-semibold text-neutral-900 dark:text-zinc-100">{{ $item->product->name }}</p>
                                <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">{{ $item->product->category->name }}</p>
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
                    <p class="brand-kicker !mb-0">{{ __('Customer notes') }}</p>
                    <p class="mt-3 text-sm leading-7 text-neutral-700 dark:text-zinc-300">
                        {{ $this->order->notes ?: __('No notes were added to this order.') }}
                    </p>
                </div>
            </section>

            <x-order-location-map
                :customer="$this->order->customer"
                :vendor-profile="$this->order->vendor"
                height="250px"
                customer-label="Customer"
                vendor-label="Your Stall"
                vendor-marker="vendorGreen"
                :show-distance="true"
            />
        </div>

        <aside class="space-y-6 xl:sticky xl:top-24 xl:self-start">
            <section class="brand-panel p-6">
                <span class="brand-kicker">{{ __('Status timeline') }}</span>
                <div class="mt-5 space-y-3">
                    @php
                        $statusSteps = [OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::PickedUp, OrderStatus::OutForDelivery, OrderStatus::Delivered];
                        $currentStatusIndex = array_search($this->order->order_status, $statusSteps, true);
                    @endphp

                    @foreach ($statusSteps as $status)
                        @php($isCompleted = $currentStatusIndex !== false && $loop->index < $currentStatusIndex)
                        <div class="suki-reveal flex items-center gap-3" wire:key="vendor-order-status-step-{{ $status->value }}" wire:transition style="transition-delay: {{ $loop->index * 80 }}ms">
                            <span
                                @class([
                                    'inline-flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold',
                                    'bg-[var(--brand-600)] text-white' => $status === $this->order->order_status,
                                    'bg-[var(--brand-100)] text-[var(--brand-700)] dark:bg-[var(--brand-900)] dark:text-[var(--brand-300)]' => $isCompleted,
                                    'bg-stone-200 text-stone-600 dark:bg-zinc-700 dark:text-zinc-300' => $status !== $this->order->order_status && ! $isCompleted,
                                ])
                            >
                                {{ $loop->iteration }}
                            </span>
                            <span class="font-medium text-neutral-900 dark:text-zinc-100">{{ Str::headline($status->value) }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="brand-panel p-6">
                <span class="brand-kicker">{{ __('Payment info') }}</span>
                <div class="mt-5 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-neutral-500 dark:text-zinc-400">{{ __('Method') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-zinc-100">{{ Str::headline($this->order->payment_method->value) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-neutral-500 dark:text-zinc-400">{{ __('Payment status') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-zinc-100">{{ Str::headline($this->order->payment_status->value) }}</span>
                    </div>
                </div>

                @if ($this->order->is_self_pickup && $this->order->order_status === OrderStatus::Ready)
                    <div class="mt-6 rounded-[1.5rem] border border-emerald-200 bg-emerald-50 p-4 text-sm dark:border-emerald-500/20 dark:bg-emerald-500/10">
                        <p class="font-semibold text-emerald-800 dark:text-emerald-200">{{ __('Awaiting customer pickup') }}</p>
                        <p class="mt-1 text-emerald-700 dark:text-emerald-300">{{ __('The customer will come to your stall. Mark as delivered when they collect the order.') }}</p>
                    </div>
                @elseif ($this->order->rider_id !== null)
                    <section class="mt-6 brand-panel-muted p-5">
                        <p class="brand-kicker !mb-0">{{ __('Rider assigned') }}</p>
                        <div class="mt-4 space-y-2 text-sm">
                            <p class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $this->order->rider?->name ?? __('Rider') }}</p>
                            <p class="text-neutral-500 dark:text-zinc-400">{{ $this->order->rider?->phone ?? $this->order->rider?->riderProfile?->contact_number ?? __('Contact via app') }}</p>
                            <p class="text-neutral-500 dark:text-zinc-400">
                                {{ Str::headline($this->order->rider?->riderProfile?->vehicle_type ?? __('Vehicle')) }}
                                @if ($this->order->rider?->riderProfile?->plate_number)
                                    - {{ $this->order->rider->riderProfile->plate_number }}
                                @endif
                            </p>
                            <x-order-status-badge :status="$this->order->order_status" />
                        </div>
                    </section>
                @elseif ($this->order->order_status === OrderStatus::Ready)
                    <div class="mt-6 rounded-[1.5rem] border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-500/20 dark:bg-amber-500/10">
                        <p class="font-semibold text-amber-800 dark:text-amber-200">{{ __('Waiting for rider') }}</p>
                        <p class="mt-1 text-amber-700 dark:text-amber-300">{{ __('A rider will claim this delivery shortly.') }}</p>
                    </div>
                @endif

                @if (in_array($this->order->order_status, [OrderStatus::Confirmed, OrderStatus::Preparing], true))
                    <div wire:transition class="mt-6 space-y-4 rounded-[1.5rem] border border-stone-200 bg-stone-50 p-4 dark:border-white/10 dark:bg-zinc-800">
                        <p class="font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Set estimated delivery') }}</p>

                        <flux:input type="datetime-local" wire:model="estimated_delivery_at" :label="__('Estimated delivery')" />
                        <flux:input wire:model="delay_note" :label="__('Delay note (optional)')" :placeholder="__('e.g. Delayed due to weather')" />

                        <button type="button" wire:click="saveDeliveryEstimate" wire:loading.attr="disabled" wire:target="saveDeliveryEstimate" class="brand-button-secondary w-full transition-all duration-200 hover:shadow-lg active:scale-[0.96]">
                            {{ __('Save estimate') }}
                        </button>
                    </div>
                @endif

                <div class="mt-6 grid gap-3">
                    @if ($this->nextStatus !== null)
                        <button
                            type="button"
                            wire:click="advanceStatus"
                            wire:transition
                            wire:loading.attr="disabled"
                            wire:target="advanceStatus"
                            class="brand-button-primary w-full transition-all duration-200 hover:shadow-lg active:scale-[0.96]"
                        >
                            {{ $this->order->is_self_pickup && $this->order->order_status === OrderStatus::Ready
                                ? __('Mark as collected by customer')
                                : __('Advance to :status', ['status' => Str::headline($this->nextStatus->value)]) }}
                        </button>
                    @endif

                    @if ($this->order->order_status === OrderStatus::Pending)
                        <flux:button
                            variant="outline"
                            type="button"
                            x-data
                            x-on:click="$flux.modal('cancel-vendor-order').show()"
                            class="w-full justify-center border-zinc-600 transition-all duration-150 active:scale-95"
                        >
                            {{ __('Cancel order') }}
                        </flux:button>
                    @endif

                    <a
                        href="{{ route('shop.customers.show', $this->order->customer_id) }}"
                        wire:navigate
                        class="brand-button-secondary w-full transition-all duration-150 active:scale-[0.96]"
                    >
                        {{ __('View customer profile') }}
                    </a>

                    <a
                        href="{{ route('messages.conversation', ['conversationReference' => $this->order->customer_id, 'order' => $this->order->id]) }}"
                        wire:navigate
                        class="brand-button-secondary w-full transition-all duration-150 active:scale-[0.96]"
                    >
                        {{ __('Message customer') }}
                    </a>

                    <flux:modal.trigger name="report-user">
                        <flux:button
                            variant="ghost"
                            type="button"
                            class="w-full justify-center text-rose-600 transition-all duration-150 hover:bg-rose-50 hover:text-rose-700 active:scale-95 dark:text-rose-300 dark:hover:bg-rose-500/10 dark:hover:text-rose-200"
                        >
                            <i class="fa-solid fa-flag text-xs"></i>
                            {{ __('Report this customer') }}
                        </flux:button>
                    </flux:modal.trigger>

                    <livewire:report.report-modal
                        :reported-user-id="$this->order->customer_id"
                        reporter-role="vendor"
                        :order-id="$this->orderId"
                    />
                </div>
            </section>
        </aside>
    </div>

    <flux:modal name="cancel-vendor-order" class="max-w-sm">
        <div class="p-6 space-y-4">
            <flux:heading size="lg">{{ __('Cancel order?') }}</flux:heading>
            <flux:text>{{ __('This order will be cancelled and the customer will be notified.') }}</flux:text>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" x-on:click="$flux.modal('cancel-vendor-order').close()">
                    {{ __('Keep order') }}
                </flux:button>

                <flux:button variant="danger" wire:click="cancelOrder" class="transition-all duration-150 active:scale-95">
                    {{ __('Cancel order') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
