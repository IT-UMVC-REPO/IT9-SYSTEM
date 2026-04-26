<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\VendorStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\VendorProfile;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Vendor Sales')] class extends Component
{
    #[Url(except: 'month')]
    public string $period = 'month';

    public function mount(): void
    {
        if (! $this->hasApprovedVendorProfile()) {
            $this->redirectRoute('customer.dashboard', navigate: true);
        }
    }

    public function updatedPeriod(): void
    {
        unset($this->summary);
        unset($this->topProducts);
        unset($this->dailyRevenue);
    }

    public function refreshSalesData(): void
    {
        unset($this->summary);
        unset($this->topProducts);
        unset($this->dailyRevenue);
    }

    #[Computed]
    public function vendorProfile(): VendorProfile
    {
        $vendorProfile = auth()->user()->vendorProfile;

        abort_if($vendorProfile === null || $vendorProfile->status !== VendorStatus::Approved, 403);

        return $vendorProfile;
    }

    /**
     * @return array{total_revenue: float, total_orders: int, average_order_value: float}
     */
    #[Computed]
    public function summary(): array
    {
        $ordersQuery = $this->deliveredOrdersQuery();
        $paymentsQuery = $this->paidPaymentsQuery();

        $totalOrders = (clone $ordersQuery)->count();
        $totalRevenue = (float) (clone $paymentsQuery)->sum('amount');

        return [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'average_order_value' => $totalOrders > 0
                ? round($totalRevenue / $totalOrders, 2)
                : 0.0,
        ];
    }

    #[Computed]
    public function topProducts(): Collection
    {
        return OrderItem::query()
            ->whereHas('order', function ($query): void {
                $query
                    ->where('vendor_id', $this->vendorProfile->getKey())
                    ->where('order_status', OrderStatus::Delivered);

                if ($this->periodStart() !== null) {
                    $query->where('created_at', '>=', $this->periodStart());
                }
            })
            ->with('product:id,name,image,price')
            ->select([
                'product_id',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(quantity * unit_price) as total_revenue'),
            ])
            ->groupBy('product_id')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();
    }

    /**
     * @return array{labels: array<int, string>, series: array<int, float>, total: float}
     */
    #[Computed]
    public function dailyRevenue(): array
    {
        $start = $this->chartStart();
        $end = now()->endOfDay();

        $payments = $this->paidPaymentsQuery()
            ->whereBetween('paid_at', [$start, $end])
            ->get(['amount', 'paid_at']);

        $chart = $this->buildDailySeries(
            start: $start,
            end: $end,
            records: $payments,
            dateResolver: fn (Payment $payment): ?CarbonInterface => $payment->paid_at,
            valueResolver: fn (Payment $payment): float => (float) $payment->amount,
        );

        return [
            ...$chart,
            'total' => round(array_sum($chart['series']), 2),
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

    private function deliveredOrdersQuery()
    {
        return Order::query()
            ->where('vendor_id', $this->vendorProfile->getKey())
            ->where('order_status', OrderStatus::Delivered)
            ->when(
                $this->periodStart() !== null,
                fn ($query) => $query->where('created_at', '>=', $this->periodStart()),
            );
    }

    private function paidPaymentsQuery()
    {
        return Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereHas('order', function ($query): void {
                $query
                    ->where('vendor_id', $this->vendorProfile->getKey())
                    ->where('order_status', OrderStatus::Delivered);
            })
            ->when(
                $this->periodStart() !== null,
                fn ($query) => $query->where('paid_at', '>=', $this->periodStart()),
            );
    }

    private function periodStart(): ?CarbonInterface
    {
        return match ($this->period) {
            'week' => now()->subDays(6)->startOfDay(),
            'month' => now()->subDays(29)->startOfDay(),
            default => null,
        };
    }

    private function chartStart(): CarbonInterface
    {
        if ($this->periodStart() !== null) {
            return Carbon::instance($this->periodStart());
        }

        $firstPaidAt = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereHas('order', function ($query): void {
                $query
                    ->where('vendor_id', $this->vendorProfile->getKey())
                    ->where('order_status', OrderStatus::Delivered);
            })
            ->orderBy('paid_at')
            ->value('paid_at');

        return $firstPaidAt !== null
            ? Carbon::parse($firstPaidAt)->startOfDay()
            : now()->subDays(29)->startOfDay();
    }

    private function hasApprovedVendorProfile(): bool
    {
        return auth()->user()->vendorProfile?->status === VendorStatus::Approved;
    }

    private function peso(float|int $value): string
    {
        return sprintf("\u{20B1}%s", number_format((float) $value, 2));
    }
};
?>

@php($dailyRevenue = $this->dailyRevenue)

<div wire:poll.60s="refreshSalesData" class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="brand-panel p-6 sm:p-8">
        <span class="brand-kicker">{{ __('Vendor analytics') }}</span>
        <div class="mt-4 flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
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
                        class="inline-flex items-center rounded-full border px-4 py-2 text-sm font-semibold transition {{ $period === $value ? 'text-white' : 'bg-white text-neutral-700 dark:bg-zinc-900 dark:text-zinc-100' }}"
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
            <article class="brand-panel-muted p-5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ $stat['label'] }}</p>
                <p class="mt-3 text-3xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $stat['value'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
        <article class="brand-panel overflow-hidden p-5">
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
                <div
                    x-data="{
                        chart: null,
                        init() {
                            if (typeof Chart !== 'function') {
                                return;
                            }

                            const styles = getComputedStyle(document.documentElement);
                            const isDark = document.documentElement.classList.contains('dark');
                            const brand600 = styles.getPropertyValue('--brand-600').trim() || '#059669';
                            const labelColor = isDark ? '#a1a1aa' : '#78716c';
                            const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';

                            Chart.getChart(this.$refs.canvas)?.destroy();

                            this.chart = new Chart(this.$refs.canvas, {
                                type: 'line',
                                data: {
                                    labels: @js($dailyRevenue['labels']),
                                    datasets: [{
                                        data: @js($dailyRevenue['series']),
                                        borderColor: brand600,
                                        backgroundColor: 'rgba(5,150,105,0.08)',
                                        fill: true,
                                        tension: 0.4,
                                        pointRadius: 0,
                                        borderWidth: 2,
                                    }],
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    interaction: {
                                        mode: 'index',
                                        intersect: false,
                                    },
                                    plugins: {
                                        legend: { display: false },
                                        tooltip: {
                                            displayColors: false,
                                            callbacks: {
                                                label: (ctx) => '₱' + Number(ctx.parsed.y ?? 0).toLocaleString('en-PH', {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2,
                                                }),
                                            },
                                        },
                                    },
                                    scales: {
                                        x: {
                                            grid: { display: false },
                                            ticks: {
                                                color: labelColor,
                                                maxTicksLimit: 8,
                                            },
                                        },
                                        y: {
                                            grid: { color: gridColor },
                                            ticks: {
                                                color: labelColor,
                                                callback: (value) => '₱' + Number(value).toLocaleString('en-PH', {
                                                    minimumFractionDigits: 0,
                                                    maximumFractionDigits: 0,
                                                }),
                                            },
                                        },
                                    },
                                },
                            });
                        },
                        destroy() {
                            this.chart?.destroy();
                        },
                    }"
                    class="mt-6"
                    style="height: 280px; position: relative;"
                >
                    <canvas x-ref="canvas" role="img" aria-label="{{ __('Area chart showing delivered order revenue for the selected period') }}"></canvas>
                </div>
            @endif
        </article>

        <article class="brand-panel p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Top products') }}</p>
                    <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Best earners') }}</h2>
                </div>
            </div>

            <div class="mt-6 space-y-3">
                @forelse ($this->topProducts as $productPerformance)
                    <article class="brand-panel-muted flex items-center gap-4 p-4" wire:key="vendor-top-product-{{ $productPerformance->product_id }}">
                        <div class="h-14 w-14 overflow-hidden rounded-[1.25rem] border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-800">
                            <img
                                src="{{ $productPerformance->product?->image_url ?? 'https://placehold.co/112x112/e7e5e4/9ca3af?text=Item' }}"
                                alt="{{ $productPerformance->product?->name ?? __('Deleted product') }}"
                                class="h-full w-full object-cover"
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
