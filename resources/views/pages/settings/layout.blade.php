@php
    $user = auth()->user();
    $effectiveRole = $user->effectiveMarketplaceRole();
    $roleLabel = match ($effectiveRole) {
        \App\Enums\UserRole::Admin => 'Admin',
        \App\Enums\UserRole::Vendor => 'Vendor',
        \App\Enums\UserRole::Customer => 'Customer',
    };
    $navItems = [
        ['route' => 'profile.edit', 'label' => __('Profile'), 'icon' => 'fa-solid fa-id-card'],
        ['route' => 'security.edit', 'label' => __('Security'), 'icon' => 'fa-solid fa-shield-halved'],
        ['route' => 'appearance.edit', 'label' => __('Appearance'), 'icon' => 'fa-solid fa-circle-half-stroke'],
    ];
@endphp

<div class="grid gap-6 xl:grid-cols-[20rem_minmax(0,1fr)] xl:items-start">
    <div class="-mx-4 overflow-x-auto px-4 pb-2 xl:hidden">
        <nav class="flex min-w-max gap-2" aria-label="{{ __('Settings') }}">
            @foreach ($navItems as $item)
                <a
                    href="{{ route($item['route']) }}"
                    wire:navigate
                    @class([
                        'inline-flex items-center gap-2 rounded-full border px-4 py-2.5 text-sm font-semibold transition',
                        'border-[var(--brand-300)] bg-[var(--brand-50)] text-[var(--brand-700)] dark:border-[var(--brand-500)] dark:bg-[color:oklch(from_var(--brand-500)_l_c_h_/_0.14)] dark:text-[var(--brand-300)]' => request()->routeIs($item['route']),
                        'border-stone-200 bg-white text-neutral-600 hover:border-[var(--brand-300)] hover:text-[var(--brand-700)] dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-300' => ! request()->routeIs($item['route']),
                    ])
                >
                    <i class="{{ $item['icon'] }} text-xs"></i>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </div>

    <aside class="hidden xl:sticky xl:top-24 xl:block">
        <div class="settings-sidebar">
            <div class="rounded-[1.5rem] border border-stone-200/80 bg-stone-50/80 p-5 dark:border-white/10 dark:bg-zinc-900/80">
                <div class="flex items-center gap-4">
                    <x-user-avatar :user="$user" size="xl" />

                    <div class="min-w-0">
                        <p class="truncate text-lg font-semibold text-neutral-900 dark:text-zinc-100">{{ $user->name }}</p>
                        <p class="truncate text-sm text-neutral-500 dark:text-zinc-400">{{ $user->email }}</p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="settings-role-badge">
                        <i class="fa-solid fa-circle-check text-[10px]"></i>
                        {{ $roleLabel }}
                    </span>
                </div>
            </div>

            <div class="mt-5">
                <p class="px-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Settings') }}</p>

                <nav class="mt-3 grid gap-3" aria-label="{{ __('Settings') }}">
                    @foreach ($navItems as $item)
                        <a
                            href="{{ route($item['route']) }}"
                            wire:navigate
                            @class([
                                'settings-nav-item',
                                'is-active' => request()->routeIs($item['route']),
                            ])
                        >
                            <span class="settings-nav-icon">
                                <i class="{{ $item['icon'] }}"></i>
                            </span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </div>
        </div>
    </aside>

    <div class="settings-content-panel p-4 sm:p-8">
        <div class="flex items-start gap-3">
            <span class="brand-soft-surface flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl">
                <i class="fa-solid fa-sliders"></i>
            </span>

            <div>
                <flux:heading>{{ $heading ?? '' }}</flux:heading>
                <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>
            </div>
        </div>

        <div class="mt-8 space-y-6">
            {{ $slot }}
        </div>
    </div>
</div>
