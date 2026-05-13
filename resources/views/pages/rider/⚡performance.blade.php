<?php

use App\Concerns\HasRiderGuard;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\RiderEarning;
use App\Models\RiderProfile;
use App\Models\RiderRating;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Performance')] class extends Component
{
    use HasRiderGuard;

    #[Computed]
    public function profile(): RiderProfile
    {
        return $this->approvedRiderProfile();
    }

    #[Computed]
    public function totals(): array
    {
        return [
            'deliveries' => Order::query()
                ->where('rider_id', auth()->id())
                ->where('order_status', OrderStatus::Delivered)
                ->count(),
            'earnings' => (float) RiderEarning::query()->forRider((int) auth()->id())->sum('amount'),
            'ratings' => (int) $this->profile->total_ratings,
            'average_rating' => $this->profile->formattedRating(),
        ];
    }

    #[Computed]
    public function ratingBreakdown(): array
    {
        $counts = RiderRating::query()
            ->where('rider_id', auth()->id())
            ->get(['rating'])
            ->countBy(fn (RiderRating $rating): int => $rating->rating);

        return [
            'labels' => collect(range(1, 5))->map(fn (int $star): string => $star.' star')->all(),
            'series' => collect(range(1, 5))->map(fn (int $star): int => (int) ($counts[$star] ?? 0))->all(),
            'colors' => ['#f97316', '#f59e0b', '#eab308', '#84cc16', '#16a34a'],
        ];
    }

    #[Computed]
    public function recentRatings(): Collection
    {
        return RiderRating::query()
            ->where('rider_id', auth()->id())
            ->with(['order:id', 'customer:id,name'])
            ->latest()
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function deliverySpeed(): array
    {
        $orders = Order::query()
            ->where('rider_id', auth()->id())
            ->where('order_status', OrderStatus::Delivered)
            ->whereNotNull('picked_up_at')
            ->whereBetween('updated_at', [now()->subDays(29)->startOfDay(), now()->endOfDay()])
            ->get(['picked_up_at', 'updated_at']);

        return [
            'average' => $orders->isEmpty()
                ? 0
                : (int) round($orders->avg(fn (Order $order): int => (int) $order->picked_up_at->diffInMinutes($order->updated_at))),
            'chart' => $this->dailyAverageDeliveryMinutes(),
        ];
    }

    #[Computed]
    public function monthlyEarnings(): array
    {
        $months = collect(range(5, 0))->map(fn (int $monthsAgo) => now()->subMonths($monthsAgo));

        return [
            'labels' => $months->map(fn ($month): string => $month->format('M Y'))->all(),
            'series' => $months->map(fn ($month): float => (float) RiderEarning::query()
                ->forRider((int) auth()->id())
                ->whereBetween('earned_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->sum('amount'))->all(),
        ];
    }

    public function acceptanceTone(): string
    {
        $rate = (float) $this->profile->acceptance_rate;

        if ($rate > 80) {
            return 'text-emerald-600 dark:text-emerald-300';
        }

        if ($rate >= 60) {
            return 'text-amber-600 dark:text-amber-300';
        }

        return 'text-rose-600 dark:text-rose-300';
    }

    public function firstName(string $name): string
    {
        return str($name)->before(' ')->toString();
    }

    public function peso(float|int $value): string
    {
        return sprintf("\u{20B1}%s", number_format((float) $value, 2));
    }

    private function dailyAverageDeliveryMinutes(): array
    {
        $days = collect(range(13, 0))->map(fn (int $daysAgo) => now()->subDays($daysAgo));

        return [
            'labels' => $days->map(fn ($day): string => $day->format('M j'))->all(),
            'series' => $days->map(function ($day): int {
                $orders = Order::query()
                    ->where('rider_id', auth()->id())
                    ->where('order_status', OrderStatus::Delivered)
                    ->whereNotNull('picked_up_at')
                    ->whereBetween('updated_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                    ->get(['picked_up_at', 'updated_at']);

                return $orders->isEmpty()
                    ? 0
                    : (int) round($orders->avg(fn (Order $order): int => (int) $order->picked_up_at->diffInMinutes($order->updated_at)));
            })->all(),
        ];
    }
}; ?>

<section class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4">
        <span class="brand-kicker">{{ __('Performance') }}</span>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Rider performance') }}</h1>
        <p class="max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Track ratings, speed, acceptance, and earnings across your delivery history.') }}
        </p>
    </div>

    <section class="grid gap-4 md:grid-cols-4">
        @foreach ([
            ['label' => __('Total deliveries'), 'value' => number_format($this->totals['deliveries']), 'icon' => 'fa-solid fa-route'],
            ['label' => __('Total earnings'), 'value' => $this->peso($this->totals['earnings']), 'icon' => 'fa-solid fa-wallet'],
            ['label' => __('Total ratings'), 'value' => number_format($this->totals['ratings']), 'icon' => 'fa-solid fa-star-half-stroke'],
            ['label' => __('Average rating'), 'value' => $this->totals['average_rating'].' ★', 'icon' => 'fa-solid fa-star'],
        ] as $total)
            <article class="brand-panel-muted flex items-center gap-4 p-5">
                <span class="brand-soft-surface flex h-12 w-12 items-center justify-center rounded-2xl">
                    <i class="{{ $total['icon'] }}"></i>
                </span>
                <div>
                    <p class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ $total['value'] }}</p>
                    <p class="text-sm text-neutral-500 dark:text-zinc-400">{{ $total['label'] }}</p>
                </div>
            </article>
        @endforeach
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
        <article class="brand-panel p-5 sm:p-6">
            <div>
                <p class="brand-kicker !mb-0">{{ __('Rating breakdown') }}</p>
                <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Customer stars') }}</h2>
            </div>

            @if (array_sum($this->ratingBreakdown['series']) === 0)
                <div class="mt-6 flex h-[240px] items-center justify-center rounded-[1.5rem] border border-dashed border-stone-200 text-sm text-neutral-500 dark:border-white/10 dark:text-zinc-400">
                    {{ __('Ratings will appear after customers review deliveries.') }}
                </div>
            @else
                <x-chart-canvas
                    type="doughnut"
                    :labels="$this->ratingBreakdown['labels']"
                    :series="$this->ratingBreakdown['series']"
                    :colors="$this->ratingBreakdown['colors']"
                    formatter="number"
                    height="240px"
                    :aria-label="__('Doughnut chart showing rider rating distribution')"
                />
            @endif

            <div class="mt-6 space-y-3">
                @forelse ($this->recentRatings as $rating)
                    <article class="brand-panel-muted p-4" wire:key="recent-rider-rating-{{ $rating->id }}">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Order #:number', ['number' => str_pad((string) $rating->order_id, 6, '0', STR_PAD_LEFT)]) }}</p>
                                <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">{{ $this->firstName($rating->customer->name) }}</p>
                            </div>
                            <div class="flex text-amber-500">
                                @for ($star = 1; $star <= 5; $star++)
                                    <i class="{{ $star <= $rating->rating ? 'fa-solid' : 'fa-regular' }} fa-star"></i>
                                @endfor
                            </div>
                        </div>
                        @if ($rating->comment)
                            <p class="mt-3 text-sm leading-6 text-neutral-600 dark:text-zinc-300">{{ $rating->comment }}</p>
                        @endif
                    </article>
                @empty
                    <p class="rounded-[1.5rem] border border-dashed border-stone-200 p-6 text-center text-sm text-neutral-500 dark:border-white/10 dark:text-zinc-400">
                        {{ __('No customer ratings yet.') }}
                    </p>
                @endforelse
            </div>
        </article>

        <div class="space-y-6">
            <article class="brand-panel p-5 sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="brand-kicker !mb-0">{{ __('Delivery speed') }}</p>
                        <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Daily average minutes') }}</h2>
                    </div>
                    <div class="brand-panel-muted px-4 py-3 text-center">
                        <p class="text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ number_format($this->deliverySpeed['average']) }}</p>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('30-day avg') }}</p>
                    </div>
                </div>

                <x-chart-canvas
                    type="line"
                    :labels="$this->deliverySpeed['chart']['labels']"
                    :series="$this->deliverySpeed['chart']['series']"
                    :colors="['brand-600', 'rgba(5,150,105,0.08)']"
                    formatter="number"
                    height="240px"
                    :max-ticks="7"
                    :aria-label="__('Line chart showing average delivery minutes over the last fourteen days')"
                />
            </article>

            <section class="grid gap-6 lg:grid-cols-[0.75fr_1.25fr]">
                <article class="brand-panel p-6">
                    <p class="brand-kicker !mb-0">{{ __('Acceptance rate') }}</p>
                    <p class="mt-5 text-5xl font-bold tabular-nums {{ $this->acceptanceTone() }}">{{ number_format((float) $this->profile->acceptance_rate, 2) }}%</p>
                    <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                        {{ __('Based on accepted delivery offers versus all offers received.') }}
                    </p>
                </article>

                <article class="brand-panel p-5 sm:p-6">
                    <div>
                        <p class="brand-kicker !mb-0">{{ __('Earnings history') }}</p>
                        <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Last 6 months') }}</h2>
                    </div>

                    <x-chart-canvas
                        type="bar"
                        :labels="$this->monthlyEarnings['labels']"
                        :series="$this->monthlyEarnings['series']"
                        :colors="['brand-600']"
                        formatter="currency"
                        height="240px"
                        :max-ticks="6"
                        :aria-label="__('Bar chart showing rider earnings for the last six months')"
                    />
                </article>
            </section>
        </div>
    </section>
</section>
