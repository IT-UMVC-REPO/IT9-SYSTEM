@props([
    'sidebar' => false,
])

<a {{ $attributes->class('flex min-w-0 items-center gap-3') }}>
    <span class="brand-logo-badge flex h-10 w-10 items-center justify-center rounded-2xl text-sm font-bold shadow-sm" aria-hidden="true">
        <span class="brand-logo-mark h-7 w-7" style="--suki-logo-mask: url('{{ asset('imgs/sukilogo.png') }}')"></span>
    </span>
    <span class="sr-only">{{ __('SukiMarket') }}</span>

    <span class="{{ $sidebar ? 'min-w-0 in-data-flux-sidebar-collapsed-desktop:hidden' : 'min-w-0' }}">
        <span class="brand-serif block truncate text-lg font-bold text-neutral-900 dark:text-zinc-100">SukiMarket</span>
        <span class="hidden truncate text-[11px] uppercase tracking-[0.16em] text-neutral-400 dark:text-zinc-400 sm:block">{{ __('Videre Est Scire') }}</span>
    </span>
</a>
