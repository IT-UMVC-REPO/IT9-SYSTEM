<div class="flex items-center gap-1 overflow-x-auto rounded-2xl border border-stone-200 bg-stone-100 p-1 dark:border-white/10 dark:bg-zinc-800/60">
    @foreach ([
        ['route' => 'vendor.products', 'match' => ['vendor.products', 'vendor.products.*'], 'icon' => 'fa-solid fa-boxes-stacked', 'label' => __('Products')],
        ['route' => 'vendor.stocks', 'match' => ['vendor.stocks'], 'icon' => 'fa-solid fa-warehouse', 'label' => __('Stock Manager')],
        ['route' => 'vendor.orders', 'match' => ['vendor.orders', 'vendor.orders.*'], 'icon' => 'fa-solid fa-bag-shopping', 'label' => __('Orders')],
        ['route' => 'vendor.sales', 'match' => ['vendor.sales'], 'icon' => 'fa-solid fa-chart-line', 'label' => __('Sales')],
    ] as $tab)
        <a href="{{ route($tab['route']) }}" wire:navigate
            @class([
                'flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-sm font-semibold transition',
                'bg-white text-neutral-900 shadow-sm dark:bg-zinc-900 dark:text-zinc-100' => request()->routeIs(...$tab['match']),
                'text-neutral-500 hover:text-neutral-900 dark:text-zinc-400 dark:hover:text-zinc-100' => ! request()->routeIs(...$tab['match']),
            ])>
            <i class="{{ $tab['icon'] }} text-xs"></i>
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
