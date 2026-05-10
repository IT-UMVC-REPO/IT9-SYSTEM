<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    @php
        $user = auth()->user();
        $effectiveMarketplaceRole = null;
        $navigationItems = [];
        $quickActionItems = [];
        $portalSummary = null;
        $mobileNavigationItems = [];
        $mobileAccountNavigationItems = [];
        $mobileBottomNavigationItems = [];
        $showNotificationBell = false;
        $isConversationSurface = request()->routeIs('messages.conversation', 'messages.group');
        $navItem = fn (string $label, string $routeName, array $patterns, string $icon): array => [
            'label' => __($label),
            'route' => route($routeName),
            'patterns' => $patterns,
            'icon' => $icon,
        ];
        $homeNavigationItem = $navItem('Home', 'home', ['home'], 'fa-solid fa-house');

        if ($user !== null) {
            $effectiveMarketplaceRole = $user->effectiveMarketplaceRole();
            $settingsNavigationItem = $navItem('Settings', 'profile.edit', ['profile.edit', 'appearance.edit', 'security.edit'], 'fa-solid fa-gear');

            [$navigationItems, $quickActionItems, $portalSummary] = match ($effectiveMarketplaceRole) {
                \App\Enums\UserRole::Customer => [
                    [
                        $homeNavigationItem,
                        $navItem('Dashboard', 'customer.dashboard', ['customer.*'], 'fa-solid fa-table-cells-large'),
                        $navItem('Storefront', 'shop.home', ['shop.home', 'shop.products.*', 'shop.vendors', 'shop.vendors.*'], 'fa-solid fa-store'),
                        $navItem('Orders', 'shop.orders', ['shop.orders', 'shop.orders.*'], 'fa-solid fa-bag-shopping'),
                        $navItem('Seller setup', 'vendor.registration', ['vendor.registration'], 'fa-solid fa-shop'),
                        $navItem('Rider setup', 'rider.registration', ['rider.registration'], 'fa-solid fa-motorcycle'),
                    ],
                    [
                        $navItem('Cart', 'shop.cart', ['shop.cart'], 'fa-solid fa-cart-shopping'),
                        $navItem('Messages', 'messages.inbox', ['messages.*'], 'fa-solid fa-comments'),
                        $navItem('Favourites', 'shop.favorites', ['shop.favorites'], 'fa-solid fa-heart'),
                    ],
                    __('Browse the market, track orders, and keep your favorite stalls close.'),
                ],
                \App\Enums\UserRole::Vendor => [
                    [
                        $navItem('Dashboard', 'vendor.dashboard', ['vendor.dashboard'], 'fa-solid fa-shop'),
                        $navItem('Storefront', 'shop.home', ['shop.home', 'shop.products.*', 'shop.vendors', 'shop.vendors.*'], 'fa-solid fa-store'),
                        $navItem('Products', 'vendor.products', ['vendor.products', 'vendor.products.*', 'vendor.stocks'], 'fa-solid fa-boxes-stacked'),
                        $navItem('Orders', 'vendor.orders', ['vendor.orders', 'vendor.orders.*'], 'fa-solid fa-bag-shopping'),
                        $navItem('Sales', 'vendor.sales', ['vendor.sales'], 'fa-solid fa-chart-line'),
                    ],
                    [
                        $navItem('Cart', 'shop.cart', ['shop.cart'], 'fa-solid fa-cart-shopping'),
                        $navItem('Messages', 'messages.inbox', ['messages.*'], 'fa-solid fa-comments'),
                        $navItem('Favourites', 'shop.favorites', ['shop.favorites'], 'fa-solid fa-heart'),
                    ],
                    __('Manage your storefront, prepare orders, and review sales from one place.'),
                ],
                \App\Enums\UserRole::Admin => [
                    [
                        $navItem('Dashboard', 'admin.dashboard', ['admin.dashboard'], 'fa-solid fa-shield-halved'),
                        $navItem('Vendors', 'admin.vendors', ['admin.vendors', 'admin.vendors.*'], 'fa-solid fa-user-check'),
                        $navItem('Riders', 'admin.riders', ['admin.riders', 'admin.riders.*'], 'fa-solid fa-motorcycle'),
                        $navItem('Users', 'admin.users', ['admin.users'], 'fa-solid fa-users'),
                        $navItem('Orders', 'admin.orders', ['admin.orders'], 'fa-solid fa-bag-shopping'),
                        $navItem('Reports', 'admin.reports', ['admin.reports'], 'fa-solid fa-flag'),
                    ],
                    [
                        $navItem('Messages', 'messages.inbox', ['messages.*'], 'fa-solid fa-comments'),
                    ],
                    __('Review approvals, users, and marketplace operations from the admin portal.'),
                ],
                \App\Enums\UserRole::Rider => [
                    [
                        $navItem('Dashboard', 'rider.dashboard', ['rider.dashboard'], 'fa-solid fa-motorcycle'),
                        $navItem('Deliveries', 'rider.deliveries', ['rider.deliveries', 'rider.deliveries.*'], 'fa-solid fa-box'),
                        $navItem('History', 'rider.history', ['rider.history'], 'fa-solid fa-clock-rotate-left'),
                    ],
                    [],
                    __('Pick up orders, track deliveries, and manage your availability.'),
                ],
            };

            $showNotificationBell = in_array($effectiveMarketplaceRole, [
                \App\Enums\UserRole::Customer,
                \App\Enums\UserRole::Vendor,
                \App\Enums\UserRole::Rider,
            ], true);

            $mobileOrdersNavigationItem = collect($navigationItems)->firstWhere('label', __('Orders'));
            $mobileMessagesNavigationItem = collect($quickActionItems)->firstWhere('label', __('Messages'));
            $mobileSellerSetupNavigationItem = collect($navigationItems)->firstWhere('label', __('Seller setup'));
            $mobileRiderSetupNavigationItem = collect($navigationItems)->firstWhere('label', __('Rider setup'));
            $mobileAccountNavigationItems = array_values(array_filter([
                $mobileOrdersNavigationItem,
                $settingsNavigationItem,
                $mobileSellerSetupNavigationItem,
                $mobileRiderSetupNavigationItem,
            ]));

            $mobileNavigationItems = array_values(array_filter(
                $effectiveMarketplaceRole === \App\Enums\UserRole::Customer
                    ? [
                        ...$navigationItems,
                        ...array_filter([$mobileMessagesNavigationItem]),
                    ]
                    : [
                        $homeNavigationItem,
                        ...$navigationItems,
                    ],
                fn (array $item): bool => ! in_array($item['label'], [__('Orders'), __('Seller setup'), __('Rider setup')], true),
            ));

            $mobileBottomNavigationItems = match ($effectiveMarketplaceRole) {
                \App\Enums\UserRole::Customer => [
                    $navItem('Home', 'customer.dashboard', ['customer.dashboard'], 'fa-solid fa-house'),
                    $navItem('Storefront', 'shop.home', ['shop.home', 'shop.products.*', 'shop.vendors', 'shop.vendors.*'], 'fa-solid fa-store'),
                    $navItem('Orders', 'shop.orders', ['shop.orders', 'shop.orders.*'], 'fa-solid fa-bag-shopping'),
                    $navItem('Messages', 'messages.inbox', ['messages.*'], 'fa-solid fa-comments'),
                    $navItem('Cart', 'shop.cart', ['shop.cart'], 'fa-solid fa-cart-shopping'),
                ],
                \App\Enums\UserRole::Vendor => [
                    $navItem('Dashboard', 'vendor.dashboard', ['vendor.dashboard'], 'fa-solid fa-shop'),
                    $navItem('Products', 'vendor.products', ['vendor.products', 'vendor.products.*', 'vendor.stocks'], 'fa-solid fa-boxes-stacked'),
                    $navItem('Orders', 'vendor.orders', ['vendor.orders', 'vendor.orders.*'], 'fa-solid fa-bag-shopping'),
                    $navItem('Messages', 'messages.inbox', ['messages.*'], 'fa-solid fa-comments'),
                    $navItem('Sales', 'vendor.sales', ['vendor.sales'], 'fa-solid fa-chart-line'),
                ],
                \App\Enums\UserRole::Admin => [
                    $navItem('Dashboard', 'admin.dashboard', ['admin.dashboard'], 'fa-solid fa-shield-halved'),
                    $navItem('Vendors', 'admin.vendors', ['admin.vendors', 'admin.vendors.*'], 'fa-solid fa-user-check'),
                    $navItem('Users', 'admin.users', ['admin.users'], 'fa-solid fa-users'),
                    $navItem('Orders', 'admin.orders', ['admin.orders'], 'fa-solid fa-bag-shopping'),
                    $navItem('Reports', 'admin.reports', ['admin.reports', 'admin.reports.*'], 'fa-solid fa-flag'),
                ],
                \App\Enums\UserRole::Rider => [
                    $navItem('Dashboard', 'rider.dashboard', ['rider.dashboard'], 'fa-solid fa-motorcycle'),
                    $navItem('Deliveries', 'rider.deliveries', ['rider.deliveries', 'rider.deliveries.*'], 'fa-solid fa-box'),
                    $navItem('History', 'rider.history', ['rider.history'], 'fa-solid fa-clock-rotate-left'),
                ],
            };
        }

        $logoHref = $user !== null && filled($navigationItems)
            ? $navigationItems[0]['route']
            : route('home');
    @endphp
    <body
        x-data="{ mobileMenuOpen: false, showBackToTop: false }"
        x-init="showBackToTop = window.scrollY > 400; window.addEventListener('scroll', () => showBackToTop = window.scrollY > 400, { passive: true })"
        x-on:keydown.escape.window="mobileMenuOpen = false"
        x-on:livewire:navigating.window="mobileMenuOpen = false"
        @class([
            'brand-shell min-h-screen text-neutral-800 antialiased dark:bg-zinc-950 dark:text-zinc-100',
            'overflow-hidden' => $isConversationSurface,
        ])
    >
        <header
            x-data="{ scrolled: false }"
            x-on:scroll.window.passive="scrolled = window.scrollY > 8"
            x-bind:class="scrolled ? 'shadow-md shadow-black/8' : 'shadow-sm shadow-black/5'"
            class="sticky top-0 z-50 border-b border-white/40 bg-white/70 shadow-sm shadow-black/5 backdrop-blur-xl backdrop-saturate-150 transition-shadow duration-200 dark:border-white/10 dark:bg-zinc-900/70"
        >
            <nav class="mx-auto flex h-[52px] max-w-[1500px] items-stretch gap-4 px-4 sm:px-6 lg:px-8">
                <div class="flex shrink-0 items-center">
                    <x-app-logo href="{{ $logoHref }}" wire:navigate class="shrink-0" />
                </div>

                @auth
                    <div class="hidden flex-1 items-stretch justify-center lg:flex">
                        @foreach ($navigationItems as $item)
                          @if (! ($loop->first && $effectiveMarketplaceRole === \App\Enums\UserRole::Customer))
                            <a
                                href="{{ $item['route'] }}"
                                wire:navigate
                                class="flex h-full items-center gap-2 border-b-2 px-5 text-sm transition-colors duration-150 {{ request()->routeIs(...$item['patterns']) ? 'nav-active font-semibold' : 'border-transparent text-stone-500 hover:text-stone-800 dark:text-zinc-300 dark:hover:text-white' }}"
                            >
                                <i class="{{ $item['icon'] }} text-sm"></i>
                                <span>{{ $item['label'] }}</span>
                            </a>
                          @endif
                        @endforeach
                    </div>

                    <div @class([
                        'ml-auto hidden items-center lg:flex',
                        'mr-1 gap-0.5' => $effectiveMarketplaceRole === \App\Enums\UserRole::Admin,
                        'mr-3 gap-1' => $effectiveMarketplaceRole !== \App\Enums\UserRole::Admin,
                    ])>
                        @foreach ($quickActionItems as $item)
                            @if ($item['route'] === route('shop.cart'))
                                <livewire:cart.cart-badge :is-active="request()->routeIs(...$item['patterns'])" :key="'header-cart-badge'" />
                            @elseif ($item['route'] === route('messages.inbox'))
                                <livewire:messaging.unread-badge :is-active="request()->routeIs(...$item['patterns'])" :key="'header-message-badge'" />
                            @else
                                <a
                                    href="{{ $item['route'] }}"
                                    title="{{ $item['label'] }}"
                                    wire:navigate
                                    class="relative flex h-9 w-9 items-center justify-center rounded-xl transition-colors duration-150 {{ request()->routeIs(...$item['patterns']) ? 'quick-action-active' : 'text-stone-500 hover:bg-stone-100 hover:text-stone-900 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white' }}"
                                >
                                    <i class="{{ $item['icon'] }} text-sm"></i>
                                </a>
                            @endif
                        @endforeach

                        @if ($showNotificationBell)
                            <livewire:notifications.notification-bell :key="'header-notification-bell'" />
                        @endif
                    </div>

                    <div class="hidden items-center lg:flex">
                        <x-desktop-user-menu />
                    </div>

                    <div class="ml-auto flex items-center gap-1 lg:hidden">
                        @foreach ($quickActionItems as $item)
                            @if ($item['route'] === route('shop.cart'))
                                <livewire:cart.cart-badge :is-active="request()->routeIs(...$item['patterns'])" :key="'mobile-header-cart-badge'" />
                            @elseif ($effectiveMarketplaceRole === \App\Enums\UserRole::Admin && $item['route'] === route('messages.inbox'))
                                <livewire:messaging.unread-badge :is-active="request()->routeIs(...$item['patterns'])" :key="'mobile-header-message-badge'" />
                            @endif
                        @endforeach

                        @if ($showNotificationBell)
                            <livewire:notifications.notification-bell :key="'mobile-header-notification-bell'" />
                        @endif

                        <button
                            type="button"
                            x-on:click="mobileMenuOpen = ! mobileMenuOpen"
                            x-bind:aria-expanded="mobileMenuOpen.toString()"
                            class="brand-summary-toggle flex h-9 w-9 items-center justify-center rounded-full border border-stone-200 bg-white text-neutral-700 shadow-sm transition dark:border-white/10 dark:bg-zinc-900/80 dark:text-zinc-100"
                            aria-label="{{ __('Open account menu') }}"
                        >
                            <i class="fa-solid fa-user text-sm"></i>
                        </button>
                    </div>
                @else
                    <div class="ml-auto hidden items-center gap-3 sm:flex">
                        <a href="{{ route('login') }}" class="brand-link" wire:navigate>
                            {{ __('Log in') }}
                        </a>
                        <a href="{{ route('register') }}" class="brand-button-primary active:scale-[0.96]" wire:navigate>
                            {{ __('Create account') }}
                        </a>
                    </div>

                    <button
                        type="button"
                        x-on:click="mobileMenuOpen = ! mobileMenuOpen"
                        x-bind:aria-expanded="mobileMenuOpen.toString()"
                        class="brand-summary-toggle ml-auto flex h-9 w-9 items-center justify-center rounded-full border border-stone-200 bg-white text-neutral-700 shadow-sm transition dark:border-white/10 dark:bg-zinc-900/80 dark:text-zinc-100 sm:hidden"
                        aria-label="{{ __('Open menu') }}"
                    >
                        <i class="fa-solid fa-bars-staggered text-sm"></i>
                    </button>
                @endauth
            </nav>

            <div
                x-cloak
                x-show="mobileMenuOpen"
                x-transition.opacity
                x-on:click="mobileMenuOpen = false"
                class="fixed inset-x-0 top-[52px] z-[55] h-[calc(100vh-52px)] bg-neutral-950/40 backdrop-blur-sm lg:hidden"
            ></div>

            <div
                x-cloak
                x-show="mobileMenuOpen"
                x-transition:enter="transition ease-out duration-220"
                x-transition:enter-start="-translate-y-4 opacity-0 scale-[0.98]"
                x-transition:enter-end="translate-y-0 opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-160"
                x-transition:leave-start="translate-y-0 opacity-100 scale-100"
                x-transition:leave-end="-translate-y-3 opacity-0 scale-[0.98]"
                class="fixed inset-x-3 top-[60px] z-[60] max-h-[calc(100vh-76px)] overflow-y-auto rounded-[1.75rem] border border-stone-200 bg-white shadow-2xl dark:border-white/10 dark:bg-zinc-900 dark:shadow-black/40 lg:hidden"
            >
                @auth
                    <div class="relative border-b border-neutral-200 bg-neutral-50 px-5 py-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <button type="button" x-on:click="mobileMenuOpen = false" class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-full bg-neutral-100 text-neutral-500 transition hover:text-neutral-900 dark:bg-neutral-800 dark:text-neutral-400 dark:hover:text-white" aria-label="{{ __('Close menu') }}">
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>

                        <div class="flex min-w-0 items-center gap-3 pr-11">
                            <x-user-avatar :user="$user" size="profile" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-neutral-900 dark:text-white">{{ $user->name }}</p>
                                <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ $user->email }}</p>
                                <a href="{{ route('profile.edit') }}" wire:navigate x-on:click="mobileMenuOpen = false" class="mt-1 inline-flex text-xs font-semibold text-[var(--brand-700)] dark:text-[var(--brand-400)]">
                                    {{ __('View Profile') }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-5 p-4">
                        <div class="grid gap-2">
                            @foreach ($mobileNavigationItems as $item)
                                <a
                                    href="{{ $item['route'] }}"
                                    wire:navigate
                                    x-on:click="mobileMenuOpen = false"
                                    @class([
                                        'brand-mobile-nav-link flex items-center justify-between gap-3 rounded-xl bg-neutral-100 px-4 py-3 text-sm font-semibold text-neutral-800 transition-all duration-150 active:scale-90 dark:bg-neutral-800 dark:text-white',
                                        'is-active' => request()->routeIs(...$item['patterns']),
                                    ])
                                >
                                    <span class="flex items-center gap-3">
                                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white text-neutral-700 shadow-sm dark:bg-neutral-900 dark:text-neutral-100">
                                            <i class="{{ $item['icon'] }}"></i>
                                        </span>
                                        <span>{{ $item['label'] }}</span>
                                    </span>
                                    <i class="fa-solid fa-chevron-right text-xs text-neutral-400 dark:text-neutral-500"></i>
                                </a>
                            @endforeach
                        </div>

                        <div class="grid gap-2">
                            <p class="px-1 text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 dark:text-neutral-500">
                                {{ __('ACCOUNT') }}
                            </p>
                            @foreach ($mobileAccountNavigationItems as $item)
                                <a
                                    href="{{ $item['route'] }}"
                                    wire:navigate
                                    x-on:click="mobileMenuOpen = false"
                                    @class([
                                        'brand-mobile-nav-link flex items-center justify-between gap-3 rounded-xl bg-neutral-100 px-4 py-3 text-sm font-semibold text-neutral-800 transition-all duration-150 active:scale-90 dark:bg-neutral-800 dark:text-white',
                                        'is-active' => request()->routeIs(...$item['patterns']),
                                    ])
                                >
                                    <span class="flex items-center gap-3">
                                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white text-neutral-700 shadow-sm dark:bg-neutral-900 dark:text-neutral-100">
                                            <i class="{{ $item['icon'] }}"></i>
                                        </span>
                                        <span>{{ $item['label'] }}</span>
                                    </span>
                                    <i class="fa-solid fa-chevron-right text-xs text-neutral-400 dark:text-neutral-500"></i>
                                </a>
                            @endforeach
                        </div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm font-semibold text-red-600 transition hover:bg-red-950/40 dark:text-red-400">
                                <span class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-400">
                                        <i class="fa-solid fa-right-from-bracket"></i>
                                    </span>
                                    <span>{{ __('Log out') }}</span>
                                </span>
                                <i class="fa-solid fa-chevron-right text-xs text-red-400"></i>
                            </button>
                        </form>
                    </div>
                @else
                    <div class="border-b border-stone-200 bg-stone-50 px-5 py-4 dark:border-white/10 dark:bg-zinc-900/95">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Welcome to LocalPalengke') }}</p>
                                <p class="mt-2 text-xs leading-6 text-neutral-500 dark:text-zinc-400">
                                    {{ __('Sign in to open your dashboard or create an account to start browsing the market.') }}
                                </p>
                            </div>

                            <button type="button" x-on:click="mobileMenuOpen = false" class="brand-button-secondary active:scale-[0.96] inline-flex h-9 w-9 items-center justify-center p-0" aria-label="{{ __('Close menu') }}">
                                <i class="fa-solid fa-xmark text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-3 p-4">
                        <a href="{{ route('login') }}" class="brand-button-secondary active:scale-[0.96] w-full" wire:navigate x-on:click="mobileMenuOpen = false">
                            {{ __('Log in') }}
                        </a>
                        <a href="{{ route('register') }}" class="brand-button-primary active:scale-[0.96] w-full" wire:navigate x-on:click="mobileMenuOpen = false">
                            {{ __('Create account') }}
                        </a>
                    </div>
                @endauth
            </div>
        </header>

        {{ $slot }}

        @auth
            @if ($mobileBottomNavigationItems !== [])
                <nav
                    x-data
                    x-show="true"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="translate-y-full opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    class="fixed inset-x-0 bottom-0 z-50 border-t border-stone-200 bg-white/95 px-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-2 shadow-[0_-12px_30px_rgba(0,0,0,0.08)] backdrop-blur-xl dark:border-white/10 dark:bg-zinc-900/95 lg:hidden"
                    aria-label="{{ __('Mobile primary navigation') }}"
                >
                    <div @class([
                        'mx-auto grid max-w-xl gap-1',
                        'grid-cols-4' => count($mobileBottomNavigationItems) === 4,
                        'grid-cols-5' => count($mobileBottomNavigationItems) !== 4,
                    ])>
                        @foreach ($mobileBottomNavigationItems as $item)
                            <a
                                href="{{ $item['route'] }}"
                                wire:navigate
                                @class([
                                    'flex min-w-0 flex-col items-center justify-center gap-1 rounded-2xl px-2 py-2 text-[11px] font-semibold transition-all duration-150 active:scale-90',
                                    'bg-[var(--brand-50)] text-[var(--brand-700)] dark:bg-white/10 dark:text-[var(--brand-300)]' => request()->routeIs(...$item['patterns']),
                                    'text-neutral-500 hover:bg-stone-100 hover:text-neutral-900 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-zinc-100' => ! request()->routeIs(...$item['patterns']),
                                ])
                            >
                                <i class="{{ $item['icon'] }} text-sm"></i>
                                <span class="max-w-full truncate">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </nav>
            @endif
        @endauth

        <button
            type="button"
            x-cloak
            x-show="showBackToTop"
            x-transition.opacity
            x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })"
            class="brand-soft-surface fixed bottom-24 right-4 z-50 flex h-11 w-11 items-center justify-center rounded-full border shadow-lg transition hover:-translate-y-0.5 lg:bottom-6"
            aria-label="{{ __('Back to top') }}"
            title="{{ __('Back to top') }}"
        >
            <i class="fa-solid fa-arrow-up text-sm"></i>
        </button>

        @persist('toast')
            <flux:toast.group position="bottom left">
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @if (session()->has('toast.warning'))
            <div x-data x-init="$flux.toast({ variant: 'warning', text: @js(session('toast.warning')) })" class="hidden"></div>
        @endif

        @auth
            <livewire:calls.incoming-call-notification :key="'incoming-call-notification'" />
        @endauth

        @fluxScripts
        <script>
            (() => {
                const ENTER_CLASS = 'suki-page-enter';

                function applyEnter(el) {
                    el.classList.remove(ENTER_CLASS);
                    void el.offsetWidth;
                    el.classList.add(ENTER_CLASS);
                    el.addEventListener('animationend', () => el.classList.remove(ENTER_CLASS), { once: true });
                }

                document.addEventListener('livewire:navigated', () => {
                    const main = document.querySelector('main');

                    if (main) {
                        applyEnter(main);
                    }

                    window.sukiRevealAll?.();
                });

                let bar = null;
                let barHideTimer = null;
                let barSafetyTimer = null;

                document.addEventListener('livewire:navigating', () => {
                    clearTimeout(barHideTimer);
                    clearTimeout(barSafetyTimer);

                    if (!bar) {
                        bar = document.createElement('div');
                        bar.id = 'suki-nprogress-bar';
                        document.body.appendChild(bar);
                    }

                    bar.style.opacity = '1';
                    bar.style.transition = '';
                    bar.style.display = 'block';

                    barSafetyTimer = setTimeout(() => {
                        if (bar) {
                            bar.style.display = 'none';
                        }
                    }, 6000);
                });

                document.addEventListener('livewire:navigated', () => {
                    clearTimeout(barSafetyTimer);

                    if (bar) {
                        bar.style.opacity = '0';
                        bar.style.transition = 'opacity 300ms ease';
                        barHideTimer = setTimeout(() => {
                            if (bar) {
                                bar.style.display = 'none';
                                bar.style.opacity = '';
                                bar.style.transition = '';
                            }
                        }, 320);
                    }
                });
            })();
        </script>
    </body>
</html>
