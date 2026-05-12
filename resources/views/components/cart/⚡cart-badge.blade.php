<?php

use App\Models\CartItem;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public bool $isActive = false;

    public function mount(bool $isActive = false): void
    {
        $this->isActive = $isActive;
    }

    #[On('cart-updated')]
    public function refreshBadge(): void
    {
        //
    }

    #[Computed]
    public function cartItemCount(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        return (int) CartItem::query()
            ->whereHas('cart', fn (Builder $builder): Builder => $builder->where('customer_id', auth()->id()))
            ->sum('quantity');
    }
}; ?>

<a
    href="{{ route('shop.cart') }}"
    title="{{ __('Cart') }}"
    wire:navigate
    class="relative flex h-9 w-9 items-center justify-center rounded-xl transition-transform duration-150 active:scale-90 {{ $isActive ? 'quick-action-active' : 'text-stone-500 hover:bg-stone-100 hover:text-stone-900 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white' }}"
>
    <i class="fa-solid fa-cart-shopping text-sm"></i>

    @if ($this->cartItemCount > 0)
        <span
            wire:transition
            class="suki-badge-enter absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-[var(--brand-600)] px-1.5 py-0.5 text-[10px] font-semibold leading-none text-white shadow-sm transition-all duration-300"
        >
            {{ $this->cartItemCount > 99 ? '99+' : $this->cartItemCount }}
        </span>
    @endif
</a>
