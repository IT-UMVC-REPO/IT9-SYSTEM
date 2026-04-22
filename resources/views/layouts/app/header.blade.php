<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    @php
        $user = auth()->user();

        [$navigationItems, $quickActionItems, $portalSummary] = match ($user->effectiveMarketplaceRole()) {
            \App\Enums\UserRole::Customer => [
                [
                    ['label' => __('Dashboard'), 'route' => route('customer.dashboard'), 'patterns' => ['customer.*'], 'icon' => 'fa-solid fa-table-cells-large'],
                    ['label' => __('Storefront'), 'route' => route('shop.home'), 'patterns' => ['shop.home', 'shop.products.*'], 'icon' => 'fa-solid fa-store'],
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
                    ['label' => __('Products'), 'route' => route('vendor.products'), 'patterns' => ['vendor.products', 'vendor.products.*'], 'icon' => 'fa-solid fa-boxes-stacked'],
                    ['label' => __('Messages'), 'route' => route('messages.inbox'), 'patterns' => ['messages.*'], 'icon' => 'fa-solid fa-comments'],
                    ['label' => __('Sales'), 'route' => route('vendor.sales'), 'patterns' => ['vendor.sales'], 'icon' => 'fa-solid fa-chart-line'],
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
                [
                    ['label' => __('Vendors'), 'route' => route('admin.vendors'), 'patterns' => ['admin.vendors', 'admin.vendors.*'], 'icon' => 'fa-solid fa-user-check'],
                    ['label' => __('Users'), 'route' => route('admin.users'), 'patterns' => ['admin.users'], 'icon' => 'fa-solid fa-users'],
                    ['label' => __('Orders'), 'route' => route('admin.orders'), 'patterns' => ['admin.orders'], 'icon' => 'fa-solid fa-bag-shopping'],
                ],
                __('Review approvals, users, and marketplace operations from the admin portal.'),
            ],
        };

        $mobileNavigationItems = [
            ...$navigationItems,
            ['label' => __('Home'), 'route' => route('home'), 'patterns' => ['home'], 'icon' => 'fa-solid fa-house'],
        ];
    @endphp
    <body class="brand-shell min-h-screen text-neutral-800 antialiased">
        <header class="sticky top-0 z-50 border-b border-[#e7e5e4] bg-white/95 backdrop-blur-md">
            <nav class="mx-auto flex h-[52px] max-w-[1500px] items-stretch gap-4 px-4 sm:px-6 lg:px-8">
                <div class="flex shrink-0 items-center">
                    <x-app-logo href="{{ $navigationItems[0]['route'] }}" wire:navigate class="shrink-0" />
                </div>

                <div class="hidden flex-1 items-stretch justify-center lg:flex">
                    @foreach ($navigationItems as $item)
                        <a
                            href="{{ $item['route'] }}"
                            wire:navigate
                            class="flex h-full items-center gap-2 border-b-2 px-5 text-sm transition-colors {{ request()->routeIs(...$item['patterns']) ? 'border-emerald-600 font-semibold text-emerald-700' : 'border-transparent text-stone-500 hover:text-stone-800' }}"
                        >
                            <i class="{{ $item['icon'] }} text-sm"></i>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="ml-auto mr-3 hidden items-center gap-1 lg:flex">
                    @foreach ($quickActionItems as $item)
                        <a
                            href="{{ $item['route'] }}"
                            title="{{ $item['label'] }}"
                            wire:navigate
                            class="relative flex h-9 w-9 items-center justify-center rounded-xl transition {{ request()->routeIs(...$item['patterns']) ? 'bg-emerald-50 text-emerald-700' : 'text-stone-500 hover:bg-stone-100 hover:text-stone-900' }}"
                        >
                            <i class="{{ $item['icon'] }} text-sm"></i>
                        </a>
                    @endforeach
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
                                <p class="text-sm text-neutral-600">{{ $portalSummary }}</p>
                            </div>
                        </div>

                        <div class="grid gap-2 p-4">
                            @foreach ($mobileNavigationItems as $item)
                                <a
                                    href="{{ $item['route'] }}"
                                    wire:navigate
                                    class="flex items-center justify-between gap-3 rounded-[1.25rem] border px-4 py-3 text-sm font-semibold transition {{ request()->routeIs(...$item['patterns']) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-stone-200 text-neutral-700 hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700' }}"
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
