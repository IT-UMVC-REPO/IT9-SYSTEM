<?php

use App\Models\Cart;
use App\Models\CartItem;
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

        $availableStock = $cartItem->product->stock_quantity;

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
    public function subtotal(): float
    {
        return (float) $this->cartItems->sum(
            fn (CartItem $item): float => (float) $item->product->price * $item->quantity,
        );
    }

    private function resolveCartItem(int $itemId): CartItem
    {
        return CartItem::query()
            ->whereKey($itemId)
            ->whereHas('cart', fn (Builder $builder): Builder => $builder->where('customer_id', auth()->id()))
            ->with(['product.vendor.user', 'product.category'])
            ->firstOrFail();
    }
}; ?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <span class="brand-kicker">{{ __('Your basket') }}</span>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Cart') }}</h1>
        <p class="max-w-2xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Review your market picks, adjust quantities, and keep everything ready for checkout with one vendor at a time.') }}
        </p>
    </section>

    @if ($this->cartItems->isNotEmpty())
        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="space-y-4">
                @foreach ($this->cartItems as $item)
                    <article
                        wire:key="cart-item-{{ $item->id }}"
                        class="brand-panel flex flex-col gap-5 p-5 transition duration-200 dark:border-white/10 dark:bg-zinc-900"
                        wire:loading.class="opacity-60"
                        wire:target="updateQuantity,removeItem"
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
                                    {{ __('PHP :amount each', ['amount' => number_format((float) $item->product->price, 2)]) }}
                                </p>

                                <div class="flex items-center gap-3">
                                    <button
                                        type="button"
                                        wire:click="updateQuantity({{ $item->id }}, {{ max(1, $item->quantity - 1) }})"
                                        wire:loading.attr="disabled"
                                        class="brand-stepper-button disabled:cursor-not-allowed disabled:opacity-40"
                                        @disabled($item->quantity <= 1)
                                        aria-label="{{ __('Decrease quantity') }}"
                                    >
                                        <span aria-hidden="true">-</span>
                                    </button>

                                    <input
                                        type="number"
                                        min="1"
                                        max="{{ max(1, $item->product->stock_quantity) }}"
                                        value="{{ $item->quantity }}"
                                        wire:change="updateQuantity({{ $item->id }}, $event.target.value)"
                                        class="brand-stepper-input"
                                        aria-label="{{ __('Quantity for :product', ['product' => $item->product->name]) }}"
                                    >

                                    <button
                                        type="button"
                                        wire:click="updateQuantity({{ $item->id }}, {{ min(max(1, $item->product->stock_quantity), $item->quantity + 1) }})"
                                        wire:loading.attr="disabled"
                                        class="brand-stepper-button disabled:cursor-not-allowed disabled:opacity-40"
                                        @disabled($item->quantity >= $item->product->stock_quantity)
                                        aria-label="{{ __('Increase quantity') }}"
                                    >
                                        <span aria-hidden="true">+</span>
                                    </button>
                                </div>

                                <div class="flex items-center gap-4">
                                    <p class="text-base font-semibold text-neutral-900 dark:text-zinc-100">
                                        {{ __('PHP :amount', ['amount' => number_format((float) $item->product->price * $item->quantity, 2)]) }}
                                    </p>

                                    <button
                                        type="button"
                                        wire:click="removeItem({{ $item->id }})"
                                        wire:confirm="{{ __('Remove this item from your cart?') }}"
                                        wire:loading.attr="disabled"
                                        class="text-sm font-medium text-rose-500 transition hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300"
                                    >
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <aside class="self-start xl:sticky xl:top-24">
                <div class="brand-panel space-y-5 p-6 dark:border-white/10 dark:bg-zinc-900">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-400">{{ __('Order summary') }}</p>
                        <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Ready for checkout') }}</h2>
                    </div>

                    <div class="space-y-3">
                        @foreach ($this->cartItems as $item)
                            <div wire:key="cart-summary-item-{{ $item->id }}" class="flex items-center justify-between gap-3 text-sm">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-neutral-900 dark:text-zinc-100">{{ $item->product->name }}</p>
                                    <p class="text-neutral-500 dark:text-zinc-400">{{ __(':qty × PHP :amount', [
                                        'qty' => $item->quantity,
                                        'amount' => number_format((float) $item->product->price, 2),
                                    ]) }}</p>
                                </div>
                                <p class="shrink-0 font-semibold text-neutral-900 dark:text-zinc-100">
                                    {{ __('PHP :amount', ['amount' => number_format((float) $item->product->price * $item->quantity, 2)]) }}
                                </p>
                            </div>
                        @endforeach
                    </div>

                    <div class="rounded-[1.5rem] border border-stone-200 bg-stone-50 p-4 dark:border-white/10 dark:bg-zinc-800">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-sm font-medium text-neutral-500 dark:text-zinc-400">{{ __('Subtotal') }}</span>
                            <span class="text-lg font-semibold text-neutral-900 dark:text-zinc-100">
                                {{ __('PHP :amount', ['amount' => number_format($this->subtotal, 2)]) }}
                            </span>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-neutral-500 dark:text-zinc-400">
                            {{ __('Delivery fees are agreed with the vendor.') }}
                        </p>
                    </div>

                    <a href="{{ route('shop.checkout') }}" wire:navigate class="brand-button-primary w-full">
                        {{ __('Proceed to checkout') }}
                    </a>

                    <a href="{{ route('shop.home') }}" wire:navigate class="brand-button-secondary w-full">
                        {{ __('Continue shopping') }}
                    </a>
                </div>
            </aside>
        </section>
    @else
        <div class="brand-panel px-6 py-14 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-neutral-400 dark:bg-zinc-800 dark:text-zinc-400">
                <i class="fa-solid fa-basket-shopping text-xl"></i>
            </span>
            <h2 class="brand-serif mt-5 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Your cart is empty') }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                {{ __('Head to the storefront and find your suki.') }}
            </p>
            <a href="{{ route('shop.home') }}" wire:navigate class="brand-button-primary mt-5">
                {{ __('Browse the market') }}
            </a>
        </div>
    @endif
</div>
