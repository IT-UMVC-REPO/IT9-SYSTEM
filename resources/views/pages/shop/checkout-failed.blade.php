<x-layouts::app :title="__('Payment not completed')">
    <div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
        <div class="brand-panel mx-auto max-w-3xl px-6 py-14 text-center">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-stone-100 text-amber-500 dark:bg-zinc-800">
                <i class="fa-solid fa-triangle-exclamation text-2xl"></i>
            </span>

            <h1 class="brand-serif mt-6 text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Payment was not completed') }}</h1>
            <p class="mt-3 text-base leading-8 text-neutral-500 dark:text-zinc-400">
                {{ __('You can review your checkout details and try again when you are ready.') }}
            </p>

            @if ($orderId)
                <p class="mt-5 text-sm font-medium text-neutral-500 dark:text-zinc-400">
                    {{ __('Pending order reference: #:id', ['id' => str_pad((string) $orderId, 6, '0', STR_PAD_LEFT)]) }}
                </p>
            @endif

            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <a href="{{ route('shop.checkout') }}" class="brand-button-primary">
                    {{ __('Try again') }}
                </a>

                <a href="{{ route('shop.cart') }}" class="brand-button-secondary">
                    {{ __('Back to cart') }}
                </a>
            </div>
        </div>
    </div>
</x-layouts::app>
