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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rider Dashboard')] class extends Component
{
    use HasRiderGuard;

    #[Computed]
    public function availableOrders(): Collection
    {
        return Order::query()
            ->where('order_status', OrderStatus::Ready)
            ->where('is_self_pickup', false)
            ->whereNull('rider_id')
            ->with(['vendor:id,user_id,store_name,vendor_address,lat,lng', 'customer:id,name,address,lat,lng'])
            ->withCount('orderItems')
            ->latest('updated_at')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function myActiveDeliveries(): Collection
    {
        return Order::query()
            ->where('rider_id', auth()->id())
            ->whereIn('order_status', [OrderStatus::PickedUp, OrderStatus::OutForDelivery])
            ->with(['vendor:id,user_id,store_name,vendor_address,lat,lng', 'customer:id,name,address,lat,lng'])
            ->withCount('orderItems')
            ->latest('updated_at')
            ->get();
    }

    #[Computed]
    public function completedToday(): int
    {
        return Order::query()
            ->where('rider_id', auth()->id())
            ->where('order_status', OrderStatus::Delivered)
            ->whereDate('updated_at', today())
            ->count();
    }

    #[Computed]
    public function totalCompleted(): int
    {
        return Order::query()
            ->where('rider_id', auth()->id())
            ->where('order_status', OrderStatus::Delivered)
            ->count();
    }

    #[Computed]
    public function isAvailable(): bool
    {
        return (bool) auth()->user()->riderProfile?->is_available;
    }

    #[Computed]
    public function activeOrderId(): ?int
    {
        return $this->myActiveDeliveries->first()?->getKey();
    }

    public function toggleAvailability(): void
    {
        $profile = auth()->user()->riderProfile;

        if ($profile !== null) {
            $profile->update(['is_available' => ! $profile->is_available]);
        }

        unset($this->isAvailable);

        Flux::toast(
            variant: 'success',
            text: $this->isAvailable ? __('You are now available for deliveries.') : __('You are now offline.'),
        );
    }

    public function claimOrder(int $orderId): void
    {
        $order = DB::transaction(function () use ($orderId): Order {
            $order = Order::query()
                ->where('order_status', OrderStatus::Ready)
                ->whereNull('rider_id')
                ->lockForUpdate()
                ->findOrFail($orderId);

            abort_if($order->is_self_pickup, 403);

            $order->forceFill([
                'rider_id' => auth()->id(),
                'order_status' => OrderStatus::PickedUp,
                'picked_up_at' => now(),
            ])->save();

            AuditLogger::log(AuditEvent::OrderPickedUp, "Rider claimed order #{$order->id}.", $order);

            return $order;
        });

        SendOrderNotificationJob::dispatch(
            orderId: $order->getKey(),
            userId: $order->customer_id,
            title: 'Rider assigned',
            message: "A rider has picked up your order #{$order->id} and is heading to you.",
            type: NotificationType::OrderUpdate,
            broadcastOrderStatus: true,
        );

        $this->clearDeliveryComputeds();

        Flux::toast(variant: 'success', text: __('Order claimed. Head to the vendor for pickup.'));
    }

    public function markOutForDelivery(int $orderId): void
    {
        $order = Order::query()
            ->where('rider_id', auth()->id())
            ->where('order_status', OrderStatus::PickedUp)
            ->findOrFail($orderId);

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

        $this->clearDeliveryComputeds();

        Flux::toast(variant: 'success', text: __('Marked as out for delivery.'));
    }

    public function markDelivered(int $orderId): void
    {
        $order = DB::transaction(function () use ($orderId): Order {
            $order = Order::query()
                ->where('rider_id', auth()->id())
                ->where('order_status', OrderStatus::OutForDelivery)
                ->with('payment')
                ->lockForUpdate()
                ->findOrFail($orderId);

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

        $this->clearDeliveryComputeds();

        Flux::toast(variant: 'success', text: __('Order marked as delivered!'));
    }

    public function distanceLabel(Order $order): string
    {
        if (! $order->vendor?->hasLocation() || ! $order->customer?->hasLocation()) {
            return __('Distance unavailable');
        }

        return '~'.number_format($this->haversineKm(
            (float) $order->vendor->lat,
            (float) $order->vendor->lng,
            (float) $order->customer->lat,
            (float) $order->customer->lng,
        ), 1).' km';
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);
        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLng / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function clearDeliveryComputeds(): void
    {
        unset($this->availableOrders, $this->myActiveDeliveries, $this->completedToday, $this->totalCompleted, $this->activeOrderId);
    }
}; ?>

<section
    x-data="{
        trackingInterval: null,
        activeOrderId: @js($this->activeOrderId),
        updateActiveOrderId(orderId) {
            this.activeOrderId = orderId;
            this.stopTracking();
            this.startTracking();
        },
        startTracking() {
            if (!this.activeOrderId || !navigator.geolocation) return;

            const sendLocation = () => {
                navigator.geolocation.getCurrentPosition((pos) => {
                    const payload = {
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                        order_id: this.activeOrderId,
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
    x-effect="updateActiveOrderId(@js($this->activeOrderId))"
    x-on:beforeunload.window="stopTracking()"
    class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8"
>
    <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <span class="brand-kicker">{{ __('Rider workspace') }}</span>
            <h1 class="brand-serif mt-3 text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Rider dashboard') }}</h1>
            <p class="mt-3 max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
                {{ __('Claim ready orders, update delivery progress, and keep customers informed with live location updates.') }}
            </p>
        </div>

        <button
            type="button"
            wire:click="toggleAvailability"
            @class([
                'inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-semibold transition-all duration-150 active:scale-[0.97]',
                'bg-emerald-600 text-white shadow-lg shadow-emerald-600/20' => $this->isAvailable,
                'border border-stone-200 bg-white text-neutral-600 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-300' => ! $this->isAvailable,
            ])
        >
            <span @class([
                'h-2.5 w-2.5 rounded-full',
                'bg-white' => $this->isAvailable,
                'bg-neutral-400' => ! $this->isAvailable,
            ])></span>
            {{ $this->isAvailable ? __('Available') : __('Offline') }}
        </button>
    </section>

    <section class="grid gap-4 md:grid-cols-3">
        @foreach ([
            ['label' => __('Active deliveries'), 'value' => $this->myActiveDeliveries->count(), 'icon' => 'fa-solid fa-route'],
            ['label' => __('Completed today'), 'value' => $this->completedToday, 'icon' => 'fa-solid fa-calendar-check'],
            ['label' => __('Total completed'), 'value' => $this->totalCompleted, 'icon' => 'fa-solid fa-circle-check'],
        ] as $stat)
            <article class="brand-panel-muted flex items-center gap-4 p-5">
                <span class="brand-soft-surface flex h-12 w-12 items-center justify-center rounded-2xl">
                    <i class="{{ $stat['icon'] }}"></i>
                </span>
                <div>
                    <p class="text-2xl font-bold tabular-nums text-neutral-900 dark:text-zinc-100">{{ number_format($stat['value']) }}</p>
                    <p class="text-sm text-neutral-500 dark:text-zinc-400">{{ $stat['label'] }}</p>
                </div>
            </article>
        @endforeach
    </section>

    <section class="grid gap-8 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
        <div class="space-y-5">
            <div>
                <p class="brand-kicker">{{ __('Available orders') }}</p>
                <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Ready for pickup') }}</h2>
            </div>

            @forelse ($this->availableOrders as $order)
                <article class="brand-panel p-5" wire:key="available-rider-order-{{ $order->id }}">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="brand-kicker !mb-0">{{ __('Order #:number', ['number' => str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]) }}</p>
                            <h3 class="mt-3 text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $order->vendor->store_name }}</h3>
                            <p class="mt-2 text-sm leading-6 text-neutral-500 dark:text-zinc-400">{{ $order->vendor->vendor_address ?: __('Pickup point not listed') }}</p>
                        </div>
                        <x-order-status-badge :status="$order->order_status" />
                    </div>

                    <div class="mt-5 grid gap-3 text-sm text-neutral-500 dark:text-zinc-400 sm:grid-cols-3">
                        <span>{{ trans_choice(':count item|:count items', $order->order_items_count, ['count' => $order->order_items_count]) }}</span>
                        <span>{{ $this->distanceLabel($order) }}</span>
                        <span>{{ $order->updated_at->diffForHumans() }}</span>
                    </div>

                    <button type="button" wire:click="claimOrder({{ $order->id }})" wire:loading.attr="disabled" wire:target="claimOrder({{ $order->id }})" class="brand-button-primary mt-5 w-full transition-all duration-150 active:scale-[0.96]">
                        {{ __('Claim delivery') }}
                    </button>
                </article>
            @empty
                <div class="brand-panel px-6 py-12 text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-neutral-400 dark:bg-zinc-800 dark:text-zinc-400">
                        <i class="fa-solid fa-box-open text-xl"></i>
                    </span>
                    <h3 class="brand-serif mt-5 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('No ready orders') }}</h3>
                    <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">{{ __('Ready orders will appear here when vendors finish preparing them.') }}</p>
                </div>
            @endforelse
        </div>

        <div class="space-y-5">
            <div>
                <p class="brand-kicker">{{ __('My active deliveries') }}</p>
                <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('On the road') }}</h2>
            </div>

            @forelse ($this->myActiveDeliveries as $order)
                <article class="brand-panel overflow-hidden p-5" wire:key="active-rider-order-{{ $order->id }}">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="brand-kicker !mb-0">{{ __('Order #:number', ['number' => str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]) }}</p>
                            <h3 class="mt-3 text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $order->customer->name }}</h3>
                            <p class="mt-2 text-sm leading-6 text-neutral-500 dark:text-zinc-400">{{ $order->customer->address ?: $order->delivery_address }}</p>
                        </div>
                        <x-order-status-badge :status="$order->order_status" />
                    </div>

                    <div class="mt-5 overflow-hidden rounded-[1.25rem] border border-stone-200 dark:border-white/10">
                        <x-order-location-map
                            :customer="$order->customer"
                            :vendor-profile="$order->vendor"
                            height="220px"
                            customer-label="Customer"
                            vendor-label="Pickup"
                            vendor-marker="vendorGreen"
                            :show-distance="true"
                            heading="Delivery route"
                            :order="$order"
                            :enable-live-rider="true"
                            :route-mode="$order->order_status === OrderStatus::PickedUp ? 'pickup' : 'dropoff'"
                            rider-label="You"
                        />
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        @if ($order->order_status === OrderStatus::PickedUp)
                            <button type="button" wire:click="markOutForDelivery({{ $order->id }})" wire:loading.attr="disabled" wire:target="markOutForDelivery({{ $order->id }})" class="brand-button-primary w-full transition-all duration-150 active:scale-[0.96]">
                                {{ __('Mark out for delivery') }}
                            </button>
                        @endif

                        @if ($order->order_status === OrderStatus::OutForDelivery)
                            <button type="button" wire:click="markDelivered({{ $order->id }})" wire:loading.attr="disabled" wire:target="markDelivered({{ $order->id }})" class="brand-button-primary w-full transition-all duration-150 active:scale-[0.96]">
                                {{ __('Mark delivered') }}
                            </button>
                        @endif

                        <a href="{{ route('rider.deliveries.show', ['orderReference' => $order->id]) }}" wire:navigate class="brand-button-secondary w-full transition-all duration-150 active:scale-[0.96]">
                            {{ __('View details') }}
                        </a>

                        <a href="{{ route('messages.conversation', ['conversationReference' => $order->customer_id, 'order' => $order->id]) }}" wire:navigate class="brand-button-secondary w-full transition-all duration-150 active:scale-[0.96]">
                            {{ __('Message customer') }}
                        </a>
                    </div>
                </article>
            @empty
                <div class="brand-panel px-6 py-12 text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-neutral-400 dark:bg-zinc-800 dark:text-zinc-400">
                        <i class="fa-solid fa-road text-xl"></i>
                    </span>
                    <h3 class="brand-serif mt-5 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('No active deliveries') }}</h3>
                    <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">{{ __('Claim a ready order to start tracking your next delivery.') }}</p>
                </div>
            @endforelse
        </div>
    </section>
</section>
