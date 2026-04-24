@props([
    'sidebar' => false,
])

<a {{ $attributes->class('flex items-center gap-3') }}>
    <span class="brand-logo-badge flex h-10 w-10 items-center justify-center rounded-2xl text-sm font-bold shadow-sm">
        <img src="{{ asset('imgs/sukilogo.png') }}" alt="SukiMarket Logo" class="h-full w-full object-cover" />
    </span>

    <span class="{{ $sidebar ? 'min-w-0 in-data-flux-sidebar-collapsed-desktop:hidden' : 'min-w-0' }}">
        <span class="brand-serif block text-lg font-bold text-neutral-900 dark:text-zinc-100">SukiMarket</span>
        <span class="block text-[11px] uppercase tracking-[0.28em] text-neutral-400 dark:text-zinc-400">Digital palengke</span>
    </span>
</a>
