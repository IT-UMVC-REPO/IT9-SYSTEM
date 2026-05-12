<?php

use App\Concerns\HasRiderGuard;
use App\Enums\AuditEvent;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Order;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Delivery Detail')] class extends Component
{
    use HasRiderGuard;

    public int $orderId;

    public function mount(string $orderReference): void
    {
        $order = Order::query()
            ->select('id', 'rider_id')
            ->where('rider_id', auth()->id())
            ->findOrFail((int) $orderReference);

        $this->orderId = $order->getKey();
    }

    public function markOutForDelivery(): void
    {
        $order = Order::query()
            ->where('rider_id', auth()->id())
            ->where('order_status', OrderStatus::PickedUp)
            ->findOrFail($this->orderId);

        $order->forceFill([
            'order_status' => OrderStatus::OutForDelivery,
            'out_for_delivery_at' => now(),
        ])->save();

        AuditLogger::log(AuditEvent::OrderOutForDelivery, "Order #{$order->id} is out for delivery.", $order);

        SendOrderNotificationJob::dispatch(
            orderId: $order->getKey(),
            userId: $order->customer_id,
            title: 'Out for delivery',
            message: "Your order #{$order->id} is out for delivery and on its way!",
            type: NotificationType::OrderUpdate,
            broadcastOrderStatus: true,
        );

        unset($this->order);

        Flux::toast(variant: 'success', text: __('Marked as out for delivery.'));
    }

    public function markDelivered(): void
    {
        $order = DB::transaction(function (): Order {
            $order = Order::query()
                ->where('rider_id', auth()->id())
                ->where('order_status', OrderStatus::OutForDelivery)
                ->with('payment')
                ->lockForUpdate()
                ->findOrFail($this->orderId);

            $order->forceFill([
                'order_status' => OrderStatus::Delivered,
                'payment_status' => PaymentStatus::Paid,
            ])->save();

            $order->payment?->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
            ]);

            AuditLogger::log(AuditEvent::OrderDelivered, "Order #{$order->id} delivered by rider.", $order);

            return $order;
        });

        SendOrderNotificationJob::dispatch(
            orderId: $order->getKey(),
            userId: $order->customer_id,
            title: 'Order delivered',
            message: "Your order #{$order->id} has been delivered. Enjoy!",
            type: NotificationType::OrderUpdate,
            broadcastOrderStatus: true,
        );

        unset($this->order);

        Flux::toast(variant: 'success', text: __('Order marked as delivered!'));

        $this->redirectRoute('rider.history', navigate: true);
    }

    #[Computed]
    public function order(): Order
    {
        return Order::query()
            ->where('rider_id', auth()->id())
            ->with([
                'customer:id,name,address,lat,lng,phone',
                'vendor:id,user_id,store_name,vendor_address,lat,lng',
                'orderItems.product.category',
                'payment',
            ])
            ->findOrFail($this->orderId);
    }
}; ?>

<section
    x-data="{
        trackingInterval: null,
        startTracking() {
            if (!@js(in_array($this->order->order_status, [OrderStatus::PickedUp, OrderStatus::OutForDelivery], true)) || !navigator.geolocation) return;

            const sendLocation = () => {
                navigator.geolocation.getCurrentPosition((pos) => {
                    const payload = {
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                        order_id: @js($this->order->id),
                    };

                    window.dispatchEvent(new CustomEvent('rider-location-updated', { detail: payload }));

                    fetch(@js(route('rider.location.update', absolute: false)), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            ...(window.Echo?.socketId?.() ? { 'X-Socket-ID': window.Echo.socketId() } : {}),
                        },
                        body: JSON.stringify(payload),
                    });
                });
            };

            sendLocation();
            this.trackingInterval = setInterval(sendLocation, 10000);
        },
        stopTracking() {
            if (this.trackingInterval) clearInterval(this.trackingInterval);
            this.trackingInterval = null;
        },
    }"
    x-init="startTracking()"
    x-on:beforeunload.window="stopTracking()"
    class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8"
>
    <div class="flex flex-col gap-4">
        <a href="{{ route('rider.deliveries') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-neutral-500 transition hover:text-neutral-900 dark:text-zinc-400 dark:hover:text-zinc-100">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            {{ __('Back to deliveries') }}
        </a>
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <span class="brand-kicker">{{ __('Delivery detail') }}</span>
                <h1 class="brand-serif mt-3 text-4xl font-bold text-neutral-900 dark:text-zinc-100">
                    {{ __('Order #:number', ['number' => str_pad((string) $this->order->id, 6, '0', STR_PAD_LEFT)]) }}
                </h1>
            </div>
            <x-order-status-badge :status="$this->order->order_status" />
        </div>
    </div>

    <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            <section class="grid gap-6 lg:grid-cols-2">
                <div class="brand-panel-muted p-6">
                    <p class="brand-kicker !mb-0">{{ __('Pickup') }}</p>
                    <h2 class="mt-3 text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $this->order->vendor->store_name }}</h2>
                    <p class="mt-2 text-sm leading-7 text-neutral-500 dark:text-zinc-400">{{ $this->order->vendor->vendor_address ?: __('Pickup point not listed') }}</p>
                </div>

                <div class="brand-panel-muted p-6">
                    <p class="brand-kicker !mb-0">{{ __('Drop-off') }}</p>
                    <h2 class="mt-3 text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $this->order->customer->name }}</h2>
                    <p class="mt-2 text-sm leading-7 text-neutral-500 dark:text-zinc-400">{{ $this->order->customer->address ?: $this->order->delivery_address }}</p>
                </div>
            </section>

            <x-order-location-map
                :customer="$this->order->customer"
                :vendor-profile="$this->order->vendor"
                height="320px"
                customer-label="Customer"
                vendor-label="Pickup"
                vendor-marker="vendorGreen"
                :show-distance="true"
                heading="Delivery route"
                :order="$this->order"
                :enable-live-rider="true"
                :route-mode="$this->order->order_status === OrderStatus::PickedUp ? 'pickup' : 'dropoff'"
                rider-label="You"
            />

            <section class="brand-panel overflow-hidden">
                <div class="border-b border-stone-200 px-6 py-5 dark:border-white/10 sm:px-8">
                    <h2 class="brand-serif text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Line items') }}</h2>
                </div>

                <div class="divide-y divide-stone-200 dark:divide-white/10">
                    @foreach ($this->order->orderItems as $item)
                        <div class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-8" wire:key="rider-delivery-item-{{ $item->id }}">
                            <div class="min-w-0">
                                <p class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $item->product->name }}</p>
                                <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">{{ $item->product->category->name }}</p>
                            </div>
                            <span class="text-sm text-neutral-500 dark:text-zinc-400">{{ $item->quantity }} {{ $item->unit->abbreviation() }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="space-y-6 xl:sticky xl:top-24 xl:self-start">
            <section class="brand-panel p-6">
                <p class="brand-kicker !mb-0">{{ __('Actions') }}</p>
                <div class="mt-5 grid gap-3">
                    @if ($this->order->order_status === OrderStatus::PickedUp)
                        <button type="button" wire:click="markOutForDelivery" wire:loading.attr="disabled" wire:target="markOutForDelivery" class="brand-button-primary w-full transition-all duration-150 active:scale-[0.96]">
                            {{ __('Mark out for delivery') }}
                        </button>
                    @endif

                    @if ($this->order->order_status === OrderStatus::OutForDelivery)
                        <button type="button" wire:click="markDelivered" wire:loading.attr="disabled" wire:target="markDelivered" class="brand-button-primary w-full transition-all duration-150 active:scale-[0.96]">
                            {{ __('Mark delivered') }}
                        </button>
                    @endif

                    <a href="{{ route('messages.conversation', ['conversationReference' => $this->order->customer_id, 'order' => $this->order->id]) }}" wire:navigate class="brand-button-secondary w-full transition-all duration-150 active:scale-[0.96]">
                        {{ __('Message customer') }}
                    </a>
                </div>
            </section>

            <section class="brand-panel-muted p-6">
                <p class="brand-kicker !mb-0">{{ __('Payment') }}</p>
                <div class="mt-5 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-neutral-500 dark:text-zinc-400">{{ __('Total') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $this->order->formattedTotal() }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-neutral-500 dark:text-zinc-400">{{ __('Status') }}</span>
                        <x-payment-status-badge :status="$this->order->payment_status" />
                    </div>
                </div>
            </section>
        </aside>
    </div>
</section>
