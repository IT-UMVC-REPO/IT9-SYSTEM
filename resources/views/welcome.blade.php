<x-layouts::app.header :title="'Fresh from the Palengke'">
    @php
        $marketItems = collect([
            ['icon' => 'fa-solid fa-carrot', 'label' => 'Vegetables'],
            ['icon' => 'fa-solid fa-fish', 'label' => 'Seafood'],
            ['icon' => 'fa-solid fa-drumstick-bite', 'label' => 'Meat'],
            ['icon' => 'fa-solid fa-apple-whole', 'label' => 'Fruits'],
            ['icon' => 'fa-solid fa-seedling', 'label' => 'Herbs'],
            ['icon' => 'fa-solid fa-wheat-awn', 'label' => 'Grains'],
        ]);

        $marketStream = $marketItems->concat($marketItems);
        $portalHomeRoute = auth()->check() ? route(auth()->user()->homeRoute()) : null;
        $sellerPortalRoute = auth()->check()
            ? route(match (auth()->user()->effectiveMarketplaceRole()) {
                \App\Enums\UserRole::Admin => 'admin.dashboard',
                \App\Enums\UserRole::Vendor => 'vendor.dashboard',
                default => 'vendor.registration',
            })
            : route('register');
        $heroActions = auth()->check()
            ? [
                ['label' => 'Go to my dashboard', 'href' => $portalHomeRoute, 'class' => 'brand-button-primary', 'icon' => 'fa-solid fa-arrow-right text-xs'],
            ]
            : [
                ['label' => 'Create a customer account', 'href' => route('register'), 'class' => 'brand-button-primary', 'icon' => 'fa-solid fa-arrow-right text-xs'],
                ['label' => 'Sign in to continue', 'href' => route('login'), 'class' => 'brand-button-secondary', 'icon' => null],
            ];
        $marketBadges = [
            ['icon' => 'fa-solid fa-circle-check', 'label' => 'Fresh daily listings'],
            ['icon' => 'fa-solid fa-user-shield', 'label' => 'Verified vendor storefronts'],
            ['icon' => 'fa-solid fa-leaf', 'label' => 'Market-first design'],
        ];
        $ctaActions = auth()->check()
            ? [
                ['label' => 'Go to your dashboard', 'href' => $portalHomeRoute, 'class' => 'brand-button-primary', 'icon' => 'fa-solid fa-arrow-right text-xs'],
            ]
            : [
                ['label' => 'Create a free account', 'href' => route('register'), 'class' => 'brand-button-primary', 'icon' => 'fa-solid fa-arrow-right text-xs'],
                ['label' => 'Sign in', 'href' => route('login'), 'class' => 'inline-flex items-center justify-center rounded-xl border border-neutral-700 px-5 py-3 text-sm font-semibold text-neutral-300 transition hover:border-neutral-500 hover:text-white', 'icon' => null],
            ];
        $footerLinks = [
            ['label' => 'Log in', 'href' => route('login')],
            ['label' => 'Register', 'href' => route('register')],
        ];
    @endphp

    <div class="relative overflow-x-hidden dark:text-zinc-100">
        <div class="brand-glow-orb pointer-events-none absolute -right-16 top-0 h-72 w-72 rounded-full blur-3xl"></div>
        {{-- <div class="pointer-events-none absolute -left-10 bottom-0 h-72 w-72 rounded-full bg-amber-100 blur-3xl dark:bg-amber-900/20"></div> --}}

        <section class="relative overflow-hidden py-2 lg:py-8">
            <div class="mx-auto grid max-w-7xl items-center gap-16 px-4 sm:px-6 lg:grid-cols-2">
                <div class="max-w-2xl">
                    <h1 class="brand-serif mt-6 text-5xl font-bold leading-tight tracking-tight text-neutral-900 dark:text-zinc-100 sm:text-6xl">
                        Fresh from the palengke,
                        <span class="brand-accent-text">with your suki still in view.</span>
                    </h1>

                    <p class="mt-6 max-w-xl text-lg leading-8 text-neutral-500 dark:text-zinc-400">
                        SukiMarket brings the warmth of the Filipino wet market online with recognizable stalls, fresh listings, and a browsing experience that keeps your favorite vendors front and center.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        @foreach ($heroActions as $action)
                            <a href="{{ $action['href'] }}" class="{{ $action['class'] }}">
                                {{ $action['label'] }}
                                @if ($action['icon'])
                                    <i class="{{ $action['icon'] }}"></i>
                                @endif
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-10 flex flex-wrap items-center gap-3 border-t border-stone-200 pt-8 dark:border-white/10">
                        @foreach ($marketBadges as $badge)
                            <span class="brand-badge"><i class="brand-accent-text {{ $badge['icon'] }}"></i> {{ $badge['label'] }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="relative hidden h-130 items-center justify-center overflow-hidden lg:flex">
                    @if ($featuredVendor)
                        <div class="brand-floating-card brand-float-a absolute left-0 top-8 w-72">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <span class="brand-soft-surface flex h-11 w-11 items-center justify-center rounded-2xl">
                                        <i class="fa-solid fa-store text-lg"></i>
                                    </span>
                                    <div>
                                        <p class="brand-accent-text-strong text-xs font-semibold uppercase tracking-[0.22em]">Featured market stall</p>
                                        <h2 class="mt-1 text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ $featuredVendor->store_name }}</h2>
                                    </div>
                                </div>
                                <span class="brand-soft-surface rounded-full px-3 py-1 text-[11px] font-semibold">Verified</span>
                            </div>

                            <p class="mt-4 text-sm leading-6 text-neutral-500 dark:text-zinc-400">
                                {{ \Illuminate\Support\Str::limit($featuredVendor->store_description, 120) }}
                            </p>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-stone-100 bg-stone-50 p-4 dark:border-white/10 dark:bg-zinc-950/60">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-400">Active listings</p>
                                    <p class="mt-2 text-2xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $featuredVendor->active_products_count }}</p>
                                </div>
                                <div class="rounded-2xl border border-stone-100 bg-stone-50 p-4 dark:border-white/10 dark:bg-zinc-950/60">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-400">Store owner</p>
                                    <p class="mt-2 text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ $featuredVendor->user?->name ?? 'Marketplace vendor' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="brand-floating-card brand-float-b absolute bottom-8 right-0 w-72">
                            <div class="flex items-center gap-3">
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                                    <i class="fa-solid fa-basket-shopping text-lg"></i>
                                </span>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">Today&rsquo;s market picks</p>
                                    <h2 class="mt-1 text-sm font-semibold text-neutral-900 dark:text-zinc-100">From {{ $featuredVendor->store_name }}</h2>
                                </div>
                            </div>

                            <ul class="mt-4 space-y-3 text-sm">
                                @foreach ($featuredProducts as $product)
                                    <li class="flex items-center justify-between gap-3 rounded-2xl border border-stone-100 bg-stone-50 px-4 py-3 dark:border-white/10 dark:bg-zinc-950/60">
                                        <div>
                                            <p class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $product->name }}</p>
                                            <p class="mt-1 text-xs text-neutral-400 dark:text-zinc-400">{{ $product->category?->name ?? 'Marketplace listing' }}</p>
                                        </div>
                                        <span class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">₱{{ number_format((float) $product->price, 2) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <div class="brand-floating-card brand-float-a absolute left-0 top-8 w-72">
                            <div class="flex items-center gap-3">
                                <span class="brand-soft-surface flex h-11 w-11 items-center justify-center rounded-2xl">
                                    <i class="fa-solid fa-store text-lg"></i>
                                </span>
                                <div>
                                    <p class="brand-accent-text-strong text-xs font-semibold uppercase tracking-[0.22em]">Marketplace spotlight</p>
                                    <h2 class="mt-1 text-sm font-semibold text-neutral-900 dark:text-zinc-100">Featured stalls will appear here as listings go live.</h2>
                                </div>
                            </div>
                            <p class="mt-4 text-sm leading-6 text-neutral-500 dark:text-zinc-400">
                                Once the marketplace has live storefronts, this space will highlight a real vendor and real products from the catalog.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <div class="overflow-hidden border-y border-stone-200 bg-white py-4 dark:border-white/10 dark:bg-zinc-900/80">
            <div class="overflow-hidden">
                <div class="brand-marquee-track flex min-w-full w-max gap-0">
                    @foreach ($marketStream as $item)
                        <span class="flex shrink-0 items-center gap-3 px-6 text-sm font-medium text-neutral-500 dark:text-zinc-400">
                            <i class="{{ $item['icon'] }} brand-accent-text"></i>
                            <span>{{ $item['label'] }}</span>
                            <span class="ml-3 text-stone-300 dark:text-zinc-700">/</span>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        <section id="features" class="py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="text-center">
                    <p class="brand-accent-text text-sm font-semibold uppercase tracking-[0.28em]">What makes it special</p>
                    <h2 class="brand-serif mt-3 text-4xl font-bold text-neutral-900 dark:text-zinc-100 sm:text-5xl">A digital palengke with a familiar feel.</h2>
                    <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-neutral-500 dark:text-zinc-400">
                        Every section is meant to feel closer to a real market stall: easy to scan, easy to trust, and easy to come back to.
                    </p>
                </div>

                <div class="mt-16 grid gap-8 lg:grid-cols-3">
                    <article class="group relative overflow-hidden rounded-3xl border border-stone-200 bg-white p-8 shadow-sm transition hover:-translate-y-1 hover:shadow-md dark:border-white/10 dark:bg-zinc-900">
                        <div class="pointer-events-none absolute inset-0 bg-cover bg-center opacity-15 transition duration-300 group-hover:scale-105 group-hover:opacity-20"
                            style="background-image: url('{{ asset('imgs/sukifruits.webp') }}')">
                        </div>
                        <div class="relative z-10">
                            <div class="brand-soft-surface flex h-14 w-14 items-center justify-center rounded-2xl transition group-hover:scale-105">
                                <i class="fa-solid fa-store text-xl"></i>
                            </div>
                            <p class="brand-accent-text mt-5 text-xs font-semibold uppercase tracking-[0.22em]">Fresh finds</p>
                            <h3 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">Browse the market with ease</h3>
                            <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">Explore produce, seafood, meat, and pantry staples through a clean storefront built around how people already shop in the palengke.</p>
                        </div>
                    </article>

                    <article class="group relative overflow-hidden rounded-3xl border border-stone-200 bg-white p-8 shadow-sm transition hover:-translate-y-1 hover:shadow-md dark:border-white/10 dark:bg-zinc-900">
                        <div class="pointer-events-none absolute inset-0 bg-cover bg-center opacity-15 transition duration-300 group-hover:scale-105 group-hover:opacity-20"
                            style="background-image: url('{{ asset('imgs/sukivendor.webp') }}')">
                        </div>
                        <div class="relative z-10">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 transition group-hover:scale-105 dark:bg-amber-500/10 dark:text-amber-300">
                                <i class="fa-solid fa-shop text-xl"></i>
                            </div>
                            <p class="mt-5 text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">Trusted stalls</p>
                            <h3 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">Know the vendor behind the listing</h3>
                            <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">Each storefront carries its own name, identity, and product selection so shoppers can recognize the people behind the goods they browse.</p>
                        </div>
                    </article>

                    <article class="group relative overflow-hidden rounded-3xl border border-stone-200 bg-white p-8 shadow-sm transition hover:-translate-y-1 hover:shadow-md dark:border-white/10 dark:bg-zinc-900">
                        <div class="pointer-events-none absolute inset-0 bg-cover bg-center opacity-15 transition duration-300 group-hover:scale-105 group-hover:opacity-20"
                            style="background-image: url('{{ asset('imgs/sukisda.png') }}')">
                        </div>
                        <div class="relative z-10">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 transition group-hover:scale-105 dark:bg-rose-500/10 dark:text-rose-300">
                                <i class="fa-solid fa-heart text-xl"></i>
                            </div>
                            <p class="mt-5 text-xs font-semibold uppercase tracking-[0.22em] text-rose-600">Suki spirit</p>
                            <h3 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">Keep the market relationship alive</h3>
                            <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">SukiMarket is built around familiarity and trust, turning neighborhood market habits into a more organized digital experience.</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="how-it-works" class="border-y border-stone-200 bg-white py-24 dark:border-white/10 dark:bg-zinc-900/80">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <div class="text-center">
                    <p class="brand-accent-text text-sm font-semibold uppercase tracking-[0.28em]">How it works</p>
                    <h2 class="brand-serif mt-3 text-4xl font-bold text-neutral-900 dark:text-zinc-100 sm:text-5xl">Simple, familiar, and market-inspired.</h2>
                </div>

                <div class="mt-16 grid gap-8 md:grid-cols-3">
                    <article class="brand-panel p-7">
                        <div class="flex items-start gap-4">
                            <span class="brand-soft-surface flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold">01</span>
                            <div>
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-stone-100 text-neutral-600 dark:bg-zinc-800 dark:text-zinc-300"><i class="fa-solid fa-user-plus"></i></span>
                                <h3 class="brand-serif mt-4 text-xl font-bold text-neutral-900 dark:text-zinc-100">Browse market categories</h3>
                                <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">Move through vegetables, seafood, fruits, and everyday staples in one organized storefront built for easy discovery.</p>
                            </div>
                        </div>
                    </article>

                    <article class="brand-panel p-7">
                        <div class="flex items-start gap-4">
                            <span class="brand-soft-surface flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold">02</span>
                            <div>
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-stone-100 text-neutral-600 dark:bg-zinc-800 dark:text-zinc-300"><i class="fa-solid fa-magnifying-glass"></i></span>
                                <h3 class="brand-serif mt-4 text-xl font-bold text-neutral-900 dark:text-zinc-100">Check each stall closely</h3>
                                <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">Open product pages, compare prices, review stock, and look at each vendor&apos;s storefront details before choosing where to shop.</p>
                            </div>
                        </div>
                    </article>

                    <article class="brand-panel p-7">
                        <div class="flex items-start gap-4">
                            <span class="brand-soft-surface flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold">03</span>
                            <div>
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-stone-100 text-neutral-600 dark:bg-zinc-800 dark:text-zinc-300"><i class="fa-solid fa-list-check"></i></span>
                                <h3 class="brand-serif mt-4 text-xl font-bold text-neutral-900 dark:text-zinc-100">Build your go-to routine</h3>
                                <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">Come back to the same trusted stalls and enjoy a marketplace experience shaped around familiarity, clarity, and convenience.</p>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="portals" class="py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="text-center">
                    <p class="brand-accent-text text-sm font-semibold uppercase tracking-[0.28em]">Built for the marketplace</p>
                    <h2 class="brand-serif mt-3 text-4xl font-bold text-neutral-900 dark:text-zinc-100 sm:text-5xl">Two tailored portals, one shared market.</h2>
                </div>

                <div class="mt-14 grid gap-6 xl:grid-cols-2">
                    <article class="brand-gradient-card relative overflow-hidden rounded-4xl p-10 text-white shadow-lg">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 text-xl"><i class="fa-solid fa-basket-shopping"></i></span>
                        <h3 class="brand-serif mt-6 text-3xl font-bold">Customer dashboard</h3>
                        <p class="brand-hero-copy mt-4 text-sm leading-7">A shopper portal for browsing fresh listings, keeping favorite stalls nearby, and following upcoming orders.</p>
                        <a href="{{ auth()->check() ? $portalHomeRoute : route('register') }}" class="brand-accent-text-strong mt-8 inline-flex items-center rounded-xl bg-white px-5 py-3 text-sm font-semibold transition hover:bg-stone-100 dark:bg-zinc-950/90 dark:hover:bg-zinc-900">
                            {{ auth()->check() ? 'Open my dashboard' : 'Create a customer account' }}
                        </a>
                    </article>

                    <article class="relative overflow-hidden rounded-4xl bg-linear-to-br from-amber-500 to-orange-600 p-10 text-white shadow-lg">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 text-xl"><i class="fa-solid fa-shop"></i></span>
                        <h3 class="brand-serif mt-6 text-3xl font-bold">Vendor workspace</h3>
                        <p class="mt-4 text-sm leading-7 text-amber-100">A seller portal built around onboarding, catalog management, order handling, and sales visibility.</p>
                        <a href="{{ $sellerPortalRoute }}" class="mt-8 inline-flex items-center rounded-xl bg-white px-5 py-3 text-sm font-semibold text-amber-700 transition hover:bg-stone-100 dark:bg-zinc-950/90 dark:text-amber-300 dark:hover:bg-zinc-900">
                            {{ auth()->check() ? 'Explore seller pages' : 'Start with an account' }}
                        </a>
                    </article>

                </div>
            </div>
        </section>

        <section class="bg-neutral-950 py-24 dark:bg-zinc-900">
            <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
                <h2 class="brand-serif mt-6 text-4xl font-bold text-white sm:text-5xl">Explore the customer storefront today.</h2>
                <p class="mt-5 text-lg leading-8 text-neutral-300">
                    Step into a cleaner digital palengke with real storefronts, strong vendor identity, and a warm market-first browsing experience.
                </p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                    @foreach ($ctaActions as $action)
                        <a href="{{ $action['href'] }}" class="{{ $action['class'] }}">
                            {{ $action['label'] }}
                            @if ($action['icon'])
                                <i class="{{ $action['icon'] }}"></i>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <footer class="border-t border-stone-200 bg-stone-50 py-12 dark:border-white/10 dark:bg-zinc-900/80">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                    <div class="flex items-center gap-3">
                       <x-app-logo class="h-8 w-auto" />
                    </div>

                    <p class="max-w-xl text-center text-xs leading-6 text-neutral-400 dark:text-zinc-400">
                        A digital marketplace inspired by the Filipino wet market experience and shaped around local trust, freshness, and familiar buying habits.
                    </p>

                    <div class="flex items-center gap-5 text-xs text-neutral-400 dark:text-zinc-400">
                        @foreach ($footerLinks as $link)
                            <a href="{{ $link['href'] }}" class="transition hover:text-neutral-700 dark:hover:text-zinc-100">{{ $link['label'] }}</a>
                        @endforeach
                    </div>
                </div>

                <div class="mt-8 border-t border-stone-200 pt-6 text-center text-xs text-neutral-400 dark:border-white/10 dark:text-zinc-400">
                    &copy; {{ date('Y') }} SukiMarket. All rights reserved.
                </div>
            </div>
        </footer>
    </div>
</x-layouts::app.header>
