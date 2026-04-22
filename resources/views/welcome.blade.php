<!DOCTYPE html>
<html class="overflow-x-hidden" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'Fresh from the Palengke'])
    </head>
    <body class="brand-shell min-h-screen text-neutral-800 antialiased">
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
        @endphp

        <header class="sticky top-0 z-50 border-b border-stone-200 bg-stone-50/90 backdrop-blur-md">
            <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <x-app-logo href="{{ route('home') }}" />

                <ul class="hidden items-center gap-8 text-sm font-medium text-neutral-500 md:flex">
                    <li><a href="#features" class="transition hover:text-emerald-700">Features</a></li>
                    <li><a href="#how-it-works" class="transition hover:text-emerald-700">How it works</a></li>
                    <li><a href="#portals" class="transition hover:text-emerald-700">Portals</a></li>
                </ul>

                <div class="hidden items-center gap-3 md:flex">
                    @auth
                        <a href="{{ $portalHomeRoute }}" class="brand-button-primary">Open dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium text-neutral-600 transition hover:text-neutral-900">Log in</a>
                        <a href="{{ route('register') }}" class="brand-button-primary">Get started</a>
                    @endauth
                </div>

                <details class="relative md:hidden">
                    <summary class="flex h-11 w-11 cursor-pointer items-center justify-center rounded-2xl border border-stone-200 bg-white text-neutral-600 shadow-sm marker:hidden">
                        <i class="fa-solid fa-bars text-sm"></i>
                    </summary>
                    <div class="absolute right-0 mt-3 w-64 rounded-3xl border border-stone-200 bg-white p-4 shadow-xl">
                        <div class="grid gap-2 text-sm font-medium text-neutral-600">
                            <a href="#features" class="rounded-2xl px-3 py-2 transition hover:bg-emerald-50 hover:text-emerald-700">Features</a>
                            <a href="#how-it-works" class="rounded-2xl px-3 py-2 transition hover:bg-emerald-50 hover:text-emerald-700">How it works</a>
                            <a href="#portals" class="rounded-2xl px-3 py-2 transition hover:bg-emerald-50 hover:text-emerald-700">Portals</a>
                        </div>
                        <div class="mt-4 grid gap-2 border-t border-stone-200 pt-4">
                            @auth
                                <a href="{{ $portalHomeRoute }}" class="brand-button-primary w-full">Open dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="brand-button-secondary w-full">Log in</a>
                                <a href="{{ route('register') }}" class="brand-button-primary w-full">Get started</a>
                            @endauth
                        </div>
                    </div>
                </details>
            </nav>
        </header>

        <div class="pointer-events-none absolute -right-16 top-0 h-72 w-72 rounded-full bg-emerald-100 blur-3xl"></div>
        <div class="pointer-events-none absolute -left-10 bottom-0 h-72 w-72 rounded-full bg-amber-100 blur-3xl"></div>

        <section class="relative overflow-hidden py-2 lg:py-8">
            <div class="mx-auto grid max-w-7xl items-center gap-16 px-4 sm:px-6 lg:grid-cols-2">
                <div class="max-w-2xl">
                    <h1 class="brand-serif mt-6 text-5xl font-bold leading-tight tracking-tight text-neutral-900 sm:text-6xl">
                        Fresh from the palengke,
                        <span class="text-emerald-600">with your suki still in view.</span>
                    </h1>

                    <p class="mt-6 max-w-xl text-lg leading-8 text-neutral-500">
                        SukiMarket brings the warmth of the Filipino wet market online with recognizable stalls, fresh listings, and a browsing experience that keeps your favorite vendors front and center.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        @auth
                            <a href="{{ $portalHomeRoute }}" class="brand-button-primary">
                                Go to my dashboard
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </a>
                        @else
                            <a href="{{ route('register') }}" class="brand-button-primary">
                                Create a customer account
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </a>
                            <a href="{{ route('login') }}" class="brand-button-secondary">Sign in to continue</a>
                        @endauth
                    </div>

                    <div class="mt-10 flex flex-wrap items-center gap-3 border-t border-stone-200 pt-8">
                        <span class="brand-badge"><i class="fa-solid fa-circle-check text-emerald-600"></i> Fresh daily listings</span>
                        <span class="brand-badge"><i class="fa-solid fa-user-shield text-emerald-600"></i> Verified vendor storefronts</span>
                        <span class="brand-badge"><i class="fa-solid fa-leaf text-emerald-600"></i> Market-first design</span>
                    </div>
                </div>

                <div class="relative hidden h-130 items-center justify-center lg:flex">
                    @if ($featuredVendor)
                        <div class="brand-floating-card brand-float-a absolute left-0 top-8 w-72">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                                        <i class="fa-solid fa-store text-lg"></i>
                                    </span>
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700">Featured market stall</p>
                                        <h2 class="mt-1 text-sm font-semibold text-neutral-900">{{ $featuredVendor->store_name }}</h2>
                                    </div>
                                </div>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700">Verified</span>
                            </div>

                            <p class="mt-4 text-sm leading-6 text-neutral-500">
                                {{ \Illuminate\Support\Str::limit($featuredVendor->store_description, 120) }}
                            </p>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-stone-100 bg-stone-50 p-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400">Active listings</p>
                                    <p class="mt-2 text-2xl font-semibold text-neutral-900">{{ $featuredVendor->active_products_count }}</p>
                                </div>
                                <div class="rounded-2xl border border-stone-100 bg-stone-50 p-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400">Store owner</p>
                                    <p class="mt-2 text-sm font-semibold text-neutral-900">{{ $featuredVendor->user?->name ?? 'Marketplace vendor' }}</p>
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
                                    <h2 class="mt-1 text-sm font-semibold text-neutral-900">From {{ $featuredVendor->store_name }}</h2>
                                </div>
                            </div>

                            <ul class="mt-4 space-y-3 text-sm">
                                @foreach ($featuredProducts as $product)
                                    <li class="flex items-center justify-between gap-3 rounded-2xl border border-stone-100 bg-stone-50 px-4 py-3">
                                        <div>
                                            <p class="font-semibold text-neutral-900">{{ $product->name }}</p>
                                            <p class="mt-1 text-xs text-neutral-400">{{ $product->category?->name ?? 'Marketplace listing' }}</p>
                                        </div>
                                        <span class="text-sm font-semibold text-neutral-900">PHP {{ number_format((float) $product->price, 2) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <div class="brand-floating-card brand-float-a absolute left-0 top-8 w-72">
                            <div class="flex items-center gap-3">
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                                    <i class="fa-solid fa-store text-lg"></i>
                                </span>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700">Marketplace spotlight</p>
                                    <h2 class="mt-1 text-sm font-semibold text-neutral-900">Featured stalls will appear here as listings go live.</h2>
                                </div>
                            </div>
                            <p class="mt-4 text-sm leading-6 text-neutral-500">
                                Once the marketplace has live storefronts, this space will highlight a real vendor and real products from the catalog.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <div class="overflow-hidden border-y border-stone-200 bg-white py-4">
            <div class="brand-marquee-track flex w-max gap-0">
                @foreach ($marketStream as $item)
                    <span class="flex shrink-0 items-center gap-3 px-6 text-sm font-medium text-neutral-500">
                        <i class="{{ $item['icon'] }} text-emerald-600"></i>
                        <span>{{ $item['label'] }}</span>
                        <span class="ml-3 text-stone-300">/</span>
                    </span>
                @endforeach
            </div>
        </div>

        <section id="features" class="py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-[0.28em] text-emerald-600">What makes it special</p>
                    <h2 class="brand-serif mt-3 text-4xl font-bold text-neutral-900 sm:text-5xl">A digital palengke with a familiar feel.</h2>
                    <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-neutral-500">
                        Every section is meant to feel closer to a real market stall: easy to scan, easy to trust, and easy to come back to.
                    </p>
                </div>

                <div class="mt-16 grid gap-8 lg:grid-cols-3">
                    <article class="group relative overflow-hidden rounded-3xl border border-stone-200 bg-white p-8 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                        <div class="pointer-events-none absolute inset-0 bg-cover bg-center opacity-15 transition duration-300 group-hover:scale-105 group-hover:opacity-20"
                            style="background-image: url('https://cdn.shopify.com/s/files/1/0423/3674/7669/files/2_fruit_600x600.png?v=1695048744');">
                        </div>
                        <div class="relative z-10">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700 transition group-hover:scale-105">
                                <i class="fa-solid fa-store text-xl"></i>
                            </div>
                            <p class="mt-5 text-xs font-semibold uppercase tracking-[0.22em] text-emerald-600">Fresh finds</p>
                            <h3 class="brand-serif mt-3 text-2xl font-bold text-neutral-900">Browse the market with ease</h3>
                            <p class="mt-3 text-sm leading-7 text-neutral-500">Explore produce, seafood, meat, and pantry staples through a clean storefront built around how people already shop in the palengke.</p>
                        </div>
                    </article>

                    <article class="group relative overflow-hidden rounded-3xl border border-stone-200 bg-white p-8 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                        <div class="pointer-events-none absolute inset-0 bg-cover bg-center opacity-15 transition duration-300 group-hover:scale-105 group-hover:opacity-20"
                            style="background-image: url('https://static.tripzilla.ph/media/98778/conversions/Palengke-tips-buying-veggies-w768.webp');">
                        </div>
                        <div class="relative z-10">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 transition group-hover:scale-105">
                                <i class="fa-solid fa-shop text-xl"></i>
                            </div>
                            <p class="mt-5 text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">Trusted stalls</p>
                            <h3 class="brand-serif mt-3 text-2xl font-bold text-neutral-900">Know the vendor behind the listing</h3>
                            <p class="mt-3 text-sm leading-7 text-neutral-500">Each storefront carries its own name, identity, and product selection so shoppers can recognize the people behind the goods they browse.</p>
                        </div>
                    </article>

                    <article class="group relative overflow-hidden rounded-3xl border border-stone-200 bg-white p-8 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                        <div class="pointer-events-none absolute inset-0 bg-cover bg-center opacity-15 transition duration-300 group-hover:scale-105 group-hover:opacity-20"
                            style="background-image: url('https://www.bulatlat.com/wp-content/uploads/2022/11/market-evelyn-eleazar-840x560.png');">
                        </div>
                        <div class="relative z-10">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 transition group-hover:scale-105">
                                <i class="fa-solid fa-heart text-xl"></i>
                            </div>
                            <p class="mt-5 text-xs font-semibold uppercase tracking-[0.22em] text-rose-600">Suki spirit</p>
                            <h3 class="brand-serif mt-3 text-2xl font-bold text-neutral-900">Keep the market relationship alive</h3>
                            <p class="mt-3 text-sm leading-7 text-neutral-500">SukiMarket is built around familiarity and trust, turning neighborhood market habits into a more organized digital experience.</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="how-it-works" class="border-y border-stone-200 bg-white py-24">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-[0.28em] text-emerald-600">How it works</p>
                    <h2 class="brand-serif mt-3 text-4xl font-bold text-neutral-900 sm:text-5xl">Simple, familiar, and market-inspired.</h2>
                </div>

                <div class="mt-16 grid gap-8 md:grid-cols-3">
                    <article class="brand-panel p-7">
                        <div class="flex items-start gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-sm font-bold text-emerald-700">01</span>
                            <div>
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-stone-100 text-neutral-600"><i class="fa-solid fa-user-plus"></i></span>
                                <h3 class="brand-serif mt-4 text-xl font-bold text-neutral-900">Browse market categories</h3>
                                <p class="mt-3 text-sm leading-7 text-neutral-500">Move through vegetables, seafood, fruits, and everyday staples in one organized storefront built for easy discovery.</p>
                            </div>
                        </div>
                    </article>

                    <article class="brand-panel p-7">
                        <div class="flex items-start gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-sm font-bold text-emerald-700">02</span>
                            <div>
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-stone-100 text-neutral-600"><i class="fa-solid fa-magnifying-glass"></i></span>
                                <h3 class="brand-serif mt-4 text-xl font-bold text-neutral-900">Check each stall closely</h3>
                                <p class="mt-3 text-sm leading-7 text-neutral-500">Open product pages, compare prices, review stock, and look at each vendor&apos;s storefront details before choosing where to shop.</p>
                            </div>
                        </div>
                    </article>

                    <article class="brand-panel p-7">
                        <div class="flex items-start gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-sm font-bold text-emerald-700">03</span>
                            <div>
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-stone-100 text-neutral-600"><i class="fa-solid fa-list-check"></i></span>
                                <h3 class="brand-serif mt-4 text-xl font-bold text-neutral-900">Build your go-to routine</h3>
                                <p class="mt-3 text-sm leading-7 text-neutral-500">Come back to the same trusted stalls and enjoy a marketplace experience shaped around familiarity, clarity, and convenience.</p>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="portals" class="py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-[0.28em] text-emerald-600">Built for the marketplace</p>
                    <h2 class="brand-serif mt-3 text-4xl font-bold text-neutral-900 sm:text-5xl">Two tailored portals, one shared market.</h2>
                </div>

                <div class="mt-14 grid gap-6 xl:grid-cols-2">
                    <article class="relative overflow-hidden rounded-4xl bg-linear-to-br from-emerald-600 to-emerald-800 p-10 text-white shadow-lg">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 text-xl"><i class="fa-solid fa-basket-shopping"></i></span>
                        <h3 class="brand-serif mt-6 text-3xl font-bold">Customer dashboard</h3>
                        <p class="mt-4 text-sm leading-7 text-emerald-100">A shopper portal for browsing fresh listings, keeping favorite stalls nearby, and following upcoming orders.</p>
                        <a href="{{ auth()->check() ? $portalHomeRoute : route('register') }}" class="mt-8 inline-flex items-center rounded-xl bg-white px-5 py-3 text-sm font-semibold text-emerald-700 transition hover:bg-stone-100">
                            {{ auth()->check() ? 'Open my dashboard' : 'Create a customer account' }}
                        </a>
                    </article>

                    <article class="relative overflow-hidden rounded-4xl bg-linear-to-br from-amber-500 to-orange-600 p-10 text-white shadow-lg">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 text-xl"><i class="fa-solid fa-shop"></i></span>
                        <h3 class="brand-serif mt-6 text-3xl font-bold">Vendor workspace</h3>
                        <p class="mt-4 text-sm leading-7 text-amber-100">A seller portal built around onboarding, catalog management, order handling, and sales visibility.</p>
                        <a href="{{ auth()->check() ? route('vendor.registration') : route('register') }}" class="mt-8 inline-flex items-center rounded-xl bg-white px-5 py-3 text-sm font-semibold text-amber-700 transition hover:bg-stone-100">
                            {{ auth()->check() ? 'Explore seller pages' : 'Start with an account' }}
                        </a>
                    </article>

                </div>
            </div>
        </section>

        <section class="bg-neutral-950 py-24">
            <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
                <h2 class="brand-serif mt-6 text-4xl font-bold text-white sm:text-5xl">Explore the customer storefront today.</h2>
                <p class="mt-5 text-lg leading-8 text-neutral-300">
                    Step into a cleaner digital palengke with real storefronts, strong vendor identity, and a warm market-first browsing experience.
                </p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                    @auth
                        <a href="{{ $portalHomeRoute }}" class="brand-button-primary">
                            Go to your dashboard
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="brand-button-primary">
                            Create a free account
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                        </a>
                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-xl border border-neutral-700 px-5 py-3 text-sm font-semibold text-neutral-300 transition hover:border-neutral-500 hover:text-white">
                            Sign in
                        </a>
                    @endauth
                </div>
            </div>
        </section>

        <footer class="border-t border-stone-200 bg-stone-50 py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                    <div class="flex items-center gap-3">
                       <x-app-logo class="h-8 w-auto" />
                    </div>

                    <p class="max-w-xl text-center text-xs leading-6 text-neutral-400">
                        A digital marketplace inspired by the Filipino wet market experience and shaped around local trust, freshness, and familiar buying habits.
                    </p>

                    <div class="flex items-center gap-5 text-xs text-neutral-400">
                        <a href="{{ route('login') }}" class="transition hover:text-neutral-700">Log in</a>
                        <a href="{{ route('register') }}" class="transition hover:text-neutral-700">Register</a>
                    </div>
                </div>

                <div class="mt-8 border-t border-stone-200 pt-6 text-center text-xs text-neutral-400">
                    &copy; {{ date('Y') }} SukiMarket. All rights reserved.
                </div>
            </div>
        </footer>
    </body>
</html>
