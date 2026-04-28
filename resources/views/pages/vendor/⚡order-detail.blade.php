<?php

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\VendorStatus;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Order;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Vendor Order Detail')] class extends Component
{
    public int $orderId;

    public function mount(string $orderReference): void
    {
        if (! $this->hasApprovedVendorProfile()) {
            $this->redirectRoute('customer.dashboard', navigate: true);

            return;
        }

        $this->orderId = Order::query()
            ->where('vendor_id', $this->vendorId())
            ->findOrFail((int) $orderReference)
            ->getKey();
    }

    public function advanceStatus(): void
    {
        $currentOrder = $this->order;

        $nextStatus = match ($currentOrder->order_status) {
            OrderStatus::Pending => OrderStatus::Confirmed,
            OrderStatus::Confirmed => OrderStatus::Preparing,
            OrderStatus::Preparing => OrderStatus::Ready,
            OrderStatus::Ready => OrderStatus::Delivered,
            default => null,
        };

        if ($nextStatus === null) {
            Flux::toast(variant: 'warning', text: __('This order has already reached its final state.'));

            return;
        }

        DB::transaction(function () use ($nextStatus): void {
            $order = Order::query()
                ->with('payment')
                ->lockForUpdate()
                ->where('vendor_id', $this->vendorId())
                ->findOrFail($this->orderId);

            $order->forceFill(['order_status' => $nextStatus])->save();

            if ($nextStatus === OrderStatus::Delivered) {
                $order->forceFill(['payment_status' => PaymentStatus::Paid])->save();

                $order->payment?->update([
                    'status' => PaymentStatus::Paid,
                    'paid_at' => now(),
                ]);
            }
        });

        SendOrderNotificationJob::dispatch(
            orderId: $this->orderId,
            userId: $currentOrder->customer_id,
            title: 'Order '.Str::headline($nextStatus->value),
            message: 'Your order #'.$this->orderId.' is now '.Str::lower(Str::headline($nextStatus->value)).'.',
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
            Order::query()
                ->where('vendor_id', $this->vendorId())
                ->findOrFail($this->orderId)
                ->forceFill(['order_status' => OrderStatus::Cancelled])
                ->save();
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

    #[Computed]
    public function order(): Order
    {
        return Order::query()
            ->where('vendor_id', $this->vendorId())
            ->with([
                'customer:id,name,address',
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
            OrderStatus::Ready => OrderStatus::Delivered,
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
        <span class="brand-kicker">{{ __('Vendor fulfilment') }}</span>
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
                                <span>{{ __('Qty :qty', ['qty' => $item->quantity]) }}</span>
                                <span>{{ __('₱:amount each', ['amount' => number_format((float) $item->unit_price, 2)]) }}</span>
                                <span class="font-semibold text-neutral-900 dark:text-zinc-100">
                                    {{ __('₱:amount', ['amount' => number_format((float) $item->unit_price * $item->quantity, 2)]) }}
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
        </div>

        <aside class="space-y-6 xl:sticky xl:top-24 xl:self-start">
            <section class="brand-panel p-6">
                <span class="brand-kicker">{{ __('Status timeline') }}</span>
                <div class="mt-5 space-y-3">
                    @foreach ([OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Delivered] as $status)
                        <div class="flex items-center gap-3" wire:key="vendor-order-status-step-{{ $status->value }}">
                            <span
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold {{ $status === $this->order->order_status ? 'text-white' : 'text-neutral-600 dark:text-zinc-300' }}"
                                style="{{ $status === $this->order->order_status
                                    ? 'background-color: var(--brand-600);'
                                    : 'background-color: color-mix(in oklab, var(--brand-50) 72%, white 28%);' }}"
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

                <div class="mt-6 grid gap-3">
                    @if ($this->nextStatus !== null)
                        <button
                            type="button"
                            wire:click="advanceStatus"
                            wire:loading.attr="disabled"
                            wire:target="advanceStatus"
                            class="brand-button-primary w-full"
                        >
                            {{ __('Advance to :status', ['status' => Str::headline($this->nextStatus->value)]) }}
                        </button>
                    @endif

                    @if ($this->order->order_status === OrderStatus::Pending)
                        <button
                            type="button"
                            wire:click="cancelOrder"
                            wire:confirm="{{ __('Cancel this order? This action cannot be undone.') }}"
                            wire:loading.attr="disabled"
                            wire:target="cancelOrder"
                            class="rounded-xl border border-stone-300 bg-white px-5 py-3 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-stone-50 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-white/5"
                        >
                            {{ __('Cancel order') }}
                        </button>
                    @endif

                    <a
                        href="{{ route('shop.customers.show', $this->order->customer_id) }}"
                        wire:navigate
                        class="brand-button-secondary w-full"
                    >
                        {{ __('View customer profile') }}
                    </a>

                    <a
                        href="{{ route('messages.conversation', ['conversationReference' => $this->order->customer_id, 'order' => $this->order->id]) }}"
                        wire:navigate
                        class="brand-button-secondary w-full"
                    >
                        {{ __('Message customer') }}
                    </a>

                    <flux:modal.trigger name="report-user">
                        <flux:button
                            variant="ghost"
                            type="button"
                            class="w-full justify-center text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-300 dark:hover:bg-rose-500/10 dark:hover:text-rose-200"
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
</section>
