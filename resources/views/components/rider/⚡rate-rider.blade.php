<?php

use App\Enums\AuditEvent;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\RiderRating;
use App\Services\AuditLogger;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rate rider')] class extends Component
{
    public int $orderId;

    public int $rating = 0;

    public string $comment = '';

    public function mount(int $orderId): void
    {
        $order = Order::query()
            ->select(['id', 'customer_id', 'rider_id', 'order_status'])
            ->findOrFail($orderId);

        abort_unless(auth()->id() === $order->customer_id, 403);
        abort_unless($order->order_status === OrderStatus::Delivered, 403);
        abort_if($order->rider_id === null, 404);

        $this->orderId = $order->getKey();
    }

    public function submit(): void
    {
        $validated = $this->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:200'],
        ]);

        $order = $this->order;

        if ($order->riderRating !== null) {
            Flux::toast(variant: 'warning', text: __('You already rated this delivery.'));

            return;
        }

        $rating = RiderRating::query()->create([
            'order_id' => $order->getKey(),
            'rider_id' => $order->rider_id,
            'customer_id' => auth()->id(),
            'rating' => (int) $validated['rating'],
            'comment' => blank($validated['comment'] ?? null) ? null : $validated['comment'],
        ]);

        $order->rider?->riderProfile?->recalculateStats();

        AuditLogger::log(
            AuditEvent::RiderRated,
            "Customer rated rider for order #{$order->id}.",
            $rating,
            auth()->id(),
        );

        unset($this->order);

        Flux::toast(variant: 'success', text: __('Thanks for rating your rider!'));

        $this->dispatch('rating-submitted', orderId: $order->getKey());
    }

    #[Computed]
    public function order(): Order
    {
        return Order::query()
            ->with(['rider:id,name', 'rider.riderProfile', 'riderRating'])
            ->findOrFail($this->orderId);
    }
}; ?>

<section class="brand-panel p-6 sm:p-8">
    <div>
        <p class="brand-kicker !mb-0">{{ __('Rate your rider') }}</p>
        <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">
            {{ __('How was your delivery?') }}
        </h2>
    </div>

    @if ($this->order->riderRating)
        <div class="mt-5 rounded-[1.5rem] border border-emerald-200 bg-emerald-50 p-5 text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200">
            <p class="font-semibold">{{ __('You already rated this delivery') }}</p>
            <div class="mt-3 flex text-amber-500">
                @for ($star = 1; $star <= 5; $star++)
                    <i class="{{ $star <= $this->order->riderRating->rating ? 'fa-solid' : 'fa-regular' }} fa-star"></i>
                @endfor
            </div>
        </div>
    @else
        <form wire:submit="submit" class="mt-6 space-y-5">
            <div
                x-data="{ hovered: 0, selected: @entangle('rating').live }"
                x-on:mouseleave="hovered = 0"
                class="flex items-center gap-2"
            >
                @for ($star = 1; $star <= 5; $star++)
                    <button
                        type="button"
                        x-on:mouseenter="hovered = {{ $star }}"
                        x-on:click="selected = {{ $star }}; $wire.set('rating', {{ $star }})"
                        class="text-3xl text-amber-500 transition-transform duration-150 hover:scale-110 active:scale-95"
                        aria-label="{{ __('Rate :count stars', ['count' => $star]) }}"
                    >
                        <i x-bind:class="(hovered || selected) >= {{ $star }} ? 'fa-solid fa-star' : 'fa-regular fa-star'"></i>
                    </button>
                @endfor
            </div>
            <flux:error name="rating" />

            <flux:textarea wire:model="comment" :label="__('Comment')" :placeholder="__('Optional note for your rider')" maxlength="200" />
            <flux:error name="comment" />

            <button type="submit" wire:loading.attr="disabled" wire:target="submit" class="brand-button-primary transition-all duration-150 active:scale-[0.96]">
                {{ __('Submit rating') }}
            </button>
        </form>
    @endif
</section>
