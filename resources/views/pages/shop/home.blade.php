<x-layouts::app :title="__('LocalPalengke Storefront')">
    <div class="suki-reveal">
    <section class="relative min-h-[420px] overflow-hidden border-b text-white" style="border-color: oklch(from var(--brand-900) l c h / 0.12);">
        <div class="absolute inset-0 z-0">
          
            <img
                src="{{ asset('imgs/localpalengke.webp') }}"
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
                    A brighter market floor for your next market run.
                </h1>

                <p class="brand-hero-copy mt-5 max-w-2xl text-base leading-8">
                    Browse approved stalls, scan live listings faster, and move through the catalog in a storefront that feels open, welcoming, and easy to explore.
                </p>
            </div>
        </div>
    </section>
    </div>

    @if ($popularVendors->isNotEmpty())
        <div class="border-b border-stone-200 bg-white dark:border-white/10 dark:bg-zinc-900/80">
            <div class="mx-auto max-w-[1500px] px-4 py-10 sm:px-6 lg:px-8">
                <div class="suki-reveal mb-6 flex items-end justify-between">
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

                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($popularVendors as $vendor)
                        <x-vendor-card :vendor="$vendor" :compact="true" class="suki-reveal" wire:key="popular-vendor-{{ $vendor->id }}" />
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <livewire:pages::shop.catalog-browser />
</x-layouts::app>
