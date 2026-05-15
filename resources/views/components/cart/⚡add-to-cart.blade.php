<?php

use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductUnitVariant;
use App\Services\StockManager;
use App\Support\UnitFormatter;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Product $product;

    public ?int $selectedVariantId = null;

    public string $quantity = '1';

    public function mount(Product $product): void
    {
        abort_unless(Auth::check(), 403);
        abort_if(Auth::user()->effectiveMarketplaceRole() === UserRole::Admin, 403);

        $this->product = Product::query()
            ->visibleToCustomers()
            ->with('unitVariants')
            ->findOrFail($product->getKey());

        $this->selectedVariantId = $this->product->purchasableVariant()?->getKey();
        $this->quantity = $this->availableStock > 0 ? '1' : '0';
    }

    public function updatedSelectedVariantId(): void
    {
        $this->clearComputedUnitState();
        $this->selectedVariantId = $this->selectedVariant?->getKey();
        $this->quantity = (string) $this->normalizedRequestedQuantity($this->quantity, $this->availableStock);
    }

    public function updatedQuantity(mixed $value): void
    {
        $this->clearComputedUnitState();
        $this->quantity = (string) $this->normalizedRequestedQuantity($value, $this->availableStock);
    }

    public function decrementQuantity(): void
    {
        $currentQuantity = $this->normalizedRequestedQuantity($this->quantity, $this->availableStock);

        if ($currentQuantity <= 1) {
            $this->quantity = (string) max(1, $currentQuantity);

            return;
        }

        $this->quantity = (string) ($currentQuantity - 1);
    }

    public function incrementQuantity(): void
    {
        $currentQuantity = $this->normalizedRequestedQuantity($this->quantity, $this->availableStock);

        if ($this->availableStock < 1) {
            $this->quantity = '0';

            return;
        }

        $this->quantity = (string) min($this->availableStock, $currentQuantity + 1);
    }

    public function addToCart(): void
    {
        $result = $this->attemptCartMutation();

        if ($result === 'unavailable') {
            return;
        }

        Flux::toast(
            variant: $result === 'capped' ? 'warning' : 'success',
            text: $result === 'capped'
                ? __('Cart quantity matched the remaining stock for this listing.')
                : __('Added to cart.'),
        );

        $this->dispatch('cart-updated');
    }

    public function buyNow(): void
    {
        $result = $this->attemptCartMutation();

        if ($result === 'unavailable') {
            return;
        }

        $this->dispatch('cart-updated');
        $this->redirectRoute('shop.cart', navigate: true);
    }

    #[Computed]
    public function selectedVariant(): ?ProductUnitVariant
    {
        if ($this->selectedVariantId === null) {
            return $this->product->purchasableVariant();
        }

        return $this->product->unitVariants->firstWhere('id', $this->selectedVariantId)
            ?? $this->product->purchasableVariant();
    }

    #[Computed]
    public function availableStock(): int
    {
        if ($this->selectedVariant instanceof ProductUnitVariant) {
            return app(StockManager::class)->availableQuantityFor($this->selectedVariant);
        }

        return (int) $this->product->stock_quantity;
    }

    #[Computed]
    public function conversionPreview(): ?string
    {
        return ($this->selectedVariant?->conversionFor(1) ?? $this->product->conversionFor(1))?->displayString;
    }

    #[Computed]
    public function pricePerConversionUnit(): ?string
    {
        return ($this->selectedVariant?->conversionFor(1) ?? $this->product->conversionFor(1))?->pricePerConversionUnitLabel();
    }

    #[Computed]
    public function selectedPriceWithUnit(): string
    {
        return $this->selectedVariant?->priceWithUnit() ?? $this->product->priceWithUnit();
    }

    #[Computed]
    public function selectedStockLabel(): string
    {
        return UnitFormatter::format($this->selectedVariant?->unit ?? $this->product->unit, $this->availableStock);
    }

    private function attemptCartMutation(): string
    {
        $customer = Auth::user();
        $product = $this->freshPurchasableProduct();

        if ($product === null) {
            return 'unavailable';
        }

        $cart = Cart::query()->firstOrCreate(
            ['customer_id' => $customer->getKey()],
            ['created_at' => now()],
        );

        $requestedQuantity = $this->normalizedRequestedQuantity($this->quantity, $this->availableStock);
        $wasCapped = $this->storeCartItem($cart, $product, $requestedQuantity);

        $this->product = $product;

        return $wasCapped ? 'capped' : 'success';
    }

    private function freshPurchasableProduct(): ?Product
    {
        $product = Product::query()
            ->with(['vendor.user', 'category.parent', 'unitVariants'])
            ->findOrFail($this->product->getKey());

        $this->product = $product;
        $this->clearComputedUnitState();
        $this->selectedVariantId = $this->selectedVariant?->getKey();

        if (
            $product->status !== ProductStatus::Active
            || $product->vendor->status !== VendorStatus::Approved
            || $this->availableStock < 1
        ) {
            $this->quantity = '0';

            Flux::toast(variant: 'warning', text: __('This listing is no longer available for purchase.'));

            return null;
        }

        return $product;
    }

    private function normalizedRequestedQuantity(mixed $value, int $availableStock): int
    {
        if ($availableStock < 1) {
            return 0;
        }

        $parsedQuantity = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($parsedQuantity === false) {
            return 1;
        }

        return min($availableStock, $parsedQuantity);
    }

    private function storeCartItem(Cart $cart, Product $product, int $requestedQuantity): bool
    {
        $cartItem = CartItem::query()->firstOrNew([
            'cart_id' => $cart->getKey(),
            'product_id' => $product->getKey(),
            'product_unit_variant_id' => $this->selectedVariant?->getKey(),
        ]);

        $existingQuantity = $cartItem->exists ? $cartItem->quantity : 0;
        $targetQuantity = min($this->availableStock, $existingQuantity + $requestedQuantity);

        $cartItem->quantity = $targetQuantity;
        $cartItem->save();

        return $targetQuantity < ($existingQuantity + $requestedQuantity);
    }

    private function clearComputedUnitState(): void
    {
        unset(
            $this->selectedVariant,
            $this->availableStock,
            $this->conversionPreview,
            $this->pricePerConversionUnit,
            $this->selectedPriceWithUnit,
            $this->selectedStockLabel,
        );
    }
}; ?>

<div class="brand-panel p-6 transition-all duration-200 dark:border-white/10 dark:bg-zinc-900">
    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-400">{{ __('Purchase panel') }}</p>
    <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Bring this stall to your cart') }}</h2>
    <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
        {{ __('Choose the unit and quantity you need, then add the item to your cart or head straight to checkout.') }}
    </p>

    @if ($product->unitVariants->count() > 1)
        <div class="mt-5 space-y-3">
            <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Selling unit') }}</p>
            <div class="grid gap-2">
                @foreach ($product->unitVariants as $variant)
                    <button
                        type="button"
                        wire:key="purchase-variant-{{ $variant->id }}"
                        wire:click="$set('selectedVariantId', {{ $variant->id }})"
                        class="flex items-center justify-between gap-3 rounded-2xl border px-4 py-3 text-left text-sm transition"
                        @class([
                            'border-[var(--brand-600)] bg-[var(--brand-50)] text-[var(--brand-900)] dark:bg-[var(--brand-900)]/30 dark:text-[var(--brand-100)]' => $this->selectedVariant?->is($variant),
                            'border-stone-200 bg-white text-neutral-700 hover:border-[var(--brand-300)] dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-200' => ! $this->selectedVariant?->is($variant),
                        ])
                    >
                        <span class="font-semibold">{{ __('Per :unit', ['unit' => strtolower($variant->unit->label())]) }}</span>
                        <span>{{ $variant->priceWithUnit() }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-5 rounded-[1.5rem] border border-stone-200 bg-stone-50 p-4 dark:border-white/10 dark:bg-zinc-800">
        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Price') }}</p>
        <p class="mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ $this->selectedPriceWithUnit }}</p>
        @if ($this->conversionPreview && $this->pricePerConversionUnit)
            <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">
                {{ __('(:price - :conversion)', [
                    'price' => $this->pricePerConversionUnit,
                    'conversion' => $this->conversionPreview,
                ]) }}
            </p>
        @endif
    </div>

    @if ($this->availableStock > 0)
        <div wire:transition class="mt-6 space-y-5">
            <div class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <label for="purchase-quantity" class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Quantity') }}</label>
                    <span class="text-xs font-medium text-neutral-400 dark:text-zinc-400">{{ __('Up to :amount available', ['amount' => $this->selectedStockLabel]) }}</span>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        x-data="stepperButton(() => $wire.decrementQuantity())"
                        x-on:mousedown.prevent="start"
                        x-on:touchstart.prevent="start"
                        x-on:mouseup.window="stop"
                        x-on:mouseleave="stop"
                        x-on:touchend.window="stop"
                        x-on:touchcancel.window="stop"
                        class="brand-stepper-button disabled:cursor-not-allowed disabled:opacity-40"
                        @disabled((int) $quantity <= 1)
                        aria-label="{{ __('Decrease quantity') }}"
                    >
                        <span aria-hidden="true">-</span>
                    </button>

                    <input
                        id="purchase-quantity"
                        type="number"
                        min="1"
                        max="{{ $this->availableStock }}"
                        wire:model.live="quantity"
                        class="brand-stepper-input transition-colors duration-150"
                    >

                    <button
                        type="button"
                        x-data="stepperButton(() => $wire.incrementQuantity())"
                        x-on:mousedown.prevent="start"
                        x-on:touchstart.prevent="start"
                        x-on:mouseup.window="stop"
                        x-on:mouseleave="stop"
                        x-on:touchend.window="stop"
                        x-on:touchcancel.window="stop"
                        class="brand-stepper-button disabled:cursor-not-allowed disabled:opacity-40"
                        @disabled((int) $quantity >= $this->availableStock)
                        aria-label="{{ __('Increase quantity') }}"
                    >
                        <span aria-hidden="true">+</span>
                    </button>
                </div>
            </div>

            <div class="space-y-3">
                <button
                    type="button"
                    wire:click="addToCart"
                    wire:loading.attr="disabled"
                    wire:target="addToCart"
                    class="brand-button-primary w-full transition-all duration-150 active:scale-[0.96]"
                >
                    <span wire:loading.remove wire:target="addToCart" class="inline-flex items-center justify-center gap-2">
                        <i class="fa-solid fa-cart-plus text-xs"></i>
                        {{ __('Add to cart') }}
                    </span>
                    <span wire:loading wire:target="addToCart">{{ __('Adding...') }}</span>
                </button>

                <button
                    type="button"
                    wire:click="buyNow"
                    wire:loading.attr="disabled"
                    wire:target="buyNow"
                    class="brand-button-secondary w-full transition-all duration-150 active:scale-[0.96]"
                >
                    <span wire:loading.remove wire:target="buyNow" class="inline-flex items-center justify-center gap-2">
                        <i class="fa-solid fa-bolt text-xs"></i>
                        {{ __('Buy now') }}
                    </span>
                    <span wire:loading wire:target="buyNow">{{ __('Preparing checkout...') }}</span>
                </button>
            </div>
        </div>
    @else
        <div
            wire:transition
            class="mt-6 flex flex-col gap-4"
        >
            <span class="inline-flex w-full items-center justify-center rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                {{ __('Sold out') }} &mdash; {{ __('check back soon') }}
            </span>

            <a
                href="{{ route('shop.home', ['category' => $product->category->id]) }}"
                class="brand-button-secondary active:scale-[0.96] w-full"
            >
                {{ __('Browse similar :category', ['category' => $product->category->name]) }}
            </a>
        </div>
    @endif
</div>
