<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('User Reports')] class extends Component
{
    use WithPagination;

    #[Url(except: 'open')]
    public string $status = 'open';

    #[Url(except: '')]
    public string $search = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function reports(): LengthAwarePaginator
    {
        return Report::query()
            ->with([
                'reporter:id,name,profile_image',
                'reportedUser:id,name,profile_image',
                'reviewer:id,name',
            ])
            ->when(
                $this->status !== 'all',
                fn ($query) => $query->where('status', $this->status),
            )
            ->when(
                filled($this->search),
                function ($query): void {
                    $searchTerm = trim($this->search);

                    $query->where(function ($builder) use ($searchTerm): void {
                        $builder
                            ->whereHas('reporter', fn ($reporterQuery) => $reporterQuery->where('name', 'like', '%'.$searchTerm.'%'))
                            ->orWhereHas('reportedUser', fn ($reportedUserQuery) => $reportedUserQuery->where('name', 'like', '%'.$searchTerm.'%'));
                    });
                },
            )
            ->latest('created_at')
            ->paginate(20);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $groupedCounts = Report::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total' => Report::query()->count(),
            ReportStatus::Open->value => (int) ($groupedCounts[ReportStatus::Open->value] ?? 0),
            ReportStatus::Reviewed->value => (int) ($groupedCounts[ReportStatus::Reviewed->value] ?? 0),
            ReportStatus::Dismissed->value => (int) ($groupedCounts[ReportStatus::Dismissed->value] ?? 0),
        ];
    }

    public function paginationView(): string
    {
        return 'layouts.app.livewire-paginate';
    }

    public function reporterRoleBadgeClasses(string $role): string
    {
        return match ($role) {
            'customer' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
            'vendor' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
            default => 'bg-stone-100 text-stone-700 dark:bg-zinc-800 dark:text-zinc-200',
        };
    }

    public function statusBadgeClasses(ReportStatus $status): string
    {
        return match ($status) {
            ReportStatus::Open => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
            ReportStatus::Reviewed => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
            ReportStatus::Dismissed => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
        };
    }
};
?>

<div wire:poll.10s class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <span class="brand-kicker">{{ __('Admin moderation') }}</span>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">
                {{ __('User reports') }}
            </h1>

            <span class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                <span class="flex h-2.5 w-2.5 rounded-full bg-current"></span>
                {{ trans_choice(':count open case|:count open cases', $this->counts[ReportStatus::Open->value], ['count' => number_format($this->counts[ReportStatus::Open->value])]) }}
            </span>
        </div>
        <p class="max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Review customer and vendor reports, search the people involved, and keep a clear moderation record for marketplace disputes.') }}
        </p>
    </section>

    <section class="brand-panel p-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="flex flex-wrap gap-3">
                @foreach ([
                    ['value' => 'all', 'label' => __('All'), 'count' => $this->counts['total']],
                    ['value' => ReportStatus::Open->value, 'label' => __('Open'), 'count' => $this->counts[ReportStatus::Open->value]],
                    ['value' => ReportStatus::Reviewed->value, 'label' => __('Reviewed'), 'count' => $this->counts[ReportStatus::Reviewed->value]],
                    ['value' => ReportStatus::Dismissed->value, 'label' => __('Dismissed'), 'count' => $this->counts[ReportStatus::Dismissed->value]],
                ] as $tab)
                    <button
                        type="button"
                        wire:click="$set('status', '{{ $tab['value'] }}')"
                        @class([
                            'inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition',
                            'border-transparent bg-[var(--brand-600)] text-white' => $status === $tab['value'],
                            'border-stone-200 bg-white text-neutral-700 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-100' => $status !== $tab['value'],
                        ])
                    >
                        <span>{{ $tab['label'] }}</span>
                        <span class="rounded-full bg-black/10 px-2 py-0.5 text-xs text-current dark:bg-white/10">
                            {{ $tab['count'] }}
                        </span>
                    </button>
                @endforeach
            </div>

            <div class="w-full max-w-md">
                <flux:input
                    wire:model.live.debounce.250ms="search"
                    :label="__('Search reports')"
                    type="search"
                    :placeholder="__('Search by reporter or reported user')"
                />
            </div>
        </div>
    </section>

    <section wire:loading.class="opacity-60" wire:target="status,search,gotoPage,previousPage,nextPage" class="transition duration-200">
        @if ($this->reports->isNotEmpty())
            <div class="hidden lg:block">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Reporter') }}</flux:table.column>
                        <flux:table.column>{{ __('Reported user') }}</flux:table.column>
                        <flux:table.column>{{ __('Reason') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Submitted') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->reports as $report)
                            @php($detailUrl = route('admin.reports.show', $report))
                            <flux:table.row :key="$report->id">
                                <flux:table.cell>
                                    <a href="{{ $detailUrl }}" wire:navigate class="flex items-center gap-3 rounded-2xl px-1 py-1 transition hover:bg-stone-50/70 dark:hover:bg-white/5">
                                        <x-user-avatar :user="$report->reporter" size="sm" />

                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-neutral-900 dark:text-zinc-100">{{ $report->reporter->name }}</p>
                                            <span @class([
                                                'mt-2 inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]',
                                                $this->reporterRoleBadgeClasses($report->reporter_role),
                                            ])>
                                                {{ Str::headline($report->reporter_role) }}
                                            </span>
                                        </div>
                                    </a>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <a href="{{ $detailUrl }}" wire:navigate class="flex items-center gap-3 rounded-2xl px-1 py-1 transition hover:bg-stone-50/70 dark:hover:bg-white/5">
                                        <x-user-avatar :user="$report->reportedUser" size="sm" />

                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-neutral-900 dark:text-zinc-100">{{ $report->reportedUser->name }}</p>
                                            <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">
                                                {{ $report->reporter_role === 'customer' ? __('Vendor account') : __('Customer account') }}
                                            </p>
                                        </div>
                                    </a>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <a href="{{ $detailUrl }}" wire:navigate class="block rounded-2xl px-1 py-2 text-neutral-700 transition hover:bg-stone-50/70 hover:text-neutral-900 dark:text-zinc-200 dark:hover:bg-white/5 dark:hover:text-white">
                                        {{ $report->reason->label() }}
                                    </a>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <a href="{{ $detailUrl }}" wire:navigate class="inline-flex rounded-2xl px-1 py-2 transition hover:bg-stone-50/70 dark:hover:bg-white/5">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]',
                                            $this->statusBadgeClasses($report->status),
                                        ])>
                                            {{ Str::headline($report->status->value) }}
                                        </span>
                                    </a>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <a href="{{ $detailUrl }}" wire:navigate class="block rounded-2xl px-1 py-2 text-neutral-700 transition hover:bg-stone-50/70 hover:text-neutral-900 dark:text-zinc-200 dark:hover:bg-white/5 dark:hover:text-white">
                                        {{ $report->created_at->format('M j, Y g:i A') }}
                                    </a>
                                </flux:table.cell>

                                <flux:table.cell align="end">
                                    <a href="{{ $detailUrl }}" wire:navigate class="brand-button-secondary">
                                        {{ $report->status === ReportStatus::Open ? __('Review case') : __('View case') }}
                                    </a>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            <div class="grid gap-4 lg:hidden">
                @foreach ($this->reports as $report)
                    <a
                        href="{{ route('admin.reports.show', $report) }}"
                        wire:key="mobile-report-{{ $report->id }}"
                        wire:navigate
                        class="brand-panel block p-5 transition hover:-translate-y-0.5 hover:shadow-lg"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="brand-kicker !mb-0">{{ __('Reporter') }}</p>
                                    <span @class([
                                        'inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]',
                                        $this->reporterRoleBadgeClasses($report->reporter_role),
                                    ])>
                                        {{ Str::headline($report->reporter_role) }}
                                    </span>
                                </div>
                                <h2 class="mt-3 text-lg font-semibold text-neutral-900 dark:text-zinc-100">{{ $report->reporter->name }}</h2>
                                <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">{{ __('Reported user: :name', ['name' => $report->reportedUser->name]) }}</p>
                                <p class="mt-3 text-sm text-neutral-600 dark:text-zinc-300">{{ $report->reason->label() }}</p>
                            </div>

                            <span @class([
                                'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]',
                                $this->statusBadgeClasses($report->status),
                            ])>
                                {{ Str::headline($report->status->value) }}
                            </span>
                        </div>

                        <div class="mt-4 grid gap-2 text-sm text-neutral-500 dark:text-zinc-400">
                            <p>{{ __('Submitted :date', ['date' => $report->created_at->format('M j, Y g:i A')]) }}</p>
                            <p>{{ $report->reviewer?->name ? __('Latest handler: :name', ['name' => $report->reviewer->name]) : __('Awaiting admin handling') }}</p>
                        </div>

                        <div class="mt-5">
                            <span class="inline-flex items-center gap-2 text-sm font-semibold" style="color: var(--brand-700);">
                                {{ __('Open case file') }}
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>

            @if ($this->reports->hasPages())
                <div class="mt-8">
                    {{ $this->reports->onEachSide(1)->links() }}
                </div>
            @endif
        @else
            <div class="brand-panel px-6 py-14 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-neutral-400 dark:bg-zinc-800 dark:text-zinc-400">
                    <i class="fa-solid fa-flag text-xl"></i>
                </span>
                <h2 class="brand-serif mt-5 text-3xl font-bold text-neutral-900 dark:text-zinc-100">
                    {{ __('No reports match this view') }}
                </h2>
                <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                    {{ __('Try a different status or search term to find the report you need to review.') }}
                </p>
            </div>
        @endif
    </section>
</div>
