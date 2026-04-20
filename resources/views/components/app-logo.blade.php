@props([
    'sidebar' => false,
])

<a {{ $attributes->class('flex items-center gap-3') }}>
    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-600 text-sm font-bold text-white shadow-sm">
        S
    </span>

    <span class="{{ $sidebar ? 'min-w-0 in-data-flux-sidebar-collapsed-desktop:hidden' : 'min-w-0' }}">
        <span class="brand-serif block text-lg font-bold text-neutral-900">SukiMarket</span>
        <span class="block text-[11px] uppercase tracking-[0.28em] text-neutral-400">Digital palengke</span>
    </span>
</a>
