<x-layouts::app :title="__('SukiMarket Storefront')">
    <section class="relative min-h-[420px] overflow-hidden border-b text-white" style="border-color: oklch(from var(--brand-900) l c h / 0.12);">
        <div class="absolute inset-0 z-0">
            {{-- Swap this to public/imgs/palengke-hero.webp when a dedicated local palengke hero photo is added. --}}
            <img
                src="{{ asset('imgs/sukimarket.webp') }}"
                alt=""
                aria-hidden="true"
                class="h-full w-full object-cover object-center"
            >
            <div class="absolute inset-0"
                style="background: linear-gradient(
                    to right,
                    var(--brand-600) 0%,
                    var(--brand-600) 30%,
                    oklch(from var(--brand-600) l c h / 0.85) 45%,
                    oklch(from var(--brand-600) l c h / 0.4) 65%,
                    oklch(from var(--brand-600) l c h / 0.1) 80%,
                    transparent 100%
                );"></div>
            <div class="absolute inset-0"
                style="background: linear-gradient(
                    to bottom,
                    rgba(0,0,0,0.18) 0%,
                    transparent 25%,
                    transparent 75%,
                    rgba(0,0,0,0.25) 100%
                );"></div>
        </div>
        <div class="pointer-events-none absolute inset-y-0 right-0 z-10 w-1/2 bg-[radial-gradient(circle_at_top_right,_rgb(255_255_255_/_0.24),_transparent_52%)]"></div>
        <div class="pointer-events-none absolute -left-20 top-10 z-10 h-56 w-56 rounded-full bg-white/12 blur-3xl"></div>
        <div class="pointer-events-none absolute bottom-0 right-[-4rem] z-10 h-72 w-72 rounded-full bg-lime-200/20 blur-3xl"></div>

        <div class="relative z-10 mx-auto max-w-[1500px] px-4 py-12 sm:px-6 lg:px-8 lg:py-20">
            <div class="max-w-2xl">
                <span class="brand-hero-copy inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em]">
                    <i class="fa-solid fa-store"></i>
                    Customer storefront
                </span>

                <h1 class="brand-serif mt-6 text-4xl font-bold leading-tight sm:text-5xl lg:text-6xl" style="text-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                    A brighter market floor for your next suki run.
                </h1>

                <p class="brand-hero-copy mt-5 max-w-2xl text-base leading-8">
                    Browse approved stalls, scan live listings faster, and move through the catalog in a storefront that feels open, welcoming, and easy to explore.
                </p>
            </div>
        </div>
    </section>

    @if ($popularVendors->isNotEmpty())
        <div class="border-b border-stone-200 bg-white dark:border-white/10 dark:bg-zinc-900/80">
            <div class="mx-auto max-w-[1500px] px-4 py-10 sm:px-6 lg:px-8">
                <div class="mb-6 flex items-end justify-between">
                    <div>
                        <p class="brand-accent-text text-xs font-semibold uppercase tracking-[0.22em]">
                            Marketplace
                        </p>
                        <h2 class="brand-serif mt-1 text-2xl font-bold text-neutral-900 dark:text-zinc-100">
                            Popular vendors this week
                        </h2>
                    </div>
                    <a href="{{ route('shop.vendors') }}"
                        class="brand-accent-text-strong hidden text-sm font-medium hover:underline sm:block">
                        Browse all &rarr;
                    </a>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    @foreach ($popularVendors as $vendor)
                        <div class="brand-card-hover group relative flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-stone-50 p-4 transition hover:shadow-md dark:border-white/10 dark:bg-zinc-900">
                            <div class="absolute right-3 top-3 z-10">
                                <livewire:vendor.follow-button :vendor="$vendor" :key="'popular-vendor-follow-'.$vendor->id" />
                            </div>
                            <a href="{{ route('shop.vendors.show', $vendor) }}" class="flex flex-1 flex-col">
                                <div class="brand-soft-surface mb-3 flex h-12 w-12 items-center justify-center rounded-xl text-base font-bold">
                                    {{ strtoupper(substr($vendor->store_name, 0, 2)) }}
                                </div>
                                <p class="line-clamp-1 pr-6 text-sm font-semibold leading-snug text-neutral-900 dark:text-zinc-100">
                                    {{ $vendor->store_name }}
                                </p>
                                <p class="mt-1 flex-1 line-clamp-2 text-xs leading-5 text-neutral-400 dark:text-zinc-400">
                                    {{ $vendor->store_description }}
                                </p>
                                <div class="mt-3 flex items-center gap-1.5 border-t border-stone-200 pt-3 dark:border-white/10">
                                    <span class="brand-accent-text-strong text-xs font-semibold">
                                        {{ $vendor->active_products_count }}
                                    </span>
                                    <span class="text-xs text-stone-400 dark:text-zinc-400">
                                        {{ Str::plural('listing', $vendor->active_products_count) }}
                                    </span>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <livewire:pages::shop.catalog-browser />
</x-layouts::app>
