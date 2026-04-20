<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="brand-shell min-h-screen antialiased">
        <div class="mx-auto flex min-h-screen max-w-7xl items-stretch p-4 sm:p-6 lg:p-8">
            <div class="grid flex-1 gap-6 lg:grid-cols-[minmax(0,1.08fr)_minmax(28rem,0.92fr)]">
                <section class="relative hidden overflow-hidden rounded-[2rem] bg-neutral-950 p-8 text-white shadow-xl lg:flex lg:flex-col lg:justify-between">
                    <div class="absolute -right-16 top-0 h-56 w-56 rounded-full bg-emerald-500/10 blur-3xl"></div>
                    <div class="absolute -left-10 bottom-0 h-48 w-48 rounded-full bg-amber-300/10 blur-3xl"></div>

                    <a href="{{ route('home') }}" class="relative z-10 flex items-center gap-3" wire:navigate>
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-500 text-sm font-bold text-white">S</span>
                        <span>
                            <span class="brand-serif block text-lg font-bold text-white">SukiMarket</span>
                            <span class="block text-[11px] uppercase tracking-[0.28em] text-neutral-400">Digital palengke</span>
                        </span>
                    </a>

                    <div class="relative z-10 max-w-xl">
                        <span class="inline-flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-emerald-200">
                            <i class="fa-solid fa-store"></i>
                            Customer portal
                        </span>
                        <h1 class="brand-serif mt-6 text-5xl font-bold leading-tight">
                            Fresh market finds, one familiar place.
                        </h1>
                        <p class="mt-5 text-base leading-8 text-neutral-300">
                            Browse approved vendors today with the same branding and storefront language you see on the public homepage. Vendor onboarding remains admin-reviewed as the next modules roll out.
                        </p>
                    </div>

                    <div class="relative z-10 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-3xl border border-white/10 bg-white/5 p-5 backdrop-blur-sm">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-200">
                                <i class="fa-solid fa-circle-check"></i>
                            </span>
                            <h2 class="mt-4 text-lg font-semibold text-white">Storefront live now</h2>
                            <p class="mt-2 text-sm leading-6 text-neutral-300">Search, browse, and inspect approved listings from the customer portal.</p>
                        </div>

                        <div class="rounded-3xl border border-white/10 bg-white/5 p-5 backdrop-blur-sm">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-400/15 text-amber-200">
                                <i class="fa-solid fa-user-shield"></i>
                            </span>
                            <h2 class="mt-4 text-lg font-semibold text-white">Seller access reviewed</h2>
                            <p class="mt-2 text-sm leading-6 text-neutral-300">Registration stays customer-first while vendor approval continues through admin review.</p>
                        </div>
                    </div>
                </section>

                <section class="flex items-center justify-center">
                    <div class="brand-panel w-full max-w-xl p-6 shadow-xl sm:p-8 lg:p-10">
                        <a href="{{ route('home') }}" class="brand-link mb-6" wire:navigate>
                            <i class="fa-solid fa-arrow-left text-xs"></i>
                            Back to home
                        </a>

                        <div class="flex flex-col gap-6">
                            {{ $slot }}
                        </div>
                    </div>
                </section>
            </div>
        </div>
        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
