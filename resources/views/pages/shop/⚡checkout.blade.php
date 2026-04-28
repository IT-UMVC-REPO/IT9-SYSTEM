<?php

use App\Concerns\OrderValidationRules;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\VendorStatus;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Services\PayMongoService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Checkout')] class extends Component {
    use OrderValidationRules;

    public string $delivery_address = '';

    public string $notes = '';

    public string $payment_method = 'cod';

    public function mount(): void
    {
        if ($this->cartItems->isEmpty()) {
            $this->redirectRoute('shop.cart', navigate: true);

            return;
        }

        $this->delivery_address = auth()->user()->address ?? '';
    }

    public function placeOrder(): mixed
    {
        $validated = $this->validate($this->orderRules());
        $customer = auth()->user();
        $paymentMethod = PaymentMethod::from($validated['payment_method']);

        if ($paymentMethod !== PaymentMethod::Cod && $this->groupedCartItems->count() > 1) {
            Flux::toast(
                variant: 'warning',
                text: __('Digital payments are only available for single-vendor orders right now. Switch to Cash on Delivery to place this mixed-vendor cart.'),
            );

            return null;
        }

        try {
            $checkoutState = DB::transaction(function () use ($validated, $customer, $paymentMethod): array {
                $cart = Cart::query()
                    ->where('customer_id', $customer->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($cart === null) {
                    throw new \RuntimeException('Cart not found.');
                }

                $cartItems = CartItem::query()
                    ->where('cart_id', $cart->getKey())
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get();

                if ($cartItems->isEmpty()) {
                    throw new \RuntimeException('Cart is empty.');
                }

                $products = Product::query()
                    ->with(['vendor'])
                    ->whereIn('id', $cartItems->pluck('product_id'))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $validationMessages = [];
                $resolvedItems = collect();

                foreach ($cartItems as $cartItem) {
                    $product = $products->get($cartItem->product_id);

                    if ($product === null) {
                        $validationMessages["cart.{$cartItem->getKey()}"] = __('One of your cart items is no longer available.');

                        continue;
                    }

                    if ($product->status !== ProductStatus::Active) {
                        $validationMessages["cart.{$cartItem->getKey()}"] = __(':product is no longer active.', [
                            'product' => $product->name,
                        ]);

                        continue;
                    }

                    if ($product->vendor->status !== VendorStatus::Approved) {
                        $validationMessages["cart.{$cartItem->getKey()}"] = __(':product is no longer available from this vendor.', [
                            'product' => $product->name,
                        ]);

                        continue;
                    }

                    if ($product->stock_quantity < $cartItem->quantity) {
                        $validationMessages["cart.{$cartItem->getKey()}"] = __('Only :count unit(s) of :product remain in stock.', [
                            'count' => $product->stock_quantity,
                            'product' => $product->name,
                        ]);

                        continue;
                    }

                    $resolvedItems->push([
                        'cart_item' => $cartItem,
                        'product' => $product,
                        'quantity' => $cartItem->quantity,
                        'unit_price' => (float) $product->price,
                        'line_total' => (float) $product->price * $cartItem->quantity,
                    ]);
                }

                if ($validationMessages !== []) {
                    throw \Illuminate\Validation\ValidationException::withMessages($validationMessages);
                }

                $groupedItems = $resolvedItems->groupBy(
                    fn (array $resolvedItem): int => $resolvedItem['product']->vendor_id,
                );

                if ($paymentMethod !== PaymentMethod::Cod && $groupedItems->count() > 1) {
                    throw new \DomainException('Digital payments require a single vendor.');
                }

                $createdOrderIds = [];

                foreach ($groupedItems as $vendorId => $vendorItems) {
                    $vendorTotal = (float) $vendorItems->sum('line_total');

                    $order = Order::query()->create([
                        'customer_id' => $customer->getKey(),
                        'vendor_id' => (int) $vendorId,
                        'total_amount' => $vendorTotal,
                        'payment_method' => $paymentMethod,
                        'payment_status' => PaymentStatus::Pending,
                        'order_status' => OrderStatus::Pending,
                        'delivery_address' => $validated['delivery_address'],
                        'notes' => blank($validated['notes'] ?? null) ? null : $validated['notes'],
                    ]);

                    foreach ($vendorItems as $resolvedItem) {
                        OrderItem::query()->create([
                            'order_id' => $order->getKey(),
                            'product_id' => $resolvedItem['product']->getKey(),
                            'quantity' => $resolvedItem['quantity'],
                            'unit_price' => $resolvedItem['unit_price'],
                        ]);

                        $resolvedItem['product']->decrement('stock_quantity', $resolvedItem['quantity']);
                    }

                    Payment::query()->create([
                        'order_id' => $order->getKey(),
                        'method' => $paymentMethod,
                        'reference_number' => null,
                        'status' => PaymentStatus::Pending,
                        'amount' => $vendorTotal,
                        'paid_at' => null,
                        'created_at' => now(),
                    ]);

                    if ($paymentMethod === PaymentMethod::Cod) {
                        SendOrderNotificationJob::dispatch(
                            orderId: $order->getKey(),
                            userId: $customer->getKey(),
                            title: 'Order placed',
                            message: 'Your order #'.$order->getKey().' has been placed and is awaiting vendor confirmation.',
                        );
                    }

                    $createdOrderIds[] = $order->getKey();
                }

                $cart->cartItems()->delete();

                return [
                    'order_ids' => $createdOrderIds,
                    'payment_method' => $paymentMethod->value,
                ];
            }, attempts: 5);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        } catch (\DomainException $exception) {
            Flux::toast(
                variant: 'warning',
                text: __('Digital payments are only available for single-vendor orders right now. Switch to Cash on Delivery to place this mixed-vendor cart.'),
            );

            return null;
        } catch (\RuntimeException $exception) {
            Flux::toast(variant: 'warning', text: __('Your cart is empty. Add items before checking out.'));
            $this->redirectRoute('shop.cart', navigate: true);

            return null;
        } catch (\Throwable $exception) {
            Log::error('Checkout failed before order completion.', [
                'customer_id' => $customer->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            Flux::toast(variant: 'danger', text: __('We could not place your order. Please try again.'));

            return null;
        }

        $paymentMethod = PaymentMethod::from($checkoutState['payment_method']);
        $createdOrderIds = $checkoutState['order_ids'];

        if ($paymentMethod === PaymentMethod::Cod) {
            Flux::toast(variant: 'success', text: $this->placedOrderMessage(count($createdOrderIds)));

            $this->redirectRoute('shop.orders', navigate: true);

            return null;
        }

        $order = Order::query()
            ->with('payment')
            ->findOrFail($createdOrderIds[0]);
        $payment = $order->payment;

        session(['pending_payment_order_id' => $order->getKey()]);

        try {
            $source = $this->digitalSourceFor(
                method: $paymentMethod,
                totalAmount: (float) $order->total_amount,
                order: $order,
            );

            $payment?->update([
                'reference_number' => data_get($source, 'data.id'),
            ]);

            return redirect()->away($this->checkoutRedirectUrl($source));
        } catch (\RuntimeException $exception) {
            $this->restoreDigitalCheckout($order->getKey());

            Log::error('PayMongo source creation failed during checkout.', [
                'order_id' => $order->getKey(),
                'customer_id' => $customer->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            Flux::toast(variant: 'danger', text: __('Payment service unavailable. Please try again or choose Cash on Delivery.'));

            return null;
        }
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
                fn (CartItem $item): float => (float) $item->product->price * $item->quantity,
            ),
        );
    }

    #[Computed]
    public function orderTotal(): float
    {
        return (float) $this->cartItems->sum(
            fn (CartItem $item): float => (float) $item->product->price * $item->quantity,
        );
    }

    private function digitalSourceFor(PaymentMethod $method, float $totalAmount, Order $order): array
    {
        $service = app(PayMongoService::class);
        $amountInCentavos = (int) round($totalAmount * 100);
        $description = 'Payment for order #'.$order->getKey();
        $successUrl = route('shop.payment.success');
        $failedUrl = route('shop.payment.failed');

        return match ($method) {
            PaymentMethod::Gcash => $service->createGCashSource($amountInCentavos, 'PHP', $description, $successUrl, $failedUrl),
            PaymentMethod::Maya => $service->createMayaSource($amountInCentavos, 'PHP', $description, $successUrl, $failedUrl),
            default => throw new \RuntimeException('Unsupported digital payment method.'),
        };
    }

    private function restoreDigitalCheckout(int $orderId): void
    {
        DB::transaction(function () use ($orderId): void {
            $order = Order::query()
                ->with(['orderItems', 'payment'])
                ->find($orderId);

            if ($order === null) {
                return;
            }

            $cart = Cart::query()->firstOrCreate(
                ['customer_id' => $order->customer_id],
                ['created_at' => now()],
            );

            foreach ($order->orderItems as $orderItem) {
                $product = Product::query()->find($orderItem->product_id);

                if ($product !== null) {
                    $product->increment('stock_quantity', $orderItem->quantity);
                }

                CartItem::query()->updateOrCreate(
                    [
                        'cart_id' => $cart->getKey(),
                        'product_id' => $orderItem->product_id,
                    ],
                    [
                        'quantity' => $orderItem->quantity,
                    ],
                );
            }

            $order->payment?->delete();
            $order->orderItems()->delete();
            $order->delete();
        }, attempts: 5);

        session()->forget('pending_payment_order_id');
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function checkoutRedirectUrl(array $source): string
    {
        $redirectUrl = data_get($source, 'data.attributes.redirect.checkout_url')
            ?? data_get($source, 'data.attributes.checkout_url');

        if (! is_string($redirectUrl) || blank($redirectUrl)) {
            throw new \RuntimeException('PayMongo checkout URL missing from source response.');
        }

        return $redirectUrl;
    }

    private function placedOrderMessage(int $orderCount): string
    {
        return trans_choice('{1} 1 order placed successfully.|[2,*] :count orders placed successfully.', $orderCount, [
            'count' => $orderCount,
        ]);
    }
}; ?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <span class="brand-kicker">{{ __('Final step') }}</span>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Checkout') }}</h1>
        <p class="max-w-2xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Confirm your delivery details, review each vendor section, and choose how you want to pay.') }}
        </p>
    </section>

    @if ($errors->any())
        <div class="brand-panel-muted border border-amber-200 px-5 py-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            <p class="font-semibold">{{ __('Please review the items in your order:') }}</p>
            <ul class="mt-2 space-y-1">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <form wire:submit="placeOrder" class="space-y-6">
            <div class="brand-panel space-y-6 p-6 sm:p-8">
                <flux:textarea
                    wire:model="delivery_address"
                    :label="__('Delivery address')"
                    rows="4"
                    required
                />

                <flux:textarea
                    wire:model="notes"
                    :label="__('Notes for the vendor')"
                    rows="3"
                    :placeholder="__('Optional handling requests or landmarks')"
                />
            </div>

            <div class="brand-panel space-y-5 p-6 sm:p-8" x-data="{ selectedMethod: $wire.entangle('payment_method') }">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-400">{{ __('Payment method') }}</p>
                    <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Choose how you want to pay') }}</h2>
                </div>

                <div class="grid gap-4">
                    @foreach ([
                        ['value' => 'cod', 'icon' => 'fa-money-bill-wave', 'title' => 'Cash on Delivery', 'description' => 'Settle payment when the order arrives at your address.'],
                        ['value' => 'gcash', 'icon' => 'fa-wallet', 'title' => 'GCash (via PayMongo)', 'description' => 'You will be redirected to PayMongo to complete your payment securely.'],
                        ['value' => 'maya', 'icon' => 'fa-credit-card', 'title' => 'Maya (via PayMongo)', 'description' => 'Use Maya through PayMongo before returning to your order confirmation.'],
                    ] as $option)
                        <label
                            class="cursor-pointer rounded-[1.5rem] border p-4 transition"
                            x-bind:class="selectedMethod === '{{ $option['value'] }}'
                                ? 'border-[var(--brand-600)] bg-[color-mix(in_oklab,var(--brand-50),white_35%)] dark:border-[var(--brand-400)] dark:bg-[color-mix(in_oklab,var(--brand-950),black_5%)]'
                                : 'border-stone-200 bg-white hover:border-stone-300 dark:border-white/10 dark:bg-zinc-900 dark:hover:border-white/20'"
                        >
                            <input
                                type="radio"
                                value="{{ $option['value'] }}"
                                wire:model.live="payment_method"
                                x-model="selectedMethod"
                                class="sr-only"
                            >

                            <div class="flex items-start gap-4">
                                <span class="brand-feature-bubble flex h-11 w-11 items-center justify-center rounded-full">
                                    <i class="fa-solid {{ $option['icon'] }}"></i>
                                </span>
                                <div class="space-y-1">
                                    <p class="font-semibold text-neutral-900 dark:text-zinc-100">{{ __($option['title']) }}</p>
                                    <p class="text-sm leading-6 text-neutral-500 dark:text-zinc-400">{{ __($option['description']) }}</p>
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>

                @if ($this->groupedCartItems->count() > 1)
                    <div class="rounded-[1.5rem] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                        <p class="font-semibold">{{ __('Mixed-vendor cart') }}</p>
                        <p class="mt-1">{{ __('Cash on Delivery is currently the only payment option when your basket includes multiple vendors.') }}</p>
                    </div>
                @endif
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="placeOrder"
                class="brand-button-primary w-full"
            >
                <span wire:loading.remove wire:target="placeOrder">{{ __('Place order') }}</span>
                <span wire:loading wire:target="placeOrder">{{ __('Placing order...') }}</span>
            </button>
        </form>

        <aside class="self-start xl:sticky xl:top-24">
            <div class="brand-panel space-y-5 p-6 dark:border-white/10 dark:bg-zinc-900">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-400">{{ __('Order summary') }}</p>
                    <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('From your cart') }}</h2>
                </div>

                <div class="space-y-4">
                    @foreach ($this->groupedCartItems as $vendorId => $items)
                        @php($vendor = $items->first()->product->vendor)

                        <section wire:key="checkout-vendor-{{ $vendorId }}" class="space-y-3 rounded-[1.5rem] border border-stone-200 bg-stone-50 p-4 dark:border-white/10 dark:bg-zinc-800">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $vendor->store_name }}</p>
                                    <p class="text-xs uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Vendor section') }}</p>
                                </div>
                                <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-900">
                                    <img
                                        src="{{ $vendor->store_image_url }}"
                                        alt="{{ $vendor->store_name }}"
                                        class="h-full w-full object-cover"
                                        onerror="this.src='https://placehold.co/320x320/e7e5e4/9ca3af?text=Store'"
                                    >
                                </div>
                            </div>

                            @foreach ($items as $item)
                                <div wire:key="checkout-item-{{ $item->id }}" class="flex items-center gap-3">
                                    <div class="h-14 w-14 shrink-0 overflow-hidden rounded-xl border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-900">
                                        <img src="{{ $item->product->image }}" alt="{{ $item->product->name }}" class="h-full w-full object-cover">
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-semibold text-neutral-900 dark:text-zinc-100">{{ $item->product->name }}</p>
                                        <p class="text-xs text-neutral-400 dark:text-zinc-500">{{ __(':qty × ₱:amount', [
                                            'qty' => $item->quantity,
                                            'amount' => number_format((float) $item->product->price, 2),
                                        ]) }}</p>
                                    </div>

                                    <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">
                                        {{ __('₱:amount', ['amount' => number_format((float) $item->product->price * $item->quantity, 2)]) }}
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
                            {{ __('₱:amount', ['amount' => number_format($this->orderTotal, 2)]) }}
                        </span>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-neutral-500 dark:text-zinc-400">
                        {{ __('Delivery fees are agreed with each vendor.') }}
                    </p>
                </div>
            </div>
        </aside>
    </section>
</div>
