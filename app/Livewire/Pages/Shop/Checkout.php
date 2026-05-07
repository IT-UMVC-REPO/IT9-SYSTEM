<?php

namespace App\Livewire\Pages\Shop;

use App\Concerns\OrderValidationRules;
use App\Enums\AuditEvent;
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
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Checkout')]
class Checkout extends Component
{
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
        $paymentMethod = PaymentMethod::Cod;

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
                        $validationMessages["cart.{$cartItem->getKey()}"] = __('Only :amount of :product remain in stock.', [
                            'amount' => $product->unitLabel(),
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
                    throw ValidationException::withMessages($validationMessages);
                }

                $groupedItems = $resolvedItems->groupBy(
                    fn (array $resolvedItem): int => $resolvedItem['product']->vendor_id,
                );

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
                            'unit' => $resolvedItem['product']->unit->value,
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

                    SendOrderNotificationJob::dispatch(
                        orderId: $order->getKey(),
                        userId: $customer->getKey(),
                        title: 'Order placed',
                        message: 'Your order #'.$order->getKey().' has been placed and is awaiting vendor confirmation.',
                    );

                    $vendorProfile = $vendorItems->first()['product']->vendor;

                    AuditLogger::log(
                        AuditEvent::OrderPlaced,
                        "Order #{$order->id} placed by {$customer->name} with '{$vendorProfile->store_name}'. Total: ₱".number_format($vendorTotal, 2).'.',
                        $order,
                        $customer->getKey(),
                        ['vendor_id' => (int) $vendorId, 'total' => $vendorTotal, 'item_count' => $vendorItems->count()],
                    );

                    $createdOrderIds[] = $order->getKey();
                }

                $cart->cartItems()->delete();

                return [
                    'order_ids' => $createdOrderIds,
                    'payment_method' => $paymentMethod->value,
                ];
            }, attempts: 5);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\RuntimeException $exception) {
            Flux::toast(variant: 'warning', text: __('Your cart is empty. Add items before checking out.'));
            $this->redirectRoute('shop.cart', navigate: true);

            return null;
        }

        $createdOrderIds = $checkoutState['order_ids'];

        Flux::toast(variant: 'success', text: $this->placedOrderMessage(count($createdOrderIds)));

        $this->redirectRoute('shop.orders', navigate: true);

        return null;
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

    private function placedOrderMessage(int $orderCount): string
    {
        return trans_choice('{1} 1 order placed successfully.|[2,*] :count orders placed successfully.', $orderCount, [
            'count' => $orderCount,
        ]);
    }

    public function render(): View
    {
        return view('pages::shop.⚡checkout');
    }
}
