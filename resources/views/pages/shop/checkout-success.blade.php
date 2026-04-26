<x-layouts::app :title="__('Order placed')">
    <div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
        <div class="brand-panel mx-auto max-w-3xl px-6 py-14 text-center">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-stone-100 text-[var(--brand-600)] dark:bg-zinc-800">
                <i class="fa-solid fa-check text-2xl"></i>
            </span>

            <h1 class="brand-serif mt-6 text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Order placed!') }}</h1>
            <p class="mt-3 text-base leading-8 text-neutral-500 dark:text-zinc-400">
                {{ __('Your payment handoff is complete. We are confirming your order and notifying the vendor now.') }}
            </p>

            <div class="mt-8 rounded-[1.5rem] border border-stone-200 bg-stone-50 px-5 py-4 dark:border-white/10 dark:bg-zinc-800">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Order reference') }}</p>
                <p class="mt-2 text-2xl font-semibold text-neutral-900 dark:text-zinc-100">#{{ str_pad((string) $order->getKey(), 6, '0', STR_PAD_LEFT) }}</p>
                <p class="mt-3 text-sm text-neutral-500 dark:text-zinc-400">{{ __('Estimated handling begins once the vendor confirms your order.') }}</p>
            </div>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <a href="{{ route('shop.orders.show', ['orderReference' => $order->getKey()]) }}" class="brand-button-primary">
                    {{ __('Track my order') }}
                </a>

                <a href="{{ route('shop.home') }}" class="brand-button-secondary">
                    {{ __('Continue shopping') }}
                </a>
            </div>
        </div>
    </div>
</x-layouts::app>
