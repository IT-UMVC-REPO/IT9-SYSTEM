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
use App\Services\AuditLogger;
use App\Support\PublicDiskUrl;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Delivery Detail')] class extends Component
{
    use HasRiderGuard;
    use WithFileUploads;

    public int $orderId;

    public string $delivered_note = '';

    public $proofUpload = null;

    public function mount(string $orderReference): void
    {
        $order = Order::query()
            ->select('id', 'rider_id', 'delivered_note')
            ->where('rider_id', auth()->id())
            ->findOrFail((int) $orderReference);

        $this->orderId = $order->getKey();
        $this->delivered_note = $order->delivered_note ?? '';
    }

    public function markOutForDelivery(): void
    {
        $order = DB::transaction(function (): Order {
            $order = Order::query()
                ->where('rider_id', auth()->id())
                ->where('order_status', OrderStatus::PickedUp)
                ->lockForUpdate()
                ->findOrFail($this->orderId);

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

        RecordRiderEarningJob::dispatch($order->getKey());

        unset($this->order);

        Flux::toast(variant: 'success', text: __('Marked as out for delivery.'));
    }

    public function saveProofOfDelivery(): void
    {
        $validated = $this->validate([
            'proofUpload' => ['nullable', 'image', 'max:5120'],
            'delivered_note' => ['nullable', 'string', 'max:200'],
        ]);

        $order = Order::query()
            ->where('rider_id', auth()->id())
            ->whereIn('order_status', [OrderStatus::OutForDelivery, OrderStatus::Delivered])
            ->findOrFail($this->orderId);

        $path = $order->proof_of_delivery_path;

        if ($this->proofUpload !== null) {
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            $path = $this->proofUpload->store('proof-of-delivery', 'public');
            $this->proofUpload = null;
        }

        $order->forceFill([
            'proof_of_delivery_path' => $path,
            'delivered_note' => blank($validated['delivered_note'] ?? null) ? null : $validated['delivered_note'],
        ])->save();

        AuditLogger::log(AuditEvent::ProofOfDeliveryUploaded, "Proof of delivery updated for order #{$order->id}.", $order);

        unset($this->order);

        Flux::toast(variant: 'success', text: __('Proof of delivery saved.'));
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
                'riderRating',
                'riderEarning',
            ])
            ->findOrFail($this->orderId);
    }

    public function proofUrl(?string $path): ?string
    {
        return PublicDiskUrl::nullable($path);
    }

    public function peso(float|int $value): string
    {
        return sprintf("\u{20B1}%s", number_format((float) $value, 2));
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
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
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
            <div class="flex flex-wrap items-center gap-3">
                @if ($this->order->riderRating)
                    <span class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                        {{ __('Customer rated: ★ :rating', ['rating' => number_format((float) $this->order->riderRating->rating, 1)]) }}
                    </span>
                @endif
                <x-order-status-badge :status="$this->order->order_status" />
            </div>
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

            @if (in_array($this->order->order_status, [OrderStatus::OutForDelivery, OrderStatus::Delivered], true))
                <section class="brand-panel p-6 sm:p-8">
                    <div class="flex flex-col gap-2">
                        <p class="brand-kicker !mb-0">{{ __('Proof of delivery') }}</p>
                        <h2 class="brand-serif text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Delivery handoff') }}</h2>
                    </div>

                    <form wire:submit="saveProofOfDelivery" class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,24rem)_minmax(0,1fr)]">
                        <div class="overflow-hidden rounded-[1.25rem] border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-800">
                            @if ($proofUpload)
                                <img src="{{ $proofUpload->temporaryUrl() }}" alt="{{ __('Proof preview') }}" class="aspect-[4/3] max-h-80 w-full object-contain">
                            @elseif ($this->proofUrl($this->order->proof_of_delivery_path))
                                <img src="{{ $this->proofUrl($this->order->proof_of_delivery_path) }}" alt="{{ __('Saved proof of delivery') }}" class="aspect-[4/3] max-h-80 w-full object-contain">
                            @else
                                <div class="flex aspect-[4/3] max-h-80 items-center justify-center text-neutral-400 dark:text-zinc-500">
                                    <i class="fa-solid fa-image text-2xl"></i>
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0 space-y-5">
                            <div class="grid min-w-0 gap-2">
                                <label for="proof-upload" class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Proof image') }}</label>
                                <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center">
                                    <label for="proof-upload" class="brand-button-secondary active:scale-[0.96] shrink-0 cursor-pointer">
                                        {{ __('Choose file') }}
                                    </label>
                                    <input id="proof-upload" type="file" wire:model="proofUpload" accept="image/*" class="sr-only">
                                    <span class="min-w-0 truncate text-sm font-medium text-neutral-500 dark:text-zinc-400">
                                        @if ($proofUpload instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
                                            {{ $proofUpload->getClientOriginalName() }}
                                        @elseif ($this->order->proof_of_delivery_path)
                                            {{ basename($this->order->proof_of_delivery_path) }}
                                        @else
                                            {{ __('No file selected') }}
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <flux:error name="proofUpload" />

                            <flux:textarea wire:model="delivered_note" :label="__('Delivery note')" :placeholder="__('Optional note for this handoff')" maxlength="200" />
                            <flux:error name="delivered_note" />

                            <button type="submit" wire:loading.attr="disabled" wire:target="saveProofOfDelivery,proofUpload" class="brand-button-primary transition-all duration-150 active:scale-[0.96]">
                                <span wire:loading.remove wire:target="saveProofOfDelivery">{{ __('Save proof') }}</span>
                                <span wire:loading wire:target="saveProofOfDelivery">{{ __('Saving...') }}</span>
                            </button>
                        </div>
                    </form>
                </section>
            @endif
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

            @if ($this->order->riderEarning)
                <section class="brand-panel p-6">
                    <p class="brand-kicker !mb-0">{{ __('Earning recorded') }}</p>
                    <div class="mt-4 inline-flex items-center gap-3 rounded-2xl bg-emerald-50 px-4 py-3 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-200">
                        <i class="fa-solid fa-circle-check"></i>
                        <span class="font-semibold">{{ __('You earned :amount for this delivery', ['amount' => $this->peso((float) $this->order->riderEarning->amount)]) }}</span>
                    </div>
                </section>
            @endif
        </aside>
    </div>
</section>
