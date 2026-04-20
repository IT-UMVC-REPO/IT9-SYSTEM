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
            ['label' => __('Settings'), 'route' => route('profile.edit'), 'pattern' => 'profile.*', 'icon' => 'fa-solid fa-gear'],
        ];
    @endphp
    <body class="brand-shell min-h-screen text-neutral-800 antialiased">
        <header class="sticky top-0 z-50 border-b border-stone-200/90 bg-stone-50/95 backdrop-blur-md">
            <nav class="mx-auto flex max-w-[1500px] items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <x-app-logo href="{{ $primaryItem['route'] }}" wire:navigate class="shrink-0" />

                <div class="hidden items-center gap-2 xl:ml-4 lg:flex">
                    @foreach ($navigationItems as $item)
                        <a
                            href="{{ $item['route'] }}"
                            wire:navigate
                            class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition {{ request()->routeIs($item['pattern']) ? 'border-emerald-200 bg-white text-emerald-700 shadow-sm' : 'border-transparent text-neutral-600 hover:border-stone-200 hover:bg-white hover:text-neutral-900' }}"
                        >
                            <i class="{{ $item['icon'] }} text-sm"></i>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="ml-auto hidden items-center gap-3 lg:flex">
                    <span class="inline-flex items-center gap-2 rounded-full border px-3.5 py-2 text-[11px] font-semibold uppercase tracking-[0.22em] {{ $portalClasses }}">
                        <i class="{{ $primaryItem['icon'] }} text-[11px]"></i>
                        {{ $portalLabel }}
                    </span>

                    <x-desktop-user-menu />
                </div>

                <details class="relative ml-auto lg:hidden">
                    <summary class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-2xl border border-stone-200 bg-white text-neutral-700 shadow-sm transition hover:border-emerald-200 hover:text-emerald-700 marker:hidden [&::-webkit-details-marker]:hidden">
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
                                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400">{{ $portalLabel }}</p>
                                <p class="mt-2 text-sm leading-6 text-neutral-600">{{ $portalSummary }}</p>
                            </div>
                        </div>

                        <div class="grid gap-2 p-4">
                            @foreach ($navigationItems as $item)
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
