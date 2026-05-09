@php($dailyRevenue = $this->dailyRevenue)

<div wire:poll.60s="refreshSalesData" class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="brand-panel p-6 sm:p-8">
        <a href="{{ route('vendor.dashboard') }}" wire:navigate class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-neutral-500 transition hover:text-neutral-900 dark:text-zinc-400 dark:hover:text-zinc-100">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            {{ __('Return to Dashboard') }}
        </a>
        <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Sales overview') }}</h1>
                <p class="mt-4 max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
                    {{ __('Review fulfilled-order revenue, compare recent periods, and see which products are driving the strongest returns for your stall.') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                @foreach ([
                    'week' => __('This Week'),
                    'month' => __('This Month'),
                    'all' => __('All Time'),
                ] as $value => $label)
                    <button
                        type="button"
                        wire:click="$set('period', '{{ $value }}')"
                        class="inline-flex items-center rounded-full border px-4 py-2 text-sm font-semibold transition-all duration-150 active:scale-[0.97] {{ $period === $value ? 'text-white' : 'bg-white text-neutral-700 dark:bg-zinc-900 dark:text-zinc-100' }}"
                        style="{{ $period === $value
                            ? 'border-color: transparent; background-color: var(--brand-600);'
                            : 'border-color: rgb(231 229 228);' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </section>

    <section class="grid gap-4 md:grid-cols-3">
        @foreach ([
            ['label' => __('Total revenue'), 'value' => $this->peso($this->summary['total_revenue'])],
            ['label' => __('Orders fulfilled'), 'value' => number_format($this->summary['total_orders'])],
            ['label' => __('Average order value'), 'value' => $this->peso($this->summary['average_order_value'])],
        ] as $stat)
            <article class="brand-panel-muted suki-reveal p-5" style="transition-delay: {{ $loop->index * 80 }}ms">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ $stat['label'] }}</p>
                <p class="mt-3 text-3xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $stat['value'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
        <article class="brand-panel suki-reveal overflow-hidden p-5" style="transition-delay: 100ms">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Revenue trend') }}</p>
                    <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Delivered order revenue') }}</h2>
                </div>

                <p class="text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $this->peso($dailyRevenue['total']) }}</p>
            </div>

            @if (array_sum($dailyRevenue['series']) === 0.0)
                <div class="mt-6 flex h-[280px] items-center justify-center rounded-[1.5rem] border border-dashed border-stone-200 text-sm text-neutral-500 dark:border-white/10 dark:text-zinc-400">
                    {{ __('No fulfilled orders yet for this period') }}
                </div>
            @else
                <x-chart-canvas
                    type="line"
                    :labels="$dailyRevenue['labels']"
                    :series="$dailyRevenue['series']"
                    :colors="['brand-600', 'rgba(5,150,105,0.08)']"
                    formatter="currency"
                    height="280px"
                    :aria-label="__('Area chart showing delivered order revenue for the selected period')"
                />
            @endif
        </article>

        <article class="brand-panel suki-reveal p-6" style="transition-delay: 200ms">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Top products') }}</p>
                    <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Best earners') }}</h2>
                </div>
            </div>

            <div class="mt-6 space-y-3">
                @forelse ($this->topProducts as $productPerformance)
                    <article class="brand-panel-muted flex items-center gap-4 p-4 transition-all duration-200 hover:shadow-md" wire:key="vendor-top-product-{{ $productPerformance->product_id }}">
                        <div class="h-14 w-14 overflow-hidden rounded-[1.25rem] border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-800">
                            <img
                                src="{{ $productPerformance->product?->image_url ?? 'https://placehold.co/112x112/e7e5e4/9ca3af?text=Item' }}"
                                alt="{{ $productPerformance->product?->name ?? __('Deleted product') }}"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            >
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-neutral-900 dark:text-zinc-100">
                                {{ $productPerformance->product?->name ?? __('Deleted product') }}
                            </p>
                            <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">
                                {{ trans_choice(':count unit sold|:count units sold', (int) $productPerformance->total_qty, ['count' => (int) $productPerformance->total_qty]) }}
                            </p>
                        </div>

                        <div class="text-right">
                            <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ $this->peso((float) $productPerformance->total_revenue) }}</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Revenue') }}</p>
                        </div>
                    </article>
                @empty
                    <div class="rounded-[1.75rem] border border-dashed border-stone-200 px-6 py-12 text-center dark:border-white/10">
                        <p class="text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                            {{ __('Your top products will appear here once delivered orders start coming in.') }}
                        </p>
                    </div>
                @endforelse
            </div>
        </article>
    </section>
</div>
