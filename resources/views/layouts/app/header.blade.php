<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    @php
        $user = auth()->user();

        [$primaryItem, $portalLabel, $portalClasses, $portalSummary] = match ($user->effectiveMarketplaceRole()) {
            \App\Enums\UserRole::Customer => [
                ['label' => __('Storefront'), 'route' => route('shop.home'), 'pattern' => 'shop.*', 'icon' => 'fa-solid fa-store'],
                __('Customer portal'),
                'border-emerald-200 bg-emerald-50 text-emerald-700',
                __('Browse approved stalls and fresh listings.'),
            ],
            \App\Enums\UserRole::Vendor => [
                ['label' => __('Vendor'), 'route' => route('vendor.dashboard'), 'pattern' => 'vendor.*', 'icon' => 'fa-solid fa-shop'],
                __('Vendor portal'),
                'border-amber-200 bg-amber-50 text-amber-700',
                __('Manage your storefront and seller tools.'),
            ],
            \App\Enums\UserRole::Admin => [
                ['label' => __('Admin'), 'route' => route('admin.dashboard'), 'pattern' => 'admin.*', 'icon' => 'fa-solid fa-shield-halved'],
                __('Admin portal'),
                'border-sky-200 bg-sky-50 text-sky-700',
                __('Oversee the marketplace and trusted access.'),
            ],
        };

        $navigationItems = [
            $primaryItem,
            ['label' => __('Home'), 'route' => route('home'), 'pattern' => 'home', 'icon' => 'fa-solid fa-house'],
        ];
    @endphp
    <body class="brand-shell min-h-screen text-neutral-800 antialiased">
        <header class="sticky top-0 z-50 border-b border-[#e7e5e4] bg-white/95 backdrop-blur-md">
            <nav class="mx-auto flex h-[52px] max-w-[1500px] items-stretch gap-4 px-4 sm:px-6 lg:px-8">
                <div class="flex shrink-0 items-center">
                    <x-app-logo href="{{ $primaryItem['route'] }}" wire:navigate class="shrink-0" />
                </div>

                <div class="hidden flex-1 items-stretch justify-center lg:flex">
                    @foreach ($navigationItems as $item)
                        @continue($item['pattern'] === 'home')

                        <a
                            href="{{ $item['route'] }}"
                            wire:navigate
                            class="flex h-full items-center gap-2 px-5 text-sm border-b-2 transition-colors {{ request()->routeIs($item['pattern']) ? 'border-emerald-600 text-emerald-700 font-semibold' : 'border-transparent text-stone-500 hover:text-stone-800' }}"
                        >
                            <i class="{{ $item['icon'] }} text-sm"></i>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="hidden lg:flex items-center gap-1 ml-auto mr-3">
                    {{-- Cart --}}
                    <a href="#" title="Cart"
                        class="relative flex h-9 w-9 items-center justify-center rounded-xl text-stone-500 transition hover:bg-stone-100 hover:text-stone-900">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25
                                a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684
                                2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25
                                L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0
                                011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                        </svg>
                    </a>

                    {{-- Messages --}}
                    <a href="#" title="Messages"
                        class="relative flex h-9 w-9 items-center justify-center rounded-xl text-stone-500 transition hover:bg-stone-100 hover:text-stone-900">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0
                                01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0
                                .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0
                                11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5
                                3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16
                                2.185.283 3.293.369V21l4.184-4.183a1.14 1.14
                                0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233
                                2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995
                                -2.707-3.228A48.394 48.394 0 0012 3c-2.392
                                0-4.744.175-7.043.513C3.373 3.746 2.25 5.14
                                2.25 6.741v6.018z" />
                        </svg>
                    </a>

                    {{-- Favourites --}}
                    <a href="#" title="Favourites"
                        class="relative flex h-9 w-9 items-center justify-center rounded-xl text-stone-500 transition hover:bg-stone-100 hover:text-stone-900">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935
                                0-3.597 1.126-4.312 2.733-.715-1.607-2.377
                                -2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0
                                7.22 9 12 9 12s9-4.78 9-12z" />
                        </svg>
                    </a>
                </div>

                <div class="hidden items-center lg:flex">
                    <x-desktop-user-menu />
                </div>

                <details class="relative ml-auto flex items-center lg:hidden">
                    <summary class="flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-full border border-stone-200 bg-white text-neutral-700 shadow-sm transition hover:border-emerald-200 hover:text-emerald-700 marker:hidden [&::-webkit-details-marker]:hidden">
                        <i class="fa-solid fa-bars-staggered text-sm"></i>
                    </summary>

                    <div class="absolute right-0 top-[calc(100%+0.75rem)] w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-[1.75rem] border border-stone-200 bg-white shadow-2xl">
                        <div class="border-b border-stone-200 bg-stone-50 px-5 py-4">
                            <div class="flex items-center gap-3">
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-neutral-900 text-sm font-semibold text-white">
                                    {{ $user->initials() }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-neutral-900">{{ $user->name }}</p>
                                    <p class="truncate text-xs text-neutral-500">{{ $user->email }}</p>
                                </div>
                            </div>

                            <div class="mt-4 rounded-[1.25rem] border border-stone-200 bg-white p-3">
                                <p class="text-sm text-neutral-600">
                                    {{ match ($user->effectiveMarketplaceRole()) {
                                        \App\Enums\UserRole::Customer => 'You are browsing as a customer.',
                                        \App\Enums\UserRole::Vendor => 'You are browsing as a vendor.',
                                        \App\Enums\UserRole::Admin => 'You are browsing as an admin.',
                                    } }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-2 p-4">
                            @foreach ($navigationItems as $item)
                                @continue($item['pattern'] === 'home')

                                <a
                                    href="{{ $item['route'] }}"
                                    wire:navigate
                                    class="flex items-center justify-between gap-3 rounded-[1.25rem] border px-4 py-3 text-sm font-semibold transition {{ request()->routeIs($item['pattern']) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-stone-200 text-neutral-700 hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700' }}"
                                >
                                    <span class="flex items-center gap-3">
                                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white text-neutral-700 shadow-sm">
                                            <i class="{{ $item['icon'] }}"></i>
                                        </span>
                                        <span>{{ $item['label'] }}</span>
                                    </span>
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </a>
                            @endforeach
                        </div>

                        <div class="border-t border-stone-200 p-4">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="brand-button-secondary w-full">
                                    <i class="fa-solid fa-right-from-bracket text-xs"></i>
                                    {{ __('Log out') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </details>
            </nav>
        </header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
