<x-layouts::app :title="$product->name">
    @php
        $availabilityClasses = $product->stock_quantity > 0
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300'
            : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300';

        $availabilityLabel = $product->stock_quantity > 0
            ? $product->stock_quantity.' units in stock'
            : 'Currently sold out';
    @endphp

    <section class="relative overflow-hidden border-b border-emerald-900/10 bg-gradient-to-br from-emerald-500 via-emerald-600 to-green-950 text-white">
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
                    <p class="mt-5 max-w-2xl text-base leading-8 text-emerald-50/90">{{ $product->description }}</p>
                </div>

                <div class="rounded-[2rem] border border-white/15 bg-white/10 p-6 backdrop-blur-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-50/80">Market price</p>
                    <p class="mt-3 text-4xl font-semibold text-white">PHP {{ number_format((float) $product->price, 2) }}</p>

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

                {{-- TODO: Replace this dummy purchase panel with the real buying flow once ordering is implemented.
                    - Persist cart lines against the authenticated customer's real cart instead of local Alpine state.
                    - Revalidate stock server-side on every add-to-cart and buy-now action to prevent stale quantities.
                    - Route buy-now into a full checkout flow with delivery details, payment selection, and order creation.
                    - Open or create vendor-specific message threads instead of linking to the generic inbox placeholder.
                    - Add loading, disabled, retry, and error states around cart, messaging, and checkout requests.
                    - Capture conversion analytics once product-detail engagement events and cart endpoints exist.
                --}}
                <div class="mt-5 space-y-4">
                    <div
                        class="brand-panel p-6 dark:border-white/10 dark:bg-zinc-900"
                        x-data="{
                            quantity: {{ $product->stock_quantity > 0 ? 1 : 0 }},
                            maxQuantity: {{ $product->stock_quantity }},
                            clamp() {
                                if (this.maxQuantity < 1) {
                                    this.quantity = 0;
                                    return;
                                }

                                const parsed = Number.parseInt(this.quantity, 10);

                                if (Number.isNaN(parsed)) {
                                    this.quantity = 1;
                                    return;
                                }

                                this.quantity = Math.min(this.maxQuantity, Math.max(1, parsed));
                            },
                            decrement() {
                                if (this.maxQuantity < 1) {
                                    return;
                                }

                                this.quantity = Math.max(1, this.quantity - 1);
                            },
                            increment() {
                                if (this.maxQuantity < 1) {
                                    return;
                                }

                                this.quantity = Math.min(this.maxQuantity, this.quantity + 1);
                            },
                            addToCart() {
                                if (this.maxQuantity < 1) {
                                    return;
                                }

                                this.clamp();

                                $flux.toast({
                                    heading: 'Added to cart',
                                    text: 'Thank you for adding this item to your cart.',
                                    variant: 'success',
                                });
                            },
                            buyNow() {
                                if (this.maxQuantity < 1) {
                                    return;
                                }

                                this.clamp();

                                $flux.toast({
                                    heading: 'Order placement coming soon',
                                    text: 'We are still setting up checkout before orders can be placed from this page.',
                                    variant: 'warning',
                                });
                            },
                        }"
                    >
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-400">Purchase panel</p>
                        <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">Bring this stall to your cart</h2>
                        <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                            Choose how many you need, then use the placeholder purchase actions while the full cart and checkout flow is being prepared.
                        </p>

                        @if ($product->stock_quantity > 0)
                            <div class="mt-6 space-y-5">
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <label for="purchase-quantity" class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">Quantity</label>
                                        <span class="text-xs font-medium text-neutral-400 dark:text-zinc-400" x-text="`Up to ${maxQuantity} available`"></span>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <button
                                            type="button"
                                            class="flex h-11 w-11 items-center justify-center rounded-xl border border-stone-300 bg-white text-lg font-semibold text-neutral-800 transition hover:border-emerald-300 hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:bg-zinc-800 dark:text-zinc-100 dark:hover:border-emerald-500 dark:hover:text-emerald-400"
                                            x-on:click="decrement()"
                                            x-bind:disabled="maxQuantity === 0 || quantity <= 1"
                                            aria-label="Decrease quantity"
                                        >
                                            <span aria-hidden="true">-</span>
                                        </button>

                                        <input
                                            id="purchase-quantity"
                                            type="number"
                                            min="1"
                                            x-bind:max="maxQuantity"
                                            x-model.number="quantity"
                                            x-on:change="clamp()"
                                            x-on:blur="clamp()"
                                            class="h-11 w-full rounded-xl border border-stone-300 bg-white px-4 text-center text-sm font-semibold text-neutral-900 outline-hidden transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-white/10 dark:bg-zinc-800 dark:text-zinc-100"
                                        >

                                        <button
                                            type="button"
                                            class="flex h-11 w-11 items-center justify-center rounded-xl border border-stone-300 bg-white text-lg font-semibold text-neutral-800 transition hover:border-emerald-300 hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:bg-zinc-800 dark:text-zinc-100 dark:hover:border-emerald-500 dark:hover:text-emerald-400"
                                            x-on:click="increment()"
                                            x-bind:disabled="maxQuantity === 0 || quantity >= maxQuantity"
                                            aria-label="Increase quantity"
                                        >
                                            <span aria-hidden="true">+</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <button
                                        type="button"
                                        class="brand-button-primary w-full"
                                        x-on:click="addToCart()"
                                    >
                                        <i class="fa-solid fa-cart-plus text-xs"></i>
                                        Add to cart
                                    </button>

                                    <button
                                        type="button"
                                        class="brand-button-secondary w-full"
                                        x-on:click="buyNow()"
                                    >
                                        <i class="fa-solid fa-bolt text-xs"></i>
                                        Buy now
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="mt-6 flex flex-col gap-4">
                                <span class="inline-flex w-full items-center justify-center rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                                    Sold out &mdash; check back soon
                                </span>

                                <a
                                    href="{{ route('shop.home', ['category' => $product->category->id]) }}"
                                    class="brand-button-secondary w-full"
                                >
                                    Browse similar {{ $product->category->name }}
                                </a>
                            </div>
                        @endif
                    </div>

                    <a
                        href="{{ route('messages.inbox') }}"
                        class="block rounded-2xl border border-stone-200 bg-stone-50 p-5 transition hover:border-emerald-200 dark:border-white/10 dark:bg-zinc-800 dark:hover:border-emerald-500/30"
                    >
                        <span class="flex items-center gap-3 text-emerald-700 dark:text-emerald-400">
                            <i class="fa-solid fa-comments"></i>
                            <span class="font-semibold">Message vendor</span>
                        </span>
                        <p class="mt-3 text-sm text-neutral-500 dark:text-zinc-400">
                            This will open a vendor conversation once messaging is live for approved storefronts.
                        </p>
                    </a>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="flex flex-col gap-3 rounded-2xl border border-stone-200 bg-stone-50 p-4 dark:border-white/10 dark:bg-zinc-800">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-900/20 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400">
                                <i class="fa-solid fa-money-bill-wave"></i>
                            </span>
                            <p class="text-sm font-bold text-neutral-900 dark:text-zinc-100">Cash on delivery</p>
                            <p class="text-xs leading-5 text-neutral-500 dark:text-zinc-400">Pay when the order arrives.</p>
                        </div>

                        <div class="flex flex-col gap-3 rounded-2xl border border-stone-200 bg-stone-50 p-4 dark:border-white/10 dark:bg-zinc-800">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-900/20 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400">
                                <i class="fa-solid fa-wallet"></i>
                            </span>
                            <p class="text-sm font-bold text-neutral-900 dark:text-zinc-100">GCash &amp; Maya</p>
                            <p class="text-xs leading-5 text-neutral-500 dark:text-zinc-400">Digital payment options.</p>
                        </div>

                        <div class="flex flex-col gap-3 rounded-2xl border border-stone-200 bg-stone-50 p-4 dark:border-white/10 dark:bg-zinc-800">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-900/20 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400">
                                <i class="fa-solid fa-headset"></i>
                            </span>
                            <p class="text-sm font-bold text-neutral-900 dark:text-zinc-100">Vendor support</p>
                            <p class="text-xs leading-5 text-neutral-500 dark:text-zinc-400">Direct conversation with the stall.</p>
                        </div>
                    </div>
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
                        <h2 class="brand-serif text-2xl font-bold text-neutral-900 transition group-hover:text-emerald-700 dark:text-zinc-100 dark:group-hover:text-emerald-400">{{ $product->vendor->store_name }}</h2>
                    </a>
                    <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">{{ $product->vendor->store_description }}</p>
                </div>
            </div>
        </section>
    </div>
</x-layouts::app>
