@props([
    'openReportsCount' => null,
])

@php
    $tabs = [
        [
            'label' => __('Users'),
            'route' => route('admin.users'),
            'active' => request()->routeIs('admin.users', 'admin.users.*'),
            'icon' => 'fa-solid fa-users',
            'count' => null,
        ],
        [
            'label' => __('Reports'),
            'route' => route('admin.reports'),
            'active' => request()->routeIs('admin.reports', 'admin.reports.*'),
            'icon' => 'fa-solid fa-flag',
            'count' => $openReportsCount,
        ],
    ];
@endphp

<nav class="brand-panel p-2" aria-label="{{ __('User management sections') }}">
    <div class="flex flex-wrap gap-2">
        @foreach ($tabs as $tab)
            <a
                href="{{ $tab['route'] }}"
                wire:navigate
                @class([
                    'inline-flex min-h-11 items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition active:scale-[0.96]',
                    'bg-[var(--brand-600)] text-white shadow-sm' => $tab['active'],
                    'text-neutral-600 hover:bg-stone-100 hover:text-neutral-950 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white' => ! $tab['active'],
                ])
                @if ($tab['active']) aria-current="page" @endif
            >
                <i class="{{ $tab['icon'] }} text-xs"></i>
                <span>{{ $tab['label'] }}</span>

                @if ($tab['count'] !== null)
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs',
                        'bg-white/20 text-white' => $tab['active'],
                        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => ! $tab['active'],
                    ])>
                        {{ number_format((int) $tab['count']) }}
                    </span>
                @endif
            </a>
        @endforeach
    </div>
</nav>
