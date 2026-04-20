<x-layouts::app :title="$product->name">
    @php
        $availabilityClasses = $product->stock_quantity > 0
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
            : 'border-amber-200 bg-amber-50 text-amber-700';

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
            <div class="overflow-hidden rounded-[2rem] border border-stone-200 bg-white shadow-sm">
                <div class="aspect-[5/4] overflow-hidden bg-stone-100">
                    <img
                        src="{{ $product->image }}"
                        alt="{{ $product->name }}"
                        class="h-full w-full object-cover"
                    >
                </div>
            </div>

            <div class="space-y-5 self-start xl:sticky xl:top-24">
                <div class="brand-panel p-6">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400">Listing snapshot</p>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                        <div class="rounded-[1.5rem] border border-stone-200 bg-stone-50 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400">Category</p>
                            <p class="mt-2 text-sm font-semibold text-neutral-900">{{ $product->category->name }}</p>
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

                <div class="brand-panel p-6">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400">Sold by</p>
                    <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900">{{ $product->vendor->store_name }}</h2>
                    <p class="mt-3 text-sm leading-7 text-neutral-500">{{ $product->vendor->store_description }}</p>

                    <div class="mt-5 rounded-[1.5rem] border border-stone-200 bg-stone-50 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400">Why this page feels clearer now</p>
                        <p class="mt-2 text-sm leading-6 text-neutral-600">
                            Product photos, pricing, stock, and vendor details stay visible without cramming the whole listing into one crowded panel.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-layouts::app>
