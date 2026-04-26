<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\VendorStatus;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admin dashboard')] class extends Component {
    public function refreshDashboard(): void
    {
        $this->dispatchChartUpdates();
    }

    #[Computed]
    public function kpis(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $yesterdayStart = now()->subDay()->startOfDay();
        $yesterdayEnd = now()->subDay()->endOfDay();

        $totalUsers = User::query()->count();
        $pendingApplications = VendorProfile::query()
            ->where('status', VendorStatus::Pending)
            ->count();
        $ordersToday = Order::query()
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();
        $revenueToday = (float) Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$todayStart, $todayEnd])
            ->sum('amount');

        $userDelta = User::query()->whereBetween('created_at', [$todayStart, $todayEnd])->count()
            - User::query()->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->count();

        $pendingDelta = VendorProfile::query()
            ->where('status', VendorStatus::Pending)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count()
            - VendorProfile::query()
                ->where('status', VendorStatus::Pending)
                ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
                ->count();

        $ordersDelta = $ordersToday - Order::query()
            ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
            ->count();

        $revenueDelta = $revenueToday - (float) Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$yesterdayStart, $yesterdayEnd])
            ->sum('amount');

        return [
            [
                'icon' => 'fa-solid fa-users',
                'label' => __('Total users'),
                'value' => number_format($totalUsers),
                'delta' => $this->signedCount($userDelta).' '.__('signups vs yesterday'),
                'delta_class' => $this->deltaClass($userDelta),
            ],
            [
                'icon' => 'fa-solid fa-store',
                'label' => __('Pending vendor applications'),
                'value' => number_format($pendingApplications),
                'delta' => $this->signedCount($pendingDelta).' '.__('submissions vs yesterday'),
                'delta_class' => $this->deltaClass($pendingDelta),
            ],
            [
                'icon' => 'fa-solid fa-basket-shopping',
                'label' => __('Orders today'),
                'value' => number_format($ordersToday),
                'delta' => $this->signedCount($ordersDelta).' '.__('vs yesterday'),
                'delta_class' => $this->deltaClass($ordersDelta),
            ],
            [
                'icon' => 'fa-solid fa-wallet',
                'label' => __('Revenue today'),
                'value' => $this->peso($revenueToday),
                'delta' => $this->signedPeso($revenueDelta).' '.__('vs yesterday'),
                'delta_class' => $this->deltaClass($revenueDelta),
            ],
        ];
    }

    #[Computed]
    public function pendingApprovals(): Collection
    {
        return VendorProfile::query()
            ->with('user:id,name')
            ->where('status', VendorStatus::Pending)
            ->latest('created_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function recentOrders(): Collection
    {
        return Order::query()
            ->with([
                'customer:id,name',
                'vendor:id,store_name',
            ])
            ->latest('created_at')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function platformHealth(): array
    {
        return [
            'active_products' => Product::query()->where('status', ProductStatus::Active)->count(),
            'active_vendor_storefronts' => VendorProfile::query()->where('status', VendorStatus::Approved)->count(),
            'unread_messages' => Message::query()->where('is_read', false)->count(),
            'notifications_sent_today' => Notification::query()
                ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
                ->count(),
        ];
    }

    #[Computed]
    public function revenueChartData(): array
    {
        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();

        $payments = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$start, $end])
            ->get(['amount', 'paid_at']);

        $chart = $this->buildDailySeries(
            $start,
            $end,
            $payments,
            fn (Payment $payment): ?CarbonInterface => $payment->paid_at,
            fn (Payment $payment): float => (float) $payment->amount,
        );

        return [
            ...$chart,
            'total' => round(array_sum($chart['series']), 2),
        ];
    }

    #[Computed]
    public function orderVolumeChartData(): array
    {
        $start = now()->subDays(13)->startOfDay();
        $end = now()->endOfDay();

        $orders = Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at']);

        $chart = $this->buildDailySeries(
            $start,
            $end,
            $orders,
            fn (Order $order): ?CarbonInterface => Carbon::parse($order->created_at),
            fn (): int => 1,
        );

        return [
            ...$chart,
            'series' => array_map(static fn ($value): int => (int) round($value), $chart['series']),
            'total' => $orders->count(),
        ];
    }

    #[Computed]
    public function vendorStatusChartData(): array
    {
        $counts = VendorProfile::query()
            ->get(['status'])
            ->countBy(fn (VendorProfile $vendorProfile): string => $vendorProfile->status->value);

        $series = [
            (int) ($counts[VendorStatus::Approved->value] ?? 0),
            (int) ($counts[VendorStatus::Pending->value] ?? 0),
            (int) ($counts[VendorStatus::Rejected->value] ?? 0),
        ];

        return [
            'labels' => ['Approved', 'Pending', 'Rejected'],
            'series' => $series,
            'colors' => ['#16a34a', '#d97706', '#dc2626'],
            'total' => array_sum($series),
        ];
    }

    #[Computed]
    public function userRegistrationChartData(): array
    {
        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();

        $users = User::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at']);

        $chart = $this->buildDailySeries(
            $start,
            $end,
            $users,
            fn (User $user): ?CarbonInterface => Carbon::parse($user->created_at),
            fn (): int => 1,
        );

        return [
            ...$chart,
            'series' => array_map(static fn ($value): int => (int) round($value), $chart['series']),
            'total' => $users->count(),
        ];
    }

    /**
     * @template TRecord of object
     *
     * @param  Collection<int, TRecord>  $records
     * @param  callable(TRecord): ?CarbonInterface  $dateResolver
     * @param  callable(TRecord): float|int  $valueResolver
     * @return array{labels: array<int, string>, series: array<int, float>}
     */
    private function buildDailySeries(CarbonInterface $start, CarbonInterface $end, Collection $records, callable $dateResolver, callable $valueResolver): array
    {
        $days = collect();
        $cursor = Carbon::instance($start);
        $endDate = Carbon::instance($end);

        while ($cursor->lte($endDate)) {
            $days->push($cursor->toDateString());
            $cursor->addDay();
        }

        /** @var array<string, float> $totals */
        $totals = $records->reduce(function (array $carry, object $record) use ($dateResolver, $valueResolver): array {
            $date = $dateResolver($record);

            if ($date === null) {
                return $carry;
            }

            $day = $date->toDateString();
            $carry[$day] = round(($carry[$day] ?? 0) + (float) $valueResolver($record), 2);

            return $carry;
        }, []);

        return [
            'labels' => $days->map(fn (string $day): string => Carbon::parse($day)->format('M j'))->all(),
            'series' => $days->map(fn (string $day): float => round((float) ($totals[$day] ?? 0), 2))->all(),
        ];
    }

    private function dispatchChartUpdates(): void
    {
        $this->dispatch('chart-data-updated:admin-revenue', data: $this->revenueChartData);
        $this->dispatch('chart-data-updated:admin-orders', data: $this->orderVolumeChartData);
        $this->dispatch('chart-data-updated:admin-vendor-status', data: $this->vendorStatusChartData);
        $this->dispatch('chart-data-updated:admin-user-registrations', data: $this->userRegistrationChartData);
    }

    private function signedCount(int|float $value): string
    {
        $formatted = number_format(abs($value));

        return $value > 0
            ? '+'.$formatted
            : ($value < 0 ? '-'.$formatted : '0');
    }

    private function signedPeso(int|float $value): string
    {
        $formatted = $this->peso(abs($value));

        return $value > 0
            ? '+'.$formatted
            : ($value < 0 ? '-'.$formatted : $this->peso(0));
    }

    private function deltaClass(int|float $value): string
    {
        if ($value > 0) {
            return 'text-emerald-600 dark:text-emerald-300';
        }

        if ($value < 0) {
            return 'text-rose-600 dark:text-rose-300';
        }

        return 'text-neutral-500 dark:text-zinc-400';
    }

    private function peso(int|float $value): string
    {
        return sprintf("\u{20B1}%s", number_format((float) $value, 2));
    }
}; ?>

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

    <section class="grid gap-6 xl:grid-cols-2">
        <article class="brand-panel overflow-hidden p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Platform revenue') }}</p>
                    <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Last 30 days') }}</h2>
                </div>

                <p class="text-xl font-semibold text-neutral-900 dark:text-zinc-100">&#8369;{{ number_format($revenueChartData['total'], 2) }}</p>
            </div>

            <figure
                x-data="window.createSukiApexChart({
                    type: 'area',
                    height: 260,
                    seriesName: 'Revenue',
                    data: @js($revenueChartData),
                    currency: true
                })"
                x-init="init()"
                x-on:chart-data-updated:admin-revenue.window="update($event.detail.data)"
                class="mt-6 overflow-hidden"
            >
                <figcaption class="sr-only">{{ __('Area chart showing platform revenue over the last 30 days.') }}</figcaption>
                <script type="application/json" id="admin-revenue-chart-data" x-ref="data">@json($revenueChartData)</script>

                @if (array_sum($revenueChartData['series']) === 0.0)
                    <div class="flex h-[260px] items-center justify-center rounded-[1.5rem] border border-dashed border-stone-200 text-sm text-neutral-500 dark:border-white/10 dark:text-zinc-400">
                        {{ __('No data yet for this period') }}
                    </div>
                @else
                    <x-chart
                        id="admin-revenue-chart"
                        x-ref="canvas"
                        :height="260"
                        role="img"
                        :aria-label="__('Area chart showing platform revenue over the last 30 days')"
                    />
                @endif
            </figure>
        </article>

        <article class="brand-panel overflow-hidden p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Orders') }}</p>
                    <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Daily volume (14 days)') }}</h2>
                </div>

                <p class="text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ number_format($orderVolumeChartData['total']) }}</p>
            </div>

            <figure
                x-data="window.createSukiApexChart({
                    type: 'bar',
                    height: 220,
                    seriesName: 'Orders',
                    data: @js($orderVolumeChartData),
                    currency: false
                })"
                x-init="init()"
                x-on:chart-data-updated:admin-orders.window="update($event.detail.data)"
                class="mt-6 overflow-hidden"
            >
                <figcaption class="sr-only">{{ __('Bar chart showing daily order volume over the last 14 days.') }}</figcaption>
                <script type="application/json" id="admin-orders-chart-data" x-ref="data">@json($orderVolumeChartData)</script>

                @if (array_sum($orderVolumeChartData['series']) === 0)
                    <div class="flex h-[220px] items-center justify-center rounded-[1.5rem] border border-dashed border-stone-200 text-sm text-neutral-500 dark:border-white/10 dark:text-zinc-400">
                        {{ __('No data yet for this period') }}
                    </div>
                @else
                    <x-chart
                        id="admin-orders-chart"
                        x-ref="canvas"
                        :height="220"
                        role="img"
                        :aria-label="__('Bar chart showing order volume over the last 14 days')"
                    />
                @endif
            </figure>
        </article>

        <article class="brand-panel overflow-hidden p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Vendors') }}</p>
                    <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Application status') }}</h2>
                </div>

                <p class="text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ number_format($vendorStatusChartData['total']) }}</p>
            </div>

            <figure
                x-data="window.createSukiApexChart({
                    type: 'donut',
                    height: 220,
                    data: @js($vendorStatusChartData),
                    colors: @js($vendorStatusChartData['colors'])
                })"
                x-init="init()"
                x-on:chart-data-updated:admin-vendor-status.window="update($event.detail.data)"
                class="mt-6 overflow-hidden"
            >
                <figcaption class="sr-only">{{ __('Donut chart showing the distribution of vendor application statuses.') }}</figcaption>
                <script type="application/json" id="admin-vendor-status-chart-data" x-ref="data">@json($vendorStatusChartData)</script>

                @if ($vendorStatusChartData['total'] === 0)
                    <div class="flex h-[220px] items-center justify-center rounded-[1.5rem] border border-dashed border-stone-200 text-sm text-neutral-500 dark:border-white/10 dark:text-zinc-400">
                        {{ __('No data yet for this period') }}
                    </div>
                @else
                    <x-chart
                        id="admin-vendor-status-chart"
                        x-ref="canvas"
                        :height="220"
                        role="img"
                        :aria-label="__('Donut chart showing vendor application statuses')"
                    />
                @endif
            </figure>
        </article>

        <article class="brand-panel overflow-hidden p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Users') }}</p>
                    <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('New registrations (30 days)') }}</h2>
                </div>

                <p class="text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ number_format($userRegistrationChartData['total']) }}</p>
            </div>

            <figure
                x-data="window.createSukiApexChart({
                    type: 'bar',
                    height: 220,
                    seriesName: 'Users',
                    data: @js($userRegistrationChartData),
                    colors: ['var(--brand-400)'],
                    currency: false
                })"
                x-init="init()"
                x-on:chart-data-updated:admin-user-registrations.window="update($event.detail.data)"
                class="mt-6 overflow-hidden"
            >
                <figcaption class="sr-only">{{ __('Bar chart showing new user registrations over the last 30 days.') }}</figcaption>
                <script type="application/json" id="admin-user-registrations-chart-data" x-ref="data">@json($userRegistrationChartData)</script>

                @if (array_sum($userRegistrationChartData['series']) === 0)
                    <div class="flex h-[220px] items-center justify-center rounded-[1.5rem] border border-dashed border-stone-200 text-sm text-neutral-500 dark:border-white/10 dark:text-zinc-400">
                        {{ __('No data yet for this period') }}
                    </div>
                @else
                    <x-chart
                        id="admin-user-registrations-chart"
                        x-ref="canvas"
                        :height="220"
                        role="img"
                        :aria-label="__('Bar chart showing new user registrations over the last 30 days')"
                    />
                @endif
            </figure>
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
                                'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => $order->order_status === OrderStatus::Pending,
                                'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300' => $order->order_status === OrderStatus::Confirmed,
                                'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300' => $order->order_status === OrderStatus::Preparing,
                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $order->order_status === OrderStatus::Ready,
                                'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-300' => $order->order_status === OrderStatus::Delivered,
                                'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' => $order->order_status === OrderStatus::Cancelled,
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

@once
    <script data-navigate-once>
        if (!window.createSukiApexChart) {
            window.createSukiApexChart = function (config) {
                return {
                    chart: null,
                    currentData: config.data,
                    appearanceListener: null,
                    init() {
                        this.render(this.currentData);

                        this.appearanceListener = () => this.rebuild();
                        window.addEventListener('flux:appearance-changed', this.appearanceListener);
                    },
                    theme() {
                        const isDark = document.documentElement.classList.contains('dark');
                        const styles = getComputedStyle(document.documentElement);

                        return {
                            isDark,
                            brand600: styles.getPropertyValue('--brand-600').trim() || '#059669',
                            brand400: styles.getPropertyValue('--brand-400').trim() || '#34d399',
                            labelColor: isDark ? '#a1a1aa' : '#78716c',
                            gridColor: isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)',
                            tooltipTheme: isDark ? 'dark' : 'light',
                        };
                    },
                    hasData(data) {
                        return Array.isArray(data?.series) && data.series.some((value) => Number(value) !== 0);
                    },
                    formatCurrency(value) {
                        return '\u20B1' + Number(value || 0).toLocaleString('en-PH', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        });
                    },
                    render(data) {
                        this.currentData = data;

                        if (!window.ApexCharts || !this.$refs.canvas || !this.hasData(data)) {
                            return;
                        }

                        const theme = this.theme();
                        const colors = config.colors?.length
                            ? config.colors.map((color) => color.startsWith('var(') ? getComputedStyle(document.documentElement).getPropertyValue(color.replace('var(', '').replace(')', '')).trim() || color : color)
                            : [theme.brand600];

                        let options = {
                            chart: {
                                type: config.type,
                                height: config.height,
                                toolbar: { show: false },
                                fontFamily: 'DM Sans, ui-sans-serif, system-ui, sans-serif',
                                animations: { enabled: true, easing: 'easeinout', speed: 400 },
                                background: 'transparent',
                            },
                            colors,
                            dataLabels: { enabled: false },
                            grid: { borderColor: theme.gridColor },
                            tooltip: { theme: theme.tooltipTheme, followCursor: false },
                        };

                        if (config.type === 'area') {
                            options = {
                                ...options,
                                series: [{ name: config.seriesName, data: data.series }],
                                stroke: { curve: 'smooth', width: 2 },
                                fill: {
                                    type: 'gradient',
                                    gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 },
                                },
                                xaxis: {
                                    categories: data.labels,
                                    labels: {
                                        rotate: -30,
                                        style: { colors: theme.labelColor },
                                    },
                                },
                                yaxis: {
                                    labels: {
                                        style: { colors: theme.labelColor },
                                        formatter: (value) => this.formatCurrency(value),
                                    },
                                },
                                tooltip: {
                                    ...options.tooltip,
                                    y: { formatter: (value) => this.formatCurrency(value) },
                                },
                            };
                        }

                        if (config.type === 'bar') {
                            options = {
                                ...options,
                                series: [{ name: config.seriesName, data: data.series }],
                                plotOptions: {
                                    bar: { borderRadius: 6, columnWidth: '55%' },
                                },
                                xaxis: {
                                    categories: data.labels,
                                    labels: { style: { colors: theme.labelColor } },
                                },
                                yaxis: {
                                    labels: {
                                        style: { colors: theme.labelColor },
                                        formatter: (value) => Math.round(value),
                                    },
                                },
                                tooltip: {
                                    ...options.tooltip,
                                    y: {
                                        formatter: (value) => config.currency
                                            ? this.formatCurrency(value)
                                            : `${Math.round(value)} ${config.seriesName.toLowerCase()}`,
                                    },
                                },
                            };
                        }

                        if (config.type === 'donut') {
                            options = {
                                ...options,
                                series: data.series,
                                labels: data.labels,
                                legend: {
                                    position: 'bottom',
                                    fontFamily: 'DM Sans, ui-sans-serif, system-ui, sans-serif',
                                    fontSize: '13px',
                                    labels: { colors: theme.labelColor },
                                },
                                plotOptions: {
                                    pie: {
                                        donut: {
                                            size: '68%',
                                            labels: {
                                                show: true,
                                                total: {
                                                    show: true,
                                                    label: 'Total',
                                                    formatter: (chart) => chart.globals.seriesTotals.reduce((total, value) => total + value, 0),
                                                },
                                            },
                                        },
                                    },
                                },
                                responsive: [{
                                    breakpoint: 768,
                                    options: {
                                        legend: { position: 'bottom' },
                                    },
                                }],
                            };
                        }

                        this.chart = new ApexCharts(this.$refs.canvas, options);
                        this.chart.render();
                    },
                    update(data) {
                        this.currentData = data;

                        if (!this.chart) {
                            this.rebuild();

                            return;
                        }

                        const theme = this.theme();

                        if (config.type === 'donut') {
                            this.chart.updateOptions({
                                labels: data.labels,
                                colors: data.colors ?? config.colors,
                                legend: { labels: { colors: theme.labelColor } },
                                grid: { borderColor: theme.gridColor },
                            });
                            this.chart.updateSeries(data.series);

                            return;
                        }

                        this.chart.updateOptions({
                            colors: config.colors ?? [theme.brand600],
                            grid: { borderColor: theme.gridColor },
                            xaxis: {
                                categories: data.labels,
                                labels: {
                                    rotate: config.type === 'area' ? -30 : 0,
                                    style: { colors: theme.labelColor },
                                },
                            },
                            yaxis: {
                                labels: {
                                    style: { colors: theme.labelColor },
                                    formatter: (value) => config.currency ? this.formatCurrency(value) : Math.round(value),
                                },
                            },
                        });

                        this.chart.updateSeries([{ name: config.seriesName, data: data.series }]);
                    },
                    rebuild() {
                        if (this.chart) {
                            this.chart.destroy();
                            this.chart = null;
                        }

                        if (!this.hasData(this.currentData) || !this.$refs.canvas) {
                            return;
                        }

                        this.$nextTick(() => this.render(this.currentData));
                    },
                };
            };
        }
    </script>
@endonce
