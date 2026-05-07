@php
    $revenueChartData = $this->revenueChartData;
    $orderVolumeChartData = $this->orderVolumeChartData;
    $vendorStatusChartData = $this->vendorStatusChartData;
    $userRegistrationChartData = $this->userRegistrationChartData;
@endphp

<div wire:poll.60s="refreshDashboard" class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <span class="brand-kicker">{{ __('Admin overview') }}</span>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">
            {{ __('Operational overview') }}
        </h1>
        <p class="max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Monitor marketplace activity, review pending approvals, and keep a close eye on orders and seller health across SukiMarket.') }}
        </p>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->kpis as $card)
            <article class="brand-panel overflow-hidden p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">
                            {{ $card['label'] }}
                        </span>
                        <p class="mt-3 text-3xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $card['value'] }}</p>
                        <p class="mt-2 text-sm font-medium {{ $card['delta_class'] }}">{{ $card['delta'] }}</p>
                    </div>

                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[var(--brand-50)] text-[var(--brand-700)] dark:bg-[color:oklch(from_var(--brand-500)_l_c_h_/_0.14)] dark:text-[var(--brand-300)]">
                        <i class="{{ $card['icon'] }} text-lg"></i>
                    </span>
                </div>
            </article>
        @endforeach
    </section>

    <section
        wire:ignore
        x-data="{
            feed: [],
            init() {
                if (!window.Echo) return;
                window.Echo.channel('admin.audit').listen('.AuditLogCreated', (entry) => {
                    this.feed.unshift(entry);
                    if (this.feed.length > 30) this.feed.pop();
                });
            },
        }"
        class="brand-panel flex flex-col gap-5 p-6 sm:p-8"
    >
        <div>
            <span class="brand-kicker">{{ __('Live activity') }}</span>
            <h2 class="brand-serif mt-3 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Platform feed') }}</h2>
        </div>

        <div class="min-h-[200px] space-y-2 overflow-y-auto max-h-72 pr-1">
            <template x-for="(entry, index) in feed" :key="entry.id ?? index">
                <div
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="flex items-start gap-3 rounded-xl border border-stone-100 bg-stone-50/70 px-4 py-2.5 dark:border-white/5 dark:bg-white/[3%]"
                >
                    <i :class="entry.icon + ' mt-0.5 text-sm text-[var(--brand-600)] dark:text-[var(--brand-400)]'"></i>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm text-neutral-800 dark:text-zinc-200">
                            <span class="font-semibold" x-text="entry.user_name"></span>
                            <span x-text="entry.description"></span>
                        </p>
                        <p class="text-xs text-neutral-400 dark:text-zinc-500" x-text="entry.label"></p>
                    </div>
                </div>
            </template>

            <p x-show="feed.length === 0" class="py-10 text-center text-sm text-neutral-400 dark:text-zinc-500">
                {{ __('Waiting for activity...') }}
            </p>
        </div>

        <a href="{{ route('admin.audit') }}" wire:navigate class="brand-button-secondary self-start text-sm">
            {{ __('View full audit log') }}
        </a>
    </section>

    <section class="grid gap-6 xl:grid-cols-2">
        <article class="brand-panel overflow-hidden p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Platform revenue') }}</p>
                    <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Last 30 days') }}</h2>
                </div>

                <p class="text-xl font-semibold text-neutral-900 dark:text-zinc-100">&#8369;{{ number_format($revenueChartData['total'], 2) }}</p>
            </div>

            @if (array_sum($revenueChartData['series']) === 0.0)
                <div class="mt-6 flex h-[260px] items-center justify-center rounded-[1.5rem] border border-dashed border-stone-200 text-sm text-neutral-500 dark:border-white/10 dark:text-zinc-400">
                    {{ __('No data yet for this period') }}
                </div>
            @else
                <x-chart-canvas
                    type="line"
                    :labels="$revenueChartData['labels']"
                    :series="$revenueChartData['series']"
                    :colors="['brand-600', 'rgba(5,150,105,0.08)']"
                    formatter="currency"
                    height="260px"
                    :aria-label="__('Area chart showing platform revenue over the last 30 days')"
                />
            @endif
        </article>

        <article class="brand-panel overflow-hidden p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Orders') }}</p>
                    <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Daily volume (14 days)') }}</h2>
                </div>

                <p class="text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ number_format($orderVolumeChartData['total']) }}</p>
            </div>

            @if (array_sum($orderVolumeChartData['series']) === 0)
                <div class="mt-6 flex h-[260px] items-center justify-center rounded-[1.5rem] border border-dashed border-stone-200 text-sm text-neutral-500 dark:border-white/10 dark:text-zinc-400">
                    {{ __('No data yet for this period') }}
                </div>
            @else
                <x-chart-canvas
                    type="bar"
                    :labels="$orderVolumeChartData['labels']"
                    :series="$orderVolumeChartData['series']"
                    :colors="['brand-600']"
                    formatter="number"
                    height="260px"
                    :max-ticks="7"
                    :aria-label="__('Bar chart showing order volume over the last 14 days')"
                />
            @endif
        </article>

        <article class="brand-panel overflow-hidden p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Vendors') }}</p>
                    <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Application status') }}</h2>
                </div>

                <p class="text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ number_format($vendorStatusChartData['total']) }}</p>
            </div>

            @if ($vendorStatusChartData['total'] === 0)
                <div class="mt-6 flex h-[220px] items-center justify-center rounded-[1.5rem] border border-dashed border-stone-200 text-sm text-neutral-500 dark:border-white/10 dark:text-zinc-400">
                    {{ __('No data yet for this period') }}
                </div>
            @else
                <x-chart-canvas
                    type="doughnut"
                    :labels="$vendorStatusChartData['labels']"
                    :series="$vendorStatusChartData['series']"
                    :colors="$vendorStatusChartData['colors']"
                    formatter="number"
                    height="220px"
                    :aria-label="__('Donut chart showing vendor application statuses')"
                />
            @endif
        </article>

        <article class="brand-panel overflow-hidden p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Users') }}</p>
                    <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('New registrations (30 days)') }}</h2>
                </div>

                <p class="text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ number_format($userRegistrationChartData['total']) }}</p>
            </div>

            @if (array_sum($userRegistrationChartData['series']) === 0)
                <div class="mt-6 flex h-[260px] items-center justify-center rounded-[1.5rem] border border-dashed border-stone-200 text-sm text-neutral-500 dark:border-white/10 dark:text-zinc-400">
                    {{ __('No data yet for this period') }}
                </div>
            @else
                <x-chart-canvas
                    type="bar"
                    :labels="$userRegistrationChartData['labels']"
                    :series="$userRegistrationChartData['series']"
                    :colors="['brand-400']"
                    formatter="number"
                    height="260px"
                    :aria-label="__('Bar chart showing new user registrations over the last 30 days')"
                />
            @endif
        </article>
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
        <article class="brand-panel p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Pending approvals') }}</p>
                    <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Newest vendor applications') }}</h2>
                </div>

                <a href="{{ route('admin.vendors') }}" wire:navigate class="brand-button-secondary">
                    {{ __('View all') }}
                </a>
            </div>

            <div class="mt-6 space-y-3">
                @forelse ($this->pendingApprovals as $vendorProfile)
                    <article class="brand-panel-muted flex items-center justify-between gap-4 p-4">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-neutral-900 dark:text-zinc-100">{{ $vendorProfile->store_name }}</p>
                            <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">{{ $vendorProfile->user->name }}</p>
                            <p class="mt-2 text-xs uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ $vendorProfile->created_at->diffForHumans() }}</p>
                        </div>

                        <a href="{{ route('admin.vendors.show', $vendorProfile) }}" wire:navigate class="brand-button-secondary">
                            {{ __('Review') }}
                        </a>
                    </article>
                @empty
                    <div class="rounded-[1.5rem] border border-dashed border-stone-200 p-8 text-center text-sm text-neutral-500 dark:border-white/10 dark:text-zinc-400">
                        {{ __('No pending vendor applications right now.') }}
                    </div>
                @endforelse
            </div>
        </article>

        <article class="brand-panel p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Recent orders') }}</p>
                    <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Latest activity across vendors') }}</h2>
                </div>

                <a href="{{ route('admin.orders') }}" wire:navigate class="brand-button-secondary">
                    {{ __('View all') }}
                </a>
            </div>

            <div class="mt-6 space-y-3">
                @forelse ($this->recentOrders as $order)
                    <article class="brand-panel-muted flex flex-col gap-4 p-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <p class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $order->customer->name }}</p>
                            <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">{{ $order->vendor->store_name }}</p>
                            <p class="mt-2 text-xs uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ $order->created_at->format('M j, Y g:i A') }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <span class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">&#8369;{{ number_format((float) $order->total_amount, 2) }}</span>
                            <span @class([
                                'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]',
                                'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => $order->order_status === \App\Enums\OrderStatus::Pending,
                                'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300' => $order->order_status === \App\Enums\OrderStatus::Confirmed,
                                'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300' => $order->order_status === \App\Enums\OrderStatus::Preparing,
                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $order->order_status === \App\Enums\OrderStatus::Ready,
                                'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-300' => $order->order_status === \App\Enums\OrderStatus::Delivered,
                                'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' => $order->order_status === \App\Enums\OrderStatus::Cancelled,
                            ])>
                                {{ ucfirst($order->order_status->value) }}
                            </span>
                        </div>
                    </article>
                @empty
                    <div class="rounded-[1.5rem] border border-dashed border-stone-200 p-8 text-center text-sm text-neutral-500 dark:border-white/10 dark:text-zinc-400">
                        {{ __('No orders have been placed yet.') }}
                    </div>
                @endforelse
            </div>
        </article>
    </section>

    <section class="brand-panel p-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Platform health') }}</p>
                <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Marketplace pulse') }}</h2>
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => __('Active products'), 'value' => $this->platformHealth['active_products']],
                ['label' => __('Active storefronts'), 'value' => $this->platformHealth['active_vendor_storefronts']],
                ['label' => __('Unread messages'), 'value' => $this->platformHealth['unread_messages']],
                ['label' => __('Notifications sent today'), 'value' => $this->platformHealth['notifications_sent_today']],
            ] as $stat)
                <article class="brand-panel-muted p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ $stat['label'] }}</p>
                    <p class="mt-3 text-2xl font-semibold text-neutral-900 dark:text-zinc-100">{{ number_format($stat['value']) }}</p>
                </article>
            @endforeach
        </div>
    </section>
</div>
