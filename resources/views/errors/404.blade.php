@php
    $user = auth()->user();
    $dashboardUrl = $user !== null
        ? route($user->homeRoute())
        : route('login');
    $dashboardLabel = $user !== null
        ? __('Return to dashboard')
        : __('Log in to continue');
@endphp

<x-layouts::app :title="__('Page not found')">
    <section class="mx-auto flex min-h-[calc(100vh-4.75rem)] w-full max-w-6xl items-center px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid w-full items-center gap-10 lg:grid-cols-[0.95fr_1.05fr]">
            <div class="order-2 lg:order-1">
                <div class="brand-kicker">
                    <i class="fa-solid fa-route" aria-hidden="true"></i>
                    <span>{{ __('404') }}</span>
                </div>

                <h1 class="mt-5 max-w-2xl text-4xl font-bold leading-tight text-neutral-950 dark:text-zinc-50 sm:text-5xl">
                    {{ __("This page doesn't exist") }}
                </h1>

                <p class="mt-4 max-w-xl text-base leading-8 text-neutral-600 dark:text-zinc-300">
                    {{ __('That market lane is not part of SukiMarket. The link may be mistyped, moved, or no longer available.') }}
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a href="{{ $dashboardUrl }}" wire:navigate class="brand-button-primary active:scale-[0.96]">
                        <i class="fa-solid fa-table-cells-large text-sm" aria-hidden="true"></i>
                        <span>{{ $dashboardLabel }}</span>
                    </a>

                    <a href="{{ route('home') }}" wire:navigate class="brand-button-secondary active:scale-[0.96]">
                        <i class="fa-solid fa-house text-sm" aria-hidden="true"></i>
                        <span>{{ __('Go home') }}</span>
                    </a>
                </div>
            </div>

            <div class="order-1 lg:order-2">
                <div class="relative mx-auto aspect-square w-full max-w-[460px] overflow-hidden rounded-[2rem] border border-stone-200 bg-white/80 p-6 shadow-xl shadow-stone-950/8 dark:border-white/10 dark:bg-zinc-900/80 dark:shadow-black/30">
                    <div class="absolute inset-x-8 top-8 h-20 rounded-full bg-amber-200/35 blur-3xl dark:bg-amber-400/10"></div>
                    <div class="relative flex h-full flex-col justify-between rounded-[1.5rem] border border-stone-200/80 bg-stone-50/90 p-6 dark:border-white/10 dark:bg-zinc-950/50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="h-3 w-3 rounded-full bg-rose-400"></span>
                                <span class="h-3 w-3 rounded-full bg-amber-400"></span>
                                <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
                            </div>
                            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-neutral-500 shadow-sm dark:bg-zinc-900 dark:text-zinc-300">{{ __('No stall here') }}</span>
                        </div>

                        <div class="grid gap-4">
                            <div class="mx-auto flex h-28 w-28 items-center justify-center rounded-[2rem] bg-white text-[var(--brand-600)] shadow-sm dark:bg-zinc-900 dark:text-[var(--brand-400)]">
                                <i class="fa-solid fa-map-location-dot text-5xl" aria-hidden="true"></i>
                            </div>
                            <div class="grid gap-3">
                                <div class="h-3 rounded-full bg-stone-200 dark:bg-zinc-800"></div>
                                <div class="h-3 w-4/5 rounded-full bg-stone-200 dark:bg-zinc-800"></div>
                                <div class="h-3 w-2/3 rounded-full bg-stone-200 dark:bg-zinc-800"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <span class="h-16 rounded-2xl bg-white shadow-sm dark:bg-zinc-900"></span>
                            <span class="h-16 rounded-2xl bg-[var(--brand-100)] shadow-sm dark:bg-zinc-800"></span>
                            <span class="h-16 rounded-2xl bg-white shadow-sm dark:bg-zinc-900"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts::app>
