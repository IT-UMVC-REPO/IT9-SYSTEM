<?php

use App\Concerns\HasRiderGuard;
use App\Enums\AuditEvent;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\RecordRiderEarningJob;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Order;
use App\Models\RiderEarning;
use App\Models\RiderProfile;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\RiderDispatchService;
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
    public function profile(): RiderProfile
    {
        return $this->approvedRiderProfile();
    }

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
            ->limit(8)
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
    public function todayEarnings(): float
    {
        return (float) RiderEarning::query()
            ->forRider((int) auth()->id())
            ->whereBetween('earned_at', [now()->startOfDay(), now()->endOfDay()])
            ->sum('amount');
    }

    #[Computed]
    public function sevenDayEarnings(): array
    {
        $days = collect(range(6, 0))->map(fn (int $daysAgo) => now()->subDays($daysAgo));

        return [
            'labels' => $days->map(fn ($day): string => $day->format('M j'))->all(),
            'series' => $days->map(fn ($day): float => (float) RiderEarning::query()
                ->forRider((int) auth()->id())
                ->whereBetween('earned_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                ->sum('amount'))->all(),
        ];
    }

    #[Computed]
    public function isAvailable(): bool
    {
        return (bool) $this->profile->is_available;
    }

    #[Computed]
    public function activeOrderId(): ?int
    {
        return $this->myActiveDeliveries->first()?->getKey();
    }

    #[Computed]
    public function canClaimMoreDeliveries(): bool
    {
        return $this->myActiveDeliveries->count() < (int) config('rider.max_concurrent_deliveries', 3);
    }

    public function toggleAvailability(): void
    {
        $profile = $this->profile;
        $profile->update([
            'is_available' => ! $profile->is_available,
            'last_seen_at' => now(),
        ]);

        unset($this->profile, $this->isAvailable);

        Flux::toast(
            variant: 'success',
            text: $this->isAvailable ? __('You are now available for deliveries.') : __('You are now offline.'),
        );
    }

    public function toggleSession(): void
    {
        $profile = $this->profile;
        $isStarting = $profile->session_started_at === null;

        $profile->forceFill([
            'session_started_at' => $isStarting ? now() : null,
            'last_seen_at' => now(),
        ])->save();

        AuditLogger::log(
            $isStarting ? AuditEvent::RiderSessionStarted : AuditEvent::RiderSessionEnded,
            $isStarting ? 'Rider session started.' : 'Rider session ended.',
            $profile,
            auth()->id(),
        );

        unset($this->profile);

        Flux::toast(
            variant: 'success',
            text: $isStarting ? __('Session started.') : __('Session ended.'),
        );
    }

    public function claimDelivery(int $orderId): void
    {
        $rider = auth()->user();

        abort_unless($rider instanceof User, 403);

        app(RiderDispatchService::class)->claimReadyOrder($orderId, $rider);

        $this->clearDeliveryComputeds();

        Flux::toast(variant: 'success', text: __('Delivery claimed. It is now in your active deliveries.'));
    }

    public function markOutForDelivery(int $orderId): void
    {
        $order = DB::transaction(function () use ($orderId): Order {
            $order = Order::query()
                ->where('rider_id', auth()->id())
                ->where('order_status', OrderStatus::PickedUp)
                ->lockForUpdate()
                ->findOrFail($orderId);

            $order->forceFill([
                'order_status' => OrderStatus::OutForDelivery,
                'out_for_delivery_at' => now(),
            ])->save();

            AuditLogger::log(AuditEvent::OrderOutForDelivery, "Order #{$order->id} is out for delivery.", $order);

            return $order;
        });

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

        RecordRiderEarningJob::dispatch($order->getKey());

        $this->clearDeliveryComputeds();

        Flux::toast(variant: 'success', text: __('Order marked as delivered!'));
    }

    public function distanceLabel(Order $order): string
    {
        $rider = auth()->user();

        if ($order->vendor?->hasLocation() && $rider?->hasLocation()) {
            return '~'.number_format($this->haversineKm(
                (float) $order->vendor->lat,
                (float) $order->vendor->lng,
                (float) $rider->lat,
                (float) $rider->lng,
            ), 1).' km from you';
        }

        if (! $order->vendor?->hasLocation() || ! $order->customer?->hasLocation()) {
            return __('Distance unavailable');
        }

        return '~'.number_format($this->haversineKm(
            (float) $order->vendor->lat,
            (float) $order->vendor->lng,
            (float) $order->customer->lat,
            (float) $order->customer->lng,
        ), 1).' km route';
    }

    public function peso(float|int $value): string
    {
        return sprintf("\u{20B1}%s", number_format((float) $value, 2));
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
        unset($this->availableOrders, $this->myActiveDeliveries, $this->completedToday, $this->todayEarnings, $this->sevenDayEarnings, $this->activeOrderId, $this->canClaimMoreDeliveries);
    }
}; ?>

@php($profile = $this->profile)

<section
    x-data="{
        trackingInterval: null,
        activeOrderId: @js($this->activeOrderId),
        userId: @js(auth()->id()),
        offer: null,
        countdown: 0,
        countdownTimer: null,
        offerFlash: null,
        earnings: {
            today: @js($this->todayEarnings),
            this_week: 0,
            this_month: 0,
        },
        earningsEndpoint: @js(route('rider.earnings.summary', absolute: false)),
        offerAcceptPath: @js(route('rider.offers.accept', ['offer' => '__OFFER__'], false)),
        offerDeclinePath: @js(route('rider.offers.decline', ['offer' => '__OFFER__'], false)),
        expiredText: @js(__('Offer expired')),
        init() {
            this.startTracking();
            this.subscribeToOffers();
            this.fetchEarnings();
        },
        csrf() {
            return document.querySelector('meta[name=csrf-token]')?.content ?? '';
        },
        headers() {
            return {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.csrf(),
                ...(window.Echo?.socketId?.() ? { 'X-Socket-ID': window.Echo.socketId() } : {}),
            };
        },
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
                        headers: this.headers(),
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
        subscribeToOffers() {
            if (!window.Echo || !this.userId) return;

            window.Echo.private(`calls.${this.userId}`)
                .listen('.RiderOfferCreated', (payload) => this.receiveOffer(payload))
                .listen('.RiderOfferExpired', (payload) => {
                    if (this.offer && Number(this.offer.offer_id) === Number(payload.offer_id)) {
                        this.expireOffer();
                    }
                });
        },
        receiveOffer(payload) {
            this.offer = payload;
            this.offerFlash = null;
            this.startCountdown(payload.expires_at);
        },
        startCountdown(expiresAt) {
            if (this.countdownTimer) clearInterval(this.countdownTimer);

            const tick = () => {
                const fallback = @js((int) config('rider.offer_timeout_seconds', 45));
                this.countdown = expiresAt
                    ? Math.max(0, Math.ceil((new Date(expiresAt).getTime() - Date.now()) / 1000))
                    : fallback;

                if (this.countdown <= 0) {
                    this.expireOffer();
                }
            };

            tick();
            this.countdownTimer = setInterval(tick, 1000);
        },
        expireOffer() {
            if (this.countdownTimer) clearInterval(this.countdownTimer);
            this.countdownTimer = null;
            this.offer = null;
            this.countdown = 0;
            this.offerFlash = this.expiredText;
            setTimeout(() => this.offerFlash = null, 3500);
        },
        offerActionUrl(path) {
            return path.replace('__OFFER__', this.offer?.offer_id ?? '');
        },
        async acceptOffer() {
            if (!this.offer) return;

            const response = await fetch(this.offerActionUrl(this.offerAcceptPath), {
                method: 'POST',
                headers: this.headers(),
                body: JSON.stringify({}),
            });

            if (response.ok) {
                const data = await response.json();
                if (data.redirect_url) window.location.href = data.redirect_url;
            }
        },
        async declineOffer() {
            if (!this.offer) return;

            const response = await fetch(this.offerActionUrl(this.offerDeclinePath), {
                method: 'POST',
                headers: this.headers(),
                body: JSON.stringify({}),
            });

            if (response.ok) {
                this.offer = null;
                if (this.countdownTimer) clearInterval(this.countdownTimer);
            }
        },
        async fetchEarnings() {
            const response = await fetch(this.earningsEndpoint, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf(),
                },
            });

            if (!response.ok) return;

            const data = await response.json();
            this.earnings.today = data.today ?? 0;
            this.earnings.this_week = data.this_week ?? 0;
            this.earnings.this_month = data.this_month ?? 0;
        },
        peso(value) {
            return '\u20b1' + Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    }"
    x-init="init()"
    x-effect="updateActiveOrderId(@js($this->activeOrderId))"
    x-on:beforeunload.window="stopTracking()"
    class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8"
>
    <section class="brand-panel p-5 sm:p-6">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <x-user-avatar :user="auth()->user()" size="xl" />
                <div>
                    <span class="brand-kicker">{{ __('Rider workspace') }}</span>
                    <h1 class="brand-serif mt-2 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ auth()->user()->name }}</h1>
                    <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">
                        {{ $profile->session_started_at ? __('On session since :time', ['time' => $profile->session_started_at->format('g:i A')]) : __('Ready when you are') }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <span class="brand-soft-surface inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-neutral-800 dark:text-zinc-100">
                    <i class="fa-solid fa-wallet text-[var(--brand-600)]"></i>
                    <span x-text="peso(earnings.today)">{{ $this->peso($this->todayEarnings) }}</span>
                </span>

                <button
                    type="button"
                    wire:click="toggleSession"
                    class="brand-button-secondary transition-all duration-150 active:scale-[0.96]"
                >
                    <i class="fa-solid {{ $profile->session_started_at ? 'fa-stop' : 'fa-play' }} text-xs"></i>
                    {{ $profile->session_started_at ? __('End Session') : __('Start Session') }}
                </button>

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
            </div>
        </div>
    </section>

    <template x-if="offer">
        <section class="brand-panel animate-pulse border-2 border-[var(--brand-500)] p-5 sm:p-6">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="brand-kicker !mb-0">{{ __('Incoming delivery offer') }}</p>
                    <h2 class="brand-serif mt-3 text-3xl font-bold text-neutral-900 dark:text-zinc-100">
                        {{ __('Order #') }}<span x-text="offer.order_number"></span>
                    </h2>
                    <div class="mt-3 flex flex-wrap gap-3 text-sm font-medium text-neutral-600 dark:text-zinc-300">
                        <span x-text="offer.store_name"></span>
                        <span>{{ __('Items:') }} <span x-text="offer.item_count"></span></span>
                        <span x-show="offer.distance_km !== null">~<span x-text="offer.distance_km"></span> {{ __('km away') }}</span>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="rounded-2xl bg-emerald-50 px-5 py-3 text-center text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-200">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em]">{{ __('Respond in') }}</p>
                        <p class="text-3xl font-bold tabular-nums" x-text="countdown"></p>
                    </div>

                    <button type="button" x-on:click="declineOffer()" class="brand-button-secondary transition-all duration-150 active:scale-[0.96]">
                        {{ __('Decline') }}
                    </button>

                    <button type="button" x-on:click="acceptOffer()" class="brand-button-primary transition-all duration-150 active:scale-[0.96]">
                        {{ __('Accept') }}
                    </button>
                </div>
            </div>
        </section>
    </template>

    <div x-show="offerFlash" x-cloak class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3 text-sm font-semibold text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200" x-text="offerFlash"></div>

    <section class="grid gap-4 md:grid-cols-4">
        @foreach ([
            ['label' => __('Active deliveries'), 'value' => number_format($this->myActiveDeliveries->count()), 'icon' => 'fa-solid fa-route'],
            ['label' => __('Completed today'), 'value' => number_format($this->completedToday), 'icon' => 'fa-solid fa-calendar-check'],
            ['label' => __('Rating'), 'value' => $profile->formattedRating().' ★', 'icon' => 'fa-solid fa-star'],
            ['label' => __("Today's earnings"), 'value' => $this->peso($this->todayEarnings), 'icon' => 'fa-solid fa-wallet'],
        ] as $stat)
            <article class="brand-panel-muted flex items-center gap-4 p-5">
                <span class="brand-soft-surface flex h-12 w-12 items-center justify-center rounded-2xl">
                    <i class="{{ $stat['icon'] }}"></i>
                </span>
                <div>
                    <p class="text-2xl font-bold tabular-nums text-neutral-900 dark:text-zinc-100">{{ $stat['value'] }}</p>
                    <p class="text-sm text-neutral-500 dark:text-zinc-400">{{ $stat['label'] }}</p>
                </div>
            </article>
        @endforeach
    </section>

    <section class="brand-panel p-5 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="brand-kicker !mb-0">{{ __('Earnings') }}</p>
                <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Last 7 days') }}</h2>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ([
                    ['label' => __('Today'), 'key' => 'today', 'value' => $this->peso($this->todayEarnings)],
                    ['label' => __('This week'), 'key' => 'this_week', 'value' => $this->peso(0)],
                    ['label' => __('This month'), 'key' => 'this_month', 'value' => $this->peso(0)],
                ] as $chip)
                    <div class="brand-panel-muted min-w-36 px-4 py-3">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ $chip['label'] }}</p>
                        <p class="mt-1 text-lg font-bold text-neutral-900 dark:text-zinc-100" x-text="peso(earnings.{{ $chip['key'] }})">{{ $chip['value'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        @if (array_sum($this->sevenDayEarnings['series']) === 0.0)
            <div class="mt-6 flex h-[220px] items-center justify-center rounded-[1.5rem] border border-dashed border-stone-200 text-sm text-neutral-500 dark:border-white/10 dark:text-zinc-400">
                {{ __('Completed delivery earnings will appear here.') }}
            </div>
        @else
            <x-chart-canvas
                type="bar"
                :labels="$this->sevenDayEarnings['labels']"
                :series="$this->sevenDayEarnings['series']"
                :colors="['brand-600']"
                formatter="currency"
                height="220px"
                :max-ticks="7"
                :aria-label="__('Bar chart showing rider earnings for the last seven days')"
            />
        @endif
    </section>

    <section class="grid gap-8 xl:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
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
                    <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">{{ __('Accepted delivery offers will appear here automatically.') }}</p>
                </div>
            @endforelse
        </div>

        <div class="space-y-5">
            <div>
                <p class="brand-kicker">{{ __('Nearby ready orders') }}</p>
                <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Informational queue') }}</h2>
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

                    <button
                        type="button"
                        wire:click="claimDelivery({{ $order->id }})"
                        wire:loading.attr="disabled"
                        wire:target="claimDelivery({{ $order->id }})"
                        @disabled(! $this->canClaimMoreDeliveries)
                        class="brand-button-primary mt-5 w-full transition-all duration-150 active:scale-[0.96] disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="claimDelivery({{ $order->id }})">
                            {{ $this->canClaimMoreDeliveries ? __('Claim delivery') : __('Delivery limit reached') }}
                        </span>
                        <span wire:loading wire:target="claimDelivery({{ $order->id }})">{{ __('Claiming...') }}</span>
                    </button>
                </article>
            @empty
                <div class="brand-panel px-6 py-12 text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-neutral-400 dark:bg-zinc-800 dark:text-zinc-400">
                        <i class="fa-solid fa-box-open text-xl"></i>
                    </span>
                    <h3 class="brand-serif mt-5 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('No ready orders nearby') }}</h3>
                    <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">{{ __('New offers will still arrive here as vendors finish preparing delivery orders.') }}</p>
                </div>
            @endforelse
        </div>
    </section>
</section>
