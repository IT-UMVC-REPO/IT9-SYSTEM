<x-layouts::app :title="$product->name">
    @php
        $canPurchaseProduct = auth()->user()?->effectiveMarketplaceRole()->value !== 'admin' && ! ($isOwnProduct ?? false);

        $availabilityClasses = $product->stock_quantity > 0
            ? 'brand-soft-surface border'
            : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300';

        $availabilityLabel = $product->stock_quantity > 0
            ? $product->stock_quantity.' units in stock'
            : 'Currently sold out';
    @endphp

    @if ($isOwnProduct ?? false)
        <div class="mx-auto max-w-[1500px] px-4 pt-6 sm:px-6 lg:px-8">
            <div class="brand-soft-surface rounded-[1.75rem] border px-5 py-4 text-sm font-semibold text-[var(--brand-800)] dark:text-[var(--brand-200)]">
                <i class="fa-solid fa-box-open mr-2"></i>
                {{ __("You're viewing your own listing. Purchase controls are hidden for you.") }}
            </div>
        </div>
    @endif

    <section class="relative overflow-hidden border-b text-white" style="border-color: oklch(from var(--brand-900) l c h / 0.12); background: linear-gradient(135deg, var(--brand-500) 0%, var(--brand-600) 55%, color-mix(in oklab, var(--brand-950) 80%, black) 100%);">
        <div class="pointer-events-none absolute inset-y-0 right-0 w-1/2 bg-[radial-gradient(circle_at_top_right,_rgb(255_255_255_/_0.18),_transparent_52%)]"></div>
        <div class="pointer-events-none absolute -left-24 top-12 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>

        <div class="mx-auto max-w-[1500px] px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
            <a
                href="{{ route('shop.home') }}"
                class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/15"
            >
                <i class="fa-solid fa-arrow-left text-xs"></i>
                Back to storefront
            </a>

            <div class="mt-8 grid gap-8 xl:grid-cols-[minmax(0,1.2fr)_minmax(22rem,0.8fr)] xl:items-end">
                <div class="max-w-3xl">
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white">
                            {{ $product->category->name }}
                        </span>
                        <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white">
                            {{ $product->vendor->store_name }}
                        </span>
                    </div>

                    <h1 class="brand-serif mt-5 text-4xl font-bold leading-tight sm:text-5xl lg:text-6xl">{{ $product->name }}</h1>
                    <p class="brand-hero-copy mt-5 max-w-2xl text-base leading-8">{{ $product->description }}</p>
                </div>

                <div class="rounded-[2rem] border border-white/15 bg-white/10 p-6 backdrop-blur-sm">
                    <p class="brand-hero-note text-[11px] font-semibold uppercase tracking-[0.22em]">Market price</p>
                    <p class="mt-3 text-4xl font-semibold text-white">₱{{ number_format((float) $product->price, 2) }}</p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <span class="rounded-full border border-white/15 bg-white/12 px-3 py-1.5 text-xs font-semibold text-white">
                            <i class="fa-solid fa-store mr-2"></i>
                            {{ $product->vendor->store_name }}
                        </span>
                        <span class="rounded-full border border-white/15 bg-white/12 px-3 py-1.5 text-xs font-semibold text-white">
                            <i class="fa-solid fa-box-open mr-2"></i>
                            {{ $availabilityLabel }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
        <section class="grid gap-8 xl:grid-cols-[minmax(0,1.1fr)_minmax(22rem,0.9fr)]">
            <div class="self-start">
                <div class="overflow-hidden rounded-[2rem] border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-800">
                    <div class="aspect-[5/4] overflow-hidden bg-stone-100 dark:bg-zinc-800">
                        <img
                            src="{{ $product->image }}"
                            alt="{{ $product->name }}"
                            onerror="this.src='https://placehold.co/640x640/e7e5e4/9ca3af?text=No+Image'"
                            class="h-full w-full object-cover"
                        >
                    </div>
                </div>

                <div class="mt-5 space-y-4">
                    @if ($canPurchaseProduct)
                        <livewire:cart.add-to-cart :product="$product" />
                    @elseif ($isOwnProduct ?? false)
                        <div class="brand-soft-surface rounded-[1.75rem] border p-5">
                            <span class="text-sm font-semibold text-[var(--brand-800)] dark:text-[var(--brand-200)]">
                                <i class="fa-solid fa-circle-check mr-2"></i>
                                {{ __('Your listing') }}
                            </span>
                            <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                                {{ __('Customers see the purchase panel here. You can manage this product from your vendor dashboard.') }}
                            </p>
                        </div>
                    @endif

                    @unless ($isOwnProduct ?? false)
                        <a
                            href="{{ route('messages.conversation', ['conversationReference' => $product->vendor->user->id]) }}"
                            wire:navigate
                            class="brand-card-hover block rounded-2xl border border-stone-200 bg-stone-50 p-5 transition dark:border-white/10 dark:bg-zinc-800"
                        >
                            <span class="brand-accent-text-strong flex items-center gap-3">
                                <i class="fa-solid fa-comments"></i>
                                <span class="font-semibold">{{ __('Message vendor') }}</span>
                            </span>
                            <p class="mt-3 text-sm text-neutral-500 dark:text-zinc-400">
                                {{ __('Ask about availability, delivery timing, or anything else before you place the order.') }}
                            </p>
                        </a>
                    @endunless

                </div>
            </div>

            <div class="self-start space-y-5 xl:sticky xl:top-24">
                <div class="brand-panel p-6 dark:border-white/10 dark:bg-zinc-900">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-400">Listing snapshot</p>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                        <div class="rounded-[1.5rem] border border-stone-200 bg-stone-50 p-4 dark:border-white/10 dark:bg-zinc-800">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-400">Category</p>
                            <p class="mt-2 text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ $product->category->name }}</p>
                        </div>

                        <div class="rounded-[1.5rem] border p-4 {{ $availabilityClasses }}">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em]">Availability</p>
                            <p class="mt-2 text-sm font-semibold">{{ $availabilityLabel }}</p>
                        </div>
                    </div>

                    <a
                        href="{{ route('shop.home', ['category' => $product->category->id]) }}"
                        class="brand-button-secondary mt-5 w-full"
                    >
                        Explore more {{ strtolower($product->category->name) }}
                    </a>
                </div>

                <div class="brand-panel p-6 dark:border-white/10 dark:bg-zinc-900">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-400">Sold by</p>
                    <a href="{{ route('shop.vendors.show', $product->vendor) }}" class="group mt-3 block">
                        <h2 class="brand-group-hover-text brand-serif text-2xl font-bold text-neutral-900 transition dark:text-zinc-100">{{ $product->vendor->store_name }}</h2>
                    </a>
                    <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">{{ $product->vendor->store_description }}</p>
                </div>
            </div>
        </section>
    </div>
</x-layouts::app>
