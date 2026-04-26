<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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

    public ?int $reviewingReportId = null;

    public string $reviewAdminNotes = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function startReview(int $reportId): void
    {
        $this->reviewingReportId = $reportId;
        $this->reviewAdminNotes = '';
    }

    public function cancelReview(): void
    {
        $this->reset('reviewingReportId', 'reviewAdminNotes');
    }

    public function submitReview(): void
    {
        if ($this->reviewingReportId === null) {
            return;
        }

        $validated = $this->validate([
            'reviewAdminNotes' => ['nullable', 'string', 'max:1500'],
        ]);

        $this->markReviewed($this->reviewingReportId, $validated['reviewAdminNotes'] ?? '');
    }

    #[Computed]
    public function reports(): LengthAwarePaginator
    {
        return Report::query()
            ->with([
                'reporter:id,name',
                'reportedUser:id,name',
                'reviewer:id,name',
                'order:id',
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

    public function markReviewed(int $reportId, string $adminNotes = ''): void
    {
        $report = Report::query()->findOrFail($reportId);

        if ($report->status !== ReportStatus::Open) {
            Flux::toast(variant: 'warning', text: __('This report has already been handled.'));

            return;
        }

        $notes = trim($adminNotes);

        DB::transaction(function () use ($report, $notes): void {
            $report->forceFill([
                'status' => ReportStatus::Reviewed,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'admin_notes' => filled($notes) ? $notes : null,
            ])->save();
        });

        unset($this->reports, $this->counts);

        $this->reset('reviewingReportId', 'reviewAdminNotes');

        Flux::modal('review-report')->close();
        Flux::toast(variant: 'success', text: __('Report marked as reviewed.'));
    }

    public function dismiss(int $reportId): void
    {
        $report = Report::query()->findOrFail($reportId);

        if ($report->status !== ReportStatus::Open) {
            Flux::toast(variant: 'warning', text: __('This report has already been handled.'));

            return;
        }

        DB::transaction(function () use ($report): void {
            $report->forceFill([
                'status' => ReportStatus::Dismissed,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ])->save();
        });

        unset($this->reports, $this->counts);

        Flux::toast(variant: 'warning', text: __('Report dismissed.'));
    }

    public function paginationView(): string
    {
        return 'layouts.app.livewire-paginate';
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

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <span class="brand-kicker">{{ __('Admin moderation') }}</span>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">
            {{ __('User reports') }}
        </h1>
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
                        <flux:table.column>{{ __('Role') }}</flux:table.column>
                        <flux:table.column>{{ __('Reason') }}</flux:table.column>
                        <flux:table.column>{{ __('Order') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Submitted') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->reports as $report)
                            <flux:table.row :key="$report->id">
                                <flux:table.cell>{{ $report->reporter->name }}</flux:table.cell>
                                <flux:table.cell>{{ $report->reportedUser->name }}</flux:table.cell>
                                <flux:table.cell>{{ Str::headline($report->reporter_role) }}</flux:table.cell>
                                <flux:table.cell>{{ $report->reason->label() }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($report->order !== null)
                                        {{ __('Order #:number', ['number' => str_pad((string) $report->order->id, 6, '0', STR_PAD_LEFT)]) }}
                                    @else
                                        <span class="text-sm text-neutral-500 dark:text-zinc-400">{{ __('Not linked') }}</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span @class([
                                        'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]',
                                        $this->statusBadgeClasses($report->status),
                                    ])>
                                        {{ Str::headline($report->status->value) }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell>{{ $report->created_at->format('M j, Y g:i A') }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    @if ($report->status === ReportStatus::Open)
                                        <div class="flex items-center justify-end gap-2">
                                            <flux:modal.trigger name="review-report">
                                                <flux:button type="button" wire:click="startReview({{ $report->id }})">
                                                    {{ __('Mark reviewed') }}
                                                </flux:button>
                                            </flux:modal.trigger>

                                            <flux:button
                                                variant="ghost"
                                                type="button"
                                                wire:click="dismiss({{ $report->id }})"
                                                wire:confirm="{{ __('Dismiss this report? This action will mark it as handled.') }}"
                                                class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-300 dark:hover:bg-rose-500/10 dark:hover:text-rose-200"
                                            >
                                                {{ __('Dismiss') }}
                                            </flux:button>
                                        </div>
                                    @else
                                        <div class="text-right text-sm text-neutral-500 dark:text-zinc-400">
                                            {{ $report->reviewer?->name ? __('Handled by :name', ['name' => $report->reviewer->name]) : __('Handled') }}
                                        </div>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            <div class="grid gap-4 lg:hidden">
                @foreach ($this->reports as $report)
                    <article class="brand-panel p-5" wire:key="mobile-report-{{ $report->id }}">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="brand-kicker !mb-0">{{ Str::headline($report->reporter_role) }}</p>
                                <h2 class="mt-3 text-lg font-semibold text-neutral-900 dark:text-zinc-100">
                                    {{ $report->reporter->name }} {{ __('reported') }} {{ $report->reportedUser->name }}
                                </h2>
                                <p class="mt-2 text-sm text-neutral-500 dark:text-zinc-400">{{ $report->reason->label() }}</p>
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
                            <p>
                                {{ $report->order !== null
                                    ? __('Linked to order #:number', ['number' => str_pad((string) $report->order->id, 6, '0', STR_PAD_LEFT)])
                                    : __('No linked order') }}
                            </p>

                            @if (filled($report->admin_notes))
                                <p>{{ __('Notes: :notes', ['notes' => $report->admin_notes]) }}</p>
                            @endif
                        </div>

                        @if ($report->status === ReportStatus::Open)
                            <div class="mt-5 flex flex-col gap-3">
                                <flux:modal.trigger name="review-report">
                                    <flux:button type="button" wire:click="startReview({{ $report->id }})" class="w-full">
                                        {{ __('Mark reviewed') }}
                                    </flux:button>
                                </flux:modal.trigger>

                                <flux:button
                                    variant="ghost"
                                    type="button"
                                    wire:click="dismiss({{ $report->id }})"
                                    wire:confirm="{{ __('Dismiss this report? This action will mark it as handled.') }}"
                                    class="w-full justify-center text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-300 dark:hover:bg-rose-500/10 dark:hover:text-rose-200"
                                >
                                    {{ __('Dismiss') }}
                                </flux:button>
                            </div>
                        @endif
                    </article>
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

    <flux:modal name="review-report" class="max-w-lg" wire:close="cancelReview">
        <form wire:submit="submitReview" class="space-y-6 rounded-[1.5rem] border border-stone-200 bg-white/95 p-6 shadow-xl dark:border-white/10 dark:bg-zinc-900/95">
            <div>
                <flux:heading size="lg">{{ __('Mark report as reviewed') }}</flux:heading>
                <flux:subheading>
                    {{ __('Add optional notes so the moderation outcome is clear for the admin team.') }}
                </flux:subheading>
            </div>

            <flux:textarea
                wire:model="reviewAdminNotes"
                :label="__('Admin notes')"
                rows="5"
                :placeholder="__('Add context for this moderation decision (optional).')"
            />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">
                        {{ __('Cancel') }}
                    </flux:button>
                </flux:modal.close>

                <flux:button type="submit">
                    {{ __('Mark reviewed') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
