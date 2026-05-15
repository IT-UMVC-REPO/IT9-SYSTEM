<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Services\StockManager;
use App\Support\UnitFormatter;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Cart')] class extends Component {
    #[On('cart-updated')]
    public function refreshCart(): void
    {
        //
    }

    public function updateQuantity(int $itemId, mixed $quantity): void
    {
        $cartItem = $this->resolveCartItem($itemId);
        $requestedQuantity = filter_var($quantity, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($requestedQuantity === false) {
            Flux::toast(variant: 'warning', text: __('Choose a valid quantity.'));

            return;
        }

        $availableStock = $this->availableStockFor($cartItem);

        if ($availableStock < 1) {
            $cartItem->delete();

            Flux::toast(variant: 'warning', text: __('This listing is no longer in stock and was removed from your cart.'));

            $this->dispatch('cart-updated');

            return;
        }

        $wasCapped = $requestedQuantity > $availableStock;

        $cartItem->update([
            'quantity' => min($availableStock, $requestedQuantity),
        ]);

        Flux::toast(
            variant: $wasCapped ? 'warning' : 'success',
            text: $wasCapped
                ? __('Cart quantity matched the available stock.')
                : __('Cart updated.'),
        );

        $this->dispatch('cart-updated');
    }

    public function decrementQuantity(int $itemId): void
    {
        $cartItem = $this->resolveCartItem($itemId);

        $this->updateQuantity($itemId, max(1, $cartItem->quantity - 1));
    }

    public function incrementQuantity(int $itemId): void
    {
        $cartItem = $this->resolveCartItem($itemId);

        $this->updateQuantity($itemId, min(max(1, $this->availableStockFor($cartItem)), $cartItem->quantity + 1));
    }

    public function removeItem(int $itemId): void
    {
        $this->resolveCartItem($itemId)->delete();

        Flux::toast(variant: 'success', text: __('Item removed from your cart.'));

        $this->dispatch('cart-updated');
    }

    #[Computed]
    public function cart(): ?Cart
    {
        return Cart::query()
            ->where('customer_id', auth()->id())
            ->with([
                'cartItems' => fn ($query) => $query
                    ->with([
                        'product.vendor.user',
                        'product.category',
                        'unitVariant',
                    ])
                    ->orderBy('product_id'),
            ])
            ->first();
    }

    #[Computed]
    public function cartItems(): Collection
    {
        return $this->cart?->cartItems ?? collect();
    }

    #[Computed]
    public function groupedCartItems(): Collection
    {
        return $this->cartItems->groupBy(
            fn (CartItem $item): int => $item->product->vendor_id,
        );
    }

    #[Computed]
    public function vendorSubtotals(): Collection
    {
        return $this->groupedCartItems->map(
            fn (Collection $items): float => (float) $items->sum(
                fn (CartItem $item): float => $item->lineTotal(),
            ),
        );
    }

    #[Computed]
    public function subtotal(): float
    {
        return (float) $this->cartItems->sum(
            fn (CartItem $item): float => $item->lineTotal(),
        );
    }

    private function resolveCartItem(int $itemId): CartItem
    {
        return CartItem::query()
            ->whereKey($itemId)
            ->whereHas('cart', fn (Builder $builder): Builder => $builder->where('customer_id', auth()->id()))
            ->with(['product.vendor.user', 'product.category', 'unitVariant'])
            ->firstOrFail();
    }

    public function availableStockFor(CartItem $item): int
    {
        if ($item->unitVariant !== null) {
            return app(StockManager::class)->availableQuantityFor($item->unitVariant);
        }

        return (int) $item->product->stock_quantity;
    }

    public function priceWithUnit(CartItem $item): string
    {
        return $item->unitVariant?->priceWithUnit() ?? $item->product->priceWithUnit();
    }

    public function quantitySummary(CartItem $item): string
    {
        $unit = $item->unitVariant?->unit ?? $item->product->unit;

        return UnitFormatter::format($unit, $item->quantity);
    }

    public function convertedQuantityLabel(CartItem $item): ?string
    {
        return ($item->unitVariant?->conversionFor($item->quantity) ?? $item->product->conversionFor($item->quantity))
            ?->convertedQuantityLabel();
    }
}; ?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <span class="brand-kicker">{{ __('Your basket') }}</span>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Cart') }}</h1>
        <p class="max-w-2xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Review your market picks, adjust quantities, and keep every stall in one shared basket before checkout.') }}
        </p>
    </section>

    @if ($this->groupedCartItems->isNotEmpty())
        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="space-y-6">
                @foreach ($this->groupedCartItems as $vendorId => $items)
                    @php($vendor = $items->first()->product->vendor)

                    <section wire:key="cart-vendor-{{ $vendorId }}" wire:transition class="suki-reveal space-y-4" style="transition-delay: {{ $loop->index * 80 }}ms">
                        <div class="brand-panel-muted flex flex-col gap-4 rounded-[2rem] p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-4">
                                <div class="h-16 w-16 shrink-0 overflow-hidden rounded-[1.5rem] border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-800">
                                    <img
                                        src="{{ $vendor->store_image_url }}"
                                        alt="{{ $vendor->store_name }}"
                                        class="h-full w-full object-cover"
                                        onerror="this.src='https://placehold.co/320x320/e7e5e4/9ca3af?text=Store'"
                                        loading="lazy"
                                    >
                                </div>

                                <div class="space-y-1">
                                    <p class="brand-kicker !mb-0">{{ __('Vendor section') }}</p>
                                    <h2 class="brand-serif text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ $vendor->store_name }}</h2>
                                    <p class="text-sm text-neutral-500 dark:text-zinc-400">
                                        {{ trans_choice(':count item|:count items', $items->count(), ['count' => $items->count()]) }}
                                    </p>
                                </div>
                            </div>

                            <div class="rounded-[1.5rem] border border-stone-200 bg-white/80 px-4 py-3 text-sm dark:border-white/10 dark:bg-zinc-900">
                                <p class="text-neutral-500 dark:text-zinc-400">{{ __('Vendor subtotal') }}</p>
                                <p class="mt-1 text-lg font-semibold text-neutral-900 dark:text-zinc-100">
                                    {{ __('₱:amount', ['amount' => number_format((float) $this->vendorSubtotals->get($vendorId, 0), 2)]) }}
                                </p>
                            </div>
                        </div>

                        @foreach ($items as $item)
                            <article
                                wire:key="cart-item-{{ $item->id }}"
                                wire:transition
                                class="brand-panel flex flex-col gap-5 p-5 transition-all duration-300 dark:border-white/10 dark:bg-zinc-900"
                                wire:loading.class="opacity-60 scale-[0.99] blur-[0.5px]"
                                wire:target="updateQuantity,incrementQuantity,decrementQuantity,removeItem"
                            >
                                <div class="flex flex-col gap-5 md:flex-row md:items-center">
                                    <a
                                        href="{{ route('shop.products.show', $item->product) }}"
                                        wire:navigate
                                        class="h-16 w-16 shrink-0 overflow-hidden rounded-xl border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-800"
                                    >
                                        <img
                                            src="{{ $item->product->image }}"
                                            alt="{{ $item->product->name }}"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        >
                                    </a>

                                    <div class="min-w-0 flex-1">
                                        <a
                                            href="{{ route('shop.products.show', $item->product) }}"
                                            wire:navigate
                                            class="brand-group-hover-text text-lg font-semibold text-neutral-900 transition dark:text-zinc-100"
                                        >
                                            {{ $item->product->name }}
                                        </a>
                                        <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">{{ $item->product->vendor->store_name }}</p>
                                        <span class="mt-3 inline-flex rounded-full border border-stone-200 bg-stone-50 px-3 py-1 text-xs font-semibold text-neutral-600 dark:border-white/10 dark:bg-zinc-800 dark:text-zinc-300">
                                            {{ $item->product->category->name }}
                                        </span>
                                    </div>

                                    <div class="grid gap-4 md:min-w-[15rem] md:justify-items-end">
                                        <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">
                                            {{ $this->priceWithUnit($item) }}
                                        </p>

                                        <div class="flex items-center gap-3">
                                            <button
                                                type="button"
                                                x-data="stepperButton(() => $wire.decrementQuantity({{ $item->id }}))"
                                                x-on:mousedown.prevent="start"
                                                x-on:touchstart.prevent="start"
                                                x-on:mouseup.window="stop"
                                                x-on:mouseleave="stop"
                                                x-on:touchend.window="stop"
                                                x-on:touchcancel.window="stop"
                                                class="brand-stepper-button disabled:cursor-not-allowed disabled:opacity-40"
                                                @disabled($item->quantity <= 1)
                                                aria-label="{{ __('Decrease quantity') }}"
                                            >
                                                <span aria-hidden="true">-</span>
                                            </button>

                                            <input
                                                type="number"
                                                min="1"
                                                max="{{ max(1, $this->availableStockFor($item)) }}"
                                                value="{{ $item->quantity }}"
                                                wire:change="updateQuantity({{ $item->id }}, $event.target.value)"
                                                class="brand-stepper-input"
                                                aria-label="{{ __('Quantity for :product', ['product' => $item->product->name]) }}"
                                            >

                                            <button
                                                type="button"
                                                x-data="stepperButton(() => $wire.incrementQuantity({{ $item->id }}))"
                                                x-on:mousedown.prevent="start"
                                                x-on:touchstart.prevent="start"
                                                x-on:mouseup.window="stop"
                                                x-on:mouseleave="stop"
                                                x-on:touchend.window="stop"
                                                x-on:touchcancel.window="stop"
                                                class="brand-stepper-button disabled:cursor-not-allowed disabled:opacity-40"
                                                @disabled($item->quantity >= $this->availableStockFor($item))
                                                aria-label="{{ __('Increase quantity') }}"
                                            >
                                                <span aria-hidden="true">+</span>
                                            </button>
                                        </div>

                                        @if ($this->convertedQuantityLabel($item))
                                            <p class="text-xs font-medium text-neutral-400 dark:text-zinc-500">
                                                {{ __(':quantity (:converted total)', [
                                                    'quantity' => $this->quantitySummary($item),
                                                    'converted' => $this->convertedQuantityLabel($item),
                                                ]) }}
                                            </p>
                                        @endif

                                        <div class="flex items-center gap-4">
                                            <p class="text-base font-semibold text-neutral-900 dark:text-zinc-100">
                                                {{ \App\Support\UnitFormatter::currency($item->lineTotal()) }}
                                            </p>

                                            <button
                                                type="button"
                                                x-data
                                                x-on:click="$flux.modal('remove-cart-item-{{ $item->id }}').show()"
                                                wire:loading.attr="disabled"
                                                class="text-sm font-medium text-rose-500 transition hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300"
                                            >
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <flux:modal name="remove-cart-item-{{ $item->id }}" class="max-w-sm">
                                        <div class="p-6 space-y-4">
                                            <flux:heading size="lg">{{ __('Remove item?') }}</flux:heading>
                                            <flux:text>{{ __('This product will be removed from your cart.') }}</flux:text>

                                            <div class="flex justify-end gap-3 pt-2">
                                                <flux:button variant="ghost" x-on:click="$flux.modal('remove-cart-item-{{ $item->id }}').close()">
                                                    {{ __('Cancel') }}
                                                </flux:button>

                                                <flux:button variant="danger" wire:click="removeItem({{ $item->id }})" x-on:click="$flux.modal('remove-cart-item-{{ $item->id }}').close()">
                                                    {{ __('Remove') }}
                                                </flux:button>
                                            </div>
                                        </div>
                                    </flux:modal>
                                </div>
                            </article>
                        @endforeach
                    </section>
                @endforeach
            </div>

            <aside
                x-data
                x-init
                x-transition:enter="transition ease-out duration-400 delay-200"
                x-transition:enter-start="opacity-0 translate-x-4"
                x-transition:enter-end="opacity-100 translate-x-0"
                class="self-start xl:sticky xl:top-24"
            >
                <div class="brand-panel space-y-5 p-6 dark:border-white/10 dark:bg-zinc-900">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-400">{{ __('Order summary') }}</p>
                        <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Ready for checkout') }}</h2>
                    </div>

                    <div class="space-y-4">
                        @foreach ($this->groupedCartItems as $vendorId => $items)
                            @php($vendor = $items->first()->product->vendor)

                            <section wire:key="cart-summary-vendor-{{ $vendorId }}" class="space-y-3 rounded-[1.5rem] border border-stone-200 bg-stone-50 p-4 dark:border-white/10 dark:bg-zinc-800">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $vendor->store_name }}</p>
                                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Vendor') }}</p>
                                </div>

                                @foreach ($items as $item)
                                    <div wire:key="cart-summary-item-{{ $item->id }}" class="flex items-center justify-between gap-3 text-sm">
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-neutral-900 dark:text-zinc-100">{{ $item->product->name }}</p>
                                            <p class="text-neutral-500 dark:text-zinc-400">
                                                {{ $this->quantitySummary($item) }}
                                                @if ($this->convertedQuantityLabel($item))
                                                    ({{ __(':converted total', ['converted' => $this->convertedQuantityLabel($item)]) }})
                                                @endif
                                                × {{ \App\Support\UnitFormatter::currency($item->unitPrice()) }}
                                            </p>
                                        </div>
                                        <p class="shrink-0 font-semibold text-neutral-900 dark:text-zinc-100">
                                            {{ \App\Support\UnitFormatter::currency($item->lineTotal()) }}
                                        </p>
                                    </div>
                                @endforeach

                                <div class="flex items-center justify-between gap-4 border-t border-stone-200 pt-3 text-sm dark:border-white/10">
                                    <span class="font-medium text-neutral-500 dark:text-zinc-400">{{ __('Vendor subtotal') }}</span>
                                    <span class="font-semibold text-neutral-900 dark:text-zinc-100">
                                        {{ __('₱:amount', ['amount' => number_format((float) $this->vendorSubtotals->get($vendorId, 0), 2)]) }}
                                    </span>
                                </div>
                            </section>
                        @endforeach
                    </div>

                    <div class="rounded-[1.5rem] border border-stone-200 bg-stone-50 p-4 dark:border-white/10 dark:bg-zinc-800">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-sm font-medium text-neutral-500 dark:text-zinc-400">{{ __('Grand total') }}</span>
                            <span class="text-lg font-semibold text-neutral-900 dark:text-zinc-100">
                                {{ __('₱:amount', ['amount' => number_format($this->subtotal, 2)]) }}
                            </span>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-neutral-500 dark:text-zinc-400">
                            {{ __('Delivery fees are agreed with each vendor.') }}
                        </p>
                    </div>

                    <a href="{{ route('shop.checkout') }}" wire:navigate class="brand-button-primary active:scale-[0.96] w-full">
                        {{ __('Proceed to checkout') }}
                    </a>

                    <a href="{{ route('shop.home') }}" wire:navigate class="brand-button-secondary active:scale-[0.96] w-full">
                        {{ __('Continue shopping') }}
                    </a>
                </div>
            </aside>
        </section>
    @else
        <div wire:transition class="brand-panel px-6 py-14 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-neutral-400 dark:bg-zinc-800 dark:text-zinc-400">
                <i class="fa-solid fa-basket-shopping text-xl"></i>
            </span>
            <h2 class="brand-serif mt-5 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Your cart is empty') }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                {{ __('Head to the storefront and find your local stall.') }}
            </p>
            <a href="{{ route('shop.home') }}" wire:navigate class="brand-button-primary active:scale-[0.96] mt-5">
                {{ __('Browse the market') }}
            </a>
        </div>
    @endif
</div>
