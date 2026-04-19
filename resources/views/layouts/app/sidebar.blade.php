<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    @php
        $primaryLabel = match (auth()->user()->effectiveMarketplaceRole()) {
            \App\Enums\UserRole::Customer => __('Storefront'),
            \App\Enums\UserRole::Vendor => __('Dashboard'),
            \App\Enums\UserRole::Admin => __('Dashboard'),
        };
    @endphp
    <body class="min-h-screen bg-neutral-50 dark:bg-neutral-950">

        <flux:sidebar 
            sticky 
            collapsible 
            x-data 
            x-on:click="if (document.documentElement.hasAttribute('data-flux-sidebar-collapsed-desktop')) { document.getElementById('sidebar-toggle-btn').click(); }"
            class="border-e border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900 !cursor-default !transition-none [&_*]:!transition-none [&_[data-flux-sidebar-resizer]]:hidden [&>div[class*='resize']]:hidden in-data-flux-sidebar-collapsed-desktop:![overflow-y:hidden] in-data-flux-sidebar-collapsed-desktop:[&::-webkit-scrollbar]:hidden in-data-flux-sidebar-collapsed-desktop:[scrollbar-width:none] in-data-flux-sidebar-collapsed-desktop:[-ms-overflow-style:none]"
        >
            <div class="flex h-[60px] shrink-0 items-center justify-between gap-2 border-b border-neutral-200 px-4 dark:border-neutral-800 in-data-flux-sidebar-collapsed-desktop:justify-center in-data-flux-sidebar-collapsed-desktop:px-0 !transition-none">
                
                <a href="{{ route('dashboard') }}" wire:navigate class="flex min-w-0 items-center gap-2.5 in-data-flux-sidebar-collapsed-desktop:pointer-events-none in-data-flux-sidebar-collapsed-desktop:mx-auto">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-600 text-xs font-bold leading-none text-white">
                        S
                    </span>
                    <span class="truncate text-sm font-semibold text-neutral-900 dark:text-white in-data-flux-sidebar-collapsed-desktop:hidden">
                        SukiMarket
                    </span>
                </a>
                
                <flux:sidebar.collapse id="sidebar-toggle-btn" class="in-data-flux-sidebar-collapsed-desktop:hidden" />
            </div>

            <flux:sidebar.nav>
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ $primaryLabel }}
                    </flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                    {{ __('Repository') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                    {{ __('Documentation') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <flux:header class="lg:hidden border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
            <flux:sidebar.toggle class="lg:hidden"/>
            <span class="ml-2 text-sm font-semibold text-neutral-900 dark:text-white">SukiMarket</span>
            <flux:spacer />
            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />
                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>
                    <flux:menu.separator />
                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>