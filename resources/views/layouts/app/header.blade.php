<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    @php
        $user = auth()->user();
        $navigationItems = [];
        $quickActionItems = [];
        $portalSummary = null;
        $mobileNavigationItems = [];

        if ($user !== null) {
            [$navigationItems, $quickActionItems, $portalSummary] = match ($user->effectiveMarketplaceRole()) {
                \App\Enums\UserRole::Customer => [
                    [
                        ['label' => __('Home'), 'route' => route('home'), 'patterns' => ['home'], 'icon' => 'fa-solid fa-house'],
                        ['label' => __('Dashboard'), 'route' => route('customer.dashboard'), 'patterns' => ['customer.*'], 'icon' => 'fa-solid fa-table-cells-large'],
                        ['label' => __('Storefront'), 'route' => route('shop.home'), 'patterns' => ['shop.home', 'shop.products.*', 'shop.vendors', 'shop.vendors.*'], 'icon' => 'fa-solid fa-store'],
                        ['label' => __('Orders'), 'route' => route('shop.orders'), 'patterns' => ['shop.orders', 'shop.orders.*'], 'icon' => 'fa-solid fa-bag-shopping'],
                        ['label' => __('Seller setup'), 'route' => route('vendor.registration'), 'patterns' => ['vendor.registration'], 'icon' => 'fa-solid fa-shop'],
                    ],
                    [
                        ['label' => __('Cart'), 'route' => route('shop.cart'), 'patterns' => ['shop.cart'], 'icon' => 'fa-solid fa-cart-shopping'],
                        ['label' => __('Messages'), 'route' => route('messages.inbox'), 'patterns' => ['messages.*'], 'icon' => 'fa-solid fa-comments'],
                        ['label' => __('Favourites'), 'route' => route('shop.favorites'), 'patterns' => ['shop.favorites'], 'icon' => 'fa-solid fa-heart'],
                    ],
                    __('Browse the market, track orders, and keep your favorite stalls close.'),
                ],
                \App\Enums\UserRole::Vendor => [
                    [
                        ['label' => __('Dashboard'), 'route' => route('vendor.dashboard'), 'patterns' => ['vendor.dashboard'], 'icon' => 'fa-solid fa-shop'],
                        ['label' => __('Products'), 'route' => route('vendor.products'), 'patterns' => ['vendor.products', 'vendor.products.*'], 'icon' => 'fa-solid fa-boxes-stacked'],
                        ['label' => __('Orders'), 'route' => route('vendor.orders'), 'patterns' => ['vendor.orders', 'vendor.orders.*'], 'icon' => 'fa-solid fa-bag-shopping'],
                        ['label' => __('Sales'), 'route' => route('vendor.sales'), 'patterns' => ['vendor.sales'], 'icon' => 'fa-solid fa-chart-line'],
                    ],
                    [
                        ['label' => __('Messages'), 'route' => route('messages.inbox'), 'patterns' => ['messages.*'], 'icon' => 'fa-solid fa-comments'],
                    ],
                    __('Manage your storefront, prepare orders, and review sales from one place.'),
                ],
                \App\Enums\UserRole::Admin => [
                    [
                        ['label' => __('Dashboard'), 'route' => route('admin.dashboard'), 'patterns' => ['admin.dashboard'], 'icon' => 'fa-solid fa-shield-halved'],
                        ['label' => __('Vendors'), 'route' => route('admin.vendors'), 'patterns' => ['admin.vendors', 'admin.vendors.*'], 'icon' => 'fa-solid fa-user-check'],
                        ['label' => __('Users'), 'route' => route('admin.users'), 'patterns' => ['admin.users'], 'icon' => 'fa-solid fa-users'],
                        ['label' => __('Orders'), 'route' => route('admin.orders'), 'patterns' => ['admin.orders'], 'icon' => 'fa-solid fa-bag-shopping'],
                    ],
                    [],
                    __('Review approvals, users, and marketplace operations from the admin portal.'),
                ],
            };

            $mobileNavigationItems = [
                ...$navigationItems,
                ['label' => __('Home'), 'route' => route('home'), 'patterns' => ['home'], 'icon' => 'fa-solid fa-house'],
            ];
        }
    @endphp
    <body class="brand-shell min-h-screen text-neutral-800 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <header class="sticky top-0 z-50 border-b border-white/40 bg-white/70 shadow-sm shadow-black/5 backdrop-blur-xl backdrop-saturate-150 dark:border-white/10 dark:bg-zinc-900/70">
            <nav class="mx-auto flex h-[52px] max-w-[1500px] items-stretch gap-4 px-4 sm:px-6 lg:px-8">
                <div class="flex shrink-0 items-center">
                    <x-app-logo href="{{ $user ? $navigationItems[0]['route'] : route('home') }}" wire:navigate class="shrink-0" />
                </div>

                @auth
                    <div class="hidden flex-1 items-stretch justify-center lg:flex">
                        @foreach ($navigationItems as $item)
                          @if (!$loop->first)
                            <a
                                href="{{ $item['route'] }}"
                                wire:navigate
                                class="flex h-full items-center gap-2 border-b-2 px-5 text-sm transition-colors {{ request()->routeIs(...$item['patterns']) ? 'nav-active font-semibold' : 'border-transparent text-stone-500 hover:text-stone-800 dark:text-zinc-300 dark:hover:text-white' }}"
                            >
                                <i class="{{ $item['icon'] }} text-sm"></i>
                                <span>{{ $item['label'] }}</span>
                            </a>
                          @endif
                        @endforeach
                    </div>

                    <div class="ml-auto mr-3 hidden items-center gap-1 lg:flex">
                        @foreach ($quickActionItems as $item)
                            <a
                                href="{{ $item['route'] }}"
                                title="{{ $item['label'] }}"
                                wire:navigate
                                class="relative flex h-9 w-9 items-center justify-center rounded-xl transition {{ request()->routeIs(...$item['patterns']) ? 'quick-action-active' : 'text-stone-500 hover:bg-stone-100 hover:text-stone-900 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white' }}"
                            >
                                <i class="{{ $item['icon'] }} text-sm"></i>
                            </a>
                        @endforeach
                    </div>

                    <div class="hidden items-center lg:flex">
                        <x-desktop-user-menu />
                    </div>

                    <details class="relative ml-auto flex items-center lg:hidden">
                        <summary class="brand-summary-toggle flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-full border border-stone-200 bg-white text-neutral-700 shadow-sm transition marker:hidden dark:border-white/10 dark:bg-zinc-900/80 dark:text-zinc-100 [&::-webkit-details-marker]:hidden">
                            <i class="fa-solid fa-bars-staggered text-sm"></i>
                        </summary>

                        <div class="absolute right-0 top-[calc(100%+0.75rem)] w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-[1.75rem] border border-stone-200 bg-white shadow-2xl dark:border-white/10 dark:bg-zinc-900 dark:shadow-black/40">
                            <div class="border-b border-stone-200 bg-stone-50 px-5 py-4 dark:border-white/10 dark:bg-zinc-900/95">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl brand-logo-badge text-sm font-semibold text-white dark:dark dark:text-zinc-900">
                                        {{ $user->initials() }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ $user->name }}</p>
                                        <p class="truncate text-xs text-neutral-500 dark:text-zinc-400">{{ $user->email }}</p>
                                    </div>
                                </div>

                                <div class="mt-4 rounded-[1.25rem] border border-stone-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                                    <p class="text-sm text-neutral-600 dark:text-zinc-300">{{ $portalSummary }}</p>
                                </div>
                            </div>

                            <div class="grid gap-2 p-4">
                                @foreach ($mobileNavigationItems as $item)
                                    <a
                                        href="{{ $item['route'] }}"
                                        wire:navigate
                                        @class([
                                            'brand-mobile-nav-link flex items-center justify-between gap-3 rounded-[1.25rem] border border-stone-200 px-4 py-3 text-sm font-semibold text-neutral-700 transition dark:border-white/10 dark:text-zinc-100',
                                            'is-active' => request()->routeIs(...$item['patterns']),
                                        ])
                                    >
                                        <span class="flex items-center gap-3">
                                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white text-neutral-700 shadow-sm dark:bg-zinc-800 dark:text-zinc-100">
                                                <i class="{{ $item['icon'] }}"></i>
                                            </span>
                                            <span>{{ $item['label'] }}</span>
                                        </span>
                                        <i class="fa-solid fa-arrow-right text-xs"></i>
                                    </a>
                                @endforeach
                            </div>

                            <div class="border-t border-stone-200 p-4 dark:border-white/10">
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
                @else
                    <div class="ml-auto hidden items-center gap-3 sm:flex">
                        <a href="{{ route('login') }}" class="brand-link" wire:navigate>
                            {{ __('Log in') }}
                        </a>
                        <a href="{{ route('register') }}" class="brand-button-primary" wire:navigate>
                            {{ __('Create account') }}
                        </a>
                    </div>

                    <details class="relative ml-auto flex items-center sm:hidden">
                        <summary class="brand-summary-toggle flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-full border border-stone-200 bg-white text-neutral-700 shadow-sm transition marker:hidden dark:border-white/10 dark:bg-zinc-900/80 dark:text-zinc-100 [&::-webkit-details-marker]:hidden">
                            <i class="fa-solid fa-bars-staggered text-sm"></i>
                        </summary>

                        <div class="absolute right-0 top-[calc(100%+0.75rem)] w-[min(18rem,calc(100vw-2rem))] overflow-hidden rounded-[1.75rem] border border-stone-200 bg-white shadow-2xl dark:border-white/10 dark:bg-zinc-900 dark:shadow-black/40">
                            <div class="border-b border-stone-200 bg-stone-50 px-5 py-4 dark:border-white/10 dark:bg-zinc-900/95">
                                <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Welcome to SukiMarket') }}</p>
                                <p class="mt-2 text-xs leading-6 text-neutral-500 dark:text-zinc-400">
                                    {{ __('Sign in to open your dashboard or create an account to start browsing the market.') }}
                                </p>
                            </div>

                            <div class="grid gap-3 p-4">
                                <a href="{{ route('login') }}" class="brand-button-secondary w-full" wire:navigate>
                                    {{ __('Log in') }}
                                </a>
                                <a href="{{ route('register') }}" class="brand-button-primary w-full" wire:navigate>
                                    {{ __('Create account') }}
                                </a>
                            </div>
                        </div>
                    </details>
                @endauth
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
