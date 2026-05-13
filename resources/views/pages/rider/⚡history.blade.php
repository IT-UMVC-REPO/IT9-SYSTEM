<?php

use App\Concerns\HasRiderGuard;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\RiderEarning;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Rider History')] class extends Component
{
    use HasRiderGuard;
    use WithPagination;

    #[Url(except: 'all')]
    public string $period = 'all';

    public function updatedPeriod(): void
    {
        $this->resetPage();
        unset($this->deliveries);
    }

    #[Computed]
    public function deliveries(): LengthAwarePaginator
    {
        return Order::query()
            ->where('rider_id', auth()->id())
            ->where('order_status', OrderStatus::Delivered)
            ->with(['vendor:id,store_name', 'customer:id,name', 'riderEarning', 'riderRating'])
            ->withCount('orderItems')
            ->when($this->period === 'month', fn ($query) => $query->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()]))
            ->when($this->period === 'week', fn ($query) => $query->whereBetween('updated_at', [now()->startOfWeek(), now()->endOfWeek()]))
            ->when($this->period === 'today', fn ($query) => $query->whereBetween('updated_at', [now()->startOfDay(), now()->endOfDay()]))
            ->latest('updated_at')
            ->paginate(12);
    }

    #[Computed]
    public function earningsSummary(): array
    {
        $baseQuery = RiderEarning::query()->forRider((int) auth()->id());

        return [
            'all' => (float) (clone $baseQuery)->sum('amount'),
            'month' => (float) (clone $baseQuery)->thisMonth()->sum('amount'),
            'week' => (float) (clone $baseQuery)
                ->whereBetween('earned_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->sum('amount'),
        ];
    }

    public function durationLabel(Order $order): string
    {
        if ($order->picked_up_at === null) {
            return __('Duration unavailable');
        }

        return __(':minutes min', [
            'minutes' => max(1, (int) $order->picked_up_at->diffInMinutes($order->updated_at)),
        ]);
    }

    public function peso(float|int $value): string
    {
        return sprintf("\u{20B1}%s", number_format((float) $value, 2));
    }

    public function paginationView(): string
    {
        return 'layouts.app.livewire-paginate';
    }
}; ?>

<section class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4">
        <span class="brand-kicker">{{ __('History') }}</span>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Completed deliveries') }}</h1>
        <p class="max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Review delivered orders, earned payouts, customer ratings, and delivery pace from your completed runs.') }}
        </p>
    </div>

    <section class="grid gap-4 md:grid-cols-3">
        @foreach ([
            ['label' => __('Total earned all time'), 'value' => $this->peso($this->earningsSummary['all']), 'icon' => 'fa-solid fa-wallet'],
            ['label' => __('This month'), 'value' => $this->peso($this->earningsSummary['month']), 'icon' => 'fa-solid fa-calendar-days'],
            ['label' => __('This week'), 'value' => $this->peso($this->earningsSummary['week']), 'icon' => 'fa-solid fa-chart-line'],
        ] as $summary)
            <article class="brand-panel-muted flex items-center gap-4 p-5">
                <span class="brand-soft-surface flex h-12 w-12 items-center justify-center rounded-2xl">
                    <i class="{{ $summary['icon'] }}"></i>
                </span>
                <div>
                    <p class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ $summary['value'] }}</p>
                    <p class="text-sm text-neutral-500 dark:text-zinc-400">{{ $summary['label'] }}</p>
                </div>
            </article>
        @endforeach
    </section>

    <section class="brand-panel p-5">
        <div class="flex flex-wrap gap-3">
            @foreach ([
                'all' => __('All time'),
                'month' => __('This month'),
                'week' => __('This week'),
                'today' => __('Today'),
            ] as $value => $label)
                <button type="button" wire:click="$set('period', '{{ $value }}')" @class([
                    'inline-flex items-center rounded-full border px-4 py-2 text-sm font-semibold transition-all duration-150 active:scale-[0.97]',
                    'border-transparent bg-[var(--brand-600)] text-white' => $period === $value,
                    'border-stone-200 bg-white text-neutral-700 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-100' => $period !== $value,
                ])>
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </section>

    @if ($this->deliveries->isNotEmpty())
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($this->deliveries as $order)
                <article class="brand-panel p-5" wire:key="rider-history-{{ $order->id }}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="brand-kicker !mb-0">{{ __('Order #:number', ['number' => str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]) }}</p>
                            <h2 class="mt-3 text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $order->customer->name }}</h2>
                            <p class="mt-2 text-sm text-neutral-500 dark:text-zinc-400">{{ $order->vendor->store_name }}</p>
                        </div>
                        <x-order-status-badge :status="$order->order_status" />
                    </div>

                    <div class="mt-5 grid gap-3 text-sm text-neutral-500 dark:text-zinc-400 sm:grid-cols-2">
                        <span>{{ trans_choice(':count item|:count items', $order->order_items_count, ['count' => $order->order_items_count]) }}</span>
                        <span>{{ __('Earned: :amount', ['amount' => $this->peso((float) ($order->riderEarning?->amount ?? 0))]) }}</span>
                        <span>{{ __('Duration: :duration', ['duration' => $this->durationLabel($order)]) }}</span>
                        <span>{{ $order->updated_at->format('M j, Y g:i A') }}</span>
                    </div>

                    @if ($order->riderRating)
                        <div class="mt-4 flex items-center gap-2 text-amber-500">
                            @for ($star = 1; $star <= 5; $star++)
                                <i class="{{ $star <= $order->riderRating->rating ? 'fa-solid' : 'fa-regular' }} fa-star"></i>
                            @endfor
                            <span class="ml-2 text-sm font-semibold text-neutral-700 dark:text-zinc-200">{{ number_format((float) $order->riderRating->rating, 1) }}</span>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>

        @if ($this->deliveries->hasPages())
            <div>{{ $this->deliveries->onEachSide(1)->links() }}</div>
        @endif
    @else
        <div class="brand-panel px-6 py-14 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-neutral-400 dark:bg-zinc-800 dark:text-zinc-400">
                <i class="fa-solid fa-clock-rotate-left text-xl"></i>
            </span>
            <h2 class="brand-serif mt-5 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('No completed deliveries yet') }}</h2>
            <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">{{ __('Delivered orders will appear here after you complete them.') }}</p>
        </div>
    @endif
</section>
