<?php

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Report;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Report detail')] class extends Component
{
    public Report $report;

    public string $reviewAdminNotes = '';

    public function mount(Report $report): void
    {
        $this->report = $this->resolveReport($report);
        $this->reviewAdminNotes = (string) ($this->report->admin_notes ?? '');
    }

    public function toggleReportedUserActiveStatus(): void
    {
        if (auth()->id() === $this->report->reportedUser->getKey()) {
            Flux::toast(variant: 'warning', text: __('You cannot deactivate your own account.'));

            return;
        }

        $this->report->reportedUser->forceFill([
            'is_active' => ! $this->report->reportedUser->is_active,
        ])->save();

        $this->report = $this->resolveReport($this->report);

        Flux::toast(
            variant: $this->report->reportedUser->is_active ? 'success' : 'warning',
            text: $this->report->reportedUser->is_active
                ? __('Account activated. The user can sign in again.')
                : __('Account deactivated. The user will be unable to log in.'),
        );
    }

    public function markReviewed(): void
    {
        if ($this->report->status !== ReportStatus::Open) {
            Flux::toast(variant: 'warning', text: __('This report has already been handled.'));

            return;
        }

        $validated = $this->validate([
            'reviewAdminNotes' => ['nullable', 'string', 'max:1500'],
        ]);

        $notes = trim((string) ($validated['reviewAdminNotes'] ?? ''));

        $this->report->forceFill([
            'status' => ReportStatus::Reviewed,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'admin_notes' => filled($notes) ? $notes : null,
        ])->save();

        Flux::toast(variant: 'success', text: __('Report marked as reviewed.'));

        $this->redirectRoute('admin.reports', navigate: true);
    }

    public function dismiss(): void
    {
        if ($this->report->status !== ReportStatus::Open) {
            Flux::toast(variant: 'warning', text: __('This report has already been handled.'));

            return;
        }

        $this->report->forceFill([
            'status' => ReportStatus::Dismissed,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ])->save();

        Flux::toast(variant: 'success', text: __('Report dismissed.'));

        $this->redirectRoute('admin.reports', navigate: true);
    }

    public function reopen(): void
    {
        if ($this->report->status === ReportStatus::Open) {
            Flux::toast(variant: 'warning', text: __('This report is already open.'));

            return;
        }

        $this->report->forceFill([
            'status' => ReportStatus::Open,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ])->save();

        Flux::toast(variant: 'success', text: __('Report re-opened and returned to the moderation queue.'));

        $this->redirectRoute('admin.reports', navigate: true);
    }

    public function roleBadgeClasses(string $role): string
    {
        return match ($role) {
            UserRole::Customer->value => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
            UserRole::Vendor->value => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
            UserRole::Admin->value => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
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

    public function reportedUserRole(): string
    {
        return $this->report->reporter_role === UserRole::Customer->value
            ? UserRole::Vendor->value
            : UserRole::Customer->value;
    }

    public function attachmentUrl(): ?string
    {
        return $this->report->attachment_path !== null
            ? Storage::disk('public')->url($this->report->attachment_path)
            : null;
    }

    public function attachmentName(): ?string
    {
        return $this->report->attachment_path !== null
            ? basename($this->report->attachment_path)
            : null;
    }

    public function hasImageAttachment(): bool
    {
        if ($this->report->attachment_path === null) {
            return false;
        }

        return Str::endsWith(strtolower($this->report->attachment_path), ['.jpg', '.jpeg', '.png', '.webp', '.gif']);
    }

    private function resolveReport(Report $report): Report
    {
        return Report::query()
            ->with([
                'reporter:id,name,email,profile_image',
                'reportedUser:id,name,email,profile_image,is_active',
                'reviewer:id,name',
                'order:id',
            ])
            ->findOrFail($report->getKey());
    }
};
?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="space-y-4">
        <a href="{{ route('admin.reports') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-neutral-600 transition hover:text-neutral-900 dark:text-zinc-300 dark:hover:text-white">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            {{ __('Back to reports') }}
        </a>

        <div class="flex flex-wrap items-center gap-3">
            <span class="brand-kicker !mb-0">{{ __('Moderation case') }}</span>
            <span @class([
                'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]',
                $this->statusBadgeClasses($report->status),
            ])>
                {{ Str::headline($report->status->value) }}
            </span>
        </div>

        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ $report->reason->label() }}</h1>
        <p class="max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Submitted :date by :reporter against :reported.', [
                'date' => $report->created_at->format('M j, Y g:i A'),
                'reporter' => $report->reporter->name,
                'reported' => $report->reportedUser->name,
            ]) }}
        </p>
    </section>

    <section class="grid gap-8 xl:grid-cols-[minmax(0,1.2fr)_minmax(24rem,0.8fr)]">
        <div class="space-y-6">
            <article class="brand-panel p-6 sm:p-8">
                <div class="grid gap-5 lg:grid-cols-2">
                    <section class="brand-panel-muted p-5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Reporter') }}</p>

                        <div class="mt-4 flex items-start gap-4">
                            <x-user-avatar :user="$report->reporter" size="md" />

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $report->reporter->name }}</h2>
                                    <span @class([
                                        'inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]',
                                        $this->roleBadgeClasses($report->reporter_role),
                                    ])>
                                        {{ Str::headline($report->reporter_role) }}
                                    </span>
                                </div>
                                <p class="mt-2 truncate text-sm text-neutral-500 dark:text-zinc-400">{{ $report->reporter->email }}</p>
                                <a href="{{ route('admin.users.show', $report->reporter) }}" wire:navigate class="mt-4 inline-flex items-center gap-2 text-sm font-semibold" style="color: var(--brand-700);">
                                    {{ __('Open profile') }}
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </a>
                            </div>
                        </div>
                    </section>

                    <section class="brand-panel-muted p-5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Reported user') }}</p>

                        <div class="mt-4 flex items-start gap-4">
                            <x-user-avatar :user="$report->reportedUser" size="md" />

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $report->reportedUser->name }}</h2>
                                    <span @class([
                                        'inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]',
                                        $this->roleBadgeClasses($this->reportedUserRole()),
                                    ])>
                                        {{ Str::headline($this->reportedUserRole()) }}
                                    </span>
                                </div>
                                <p class="mt-2 truncate text-sm text-neutral-500 dark:text-zinc-400">{{ $report->reportedUser->email }}</p>
                                <a href="{{ route('admin.users.show', $report->reportedUser) }}" wire:navigate class="mt-4 inline-flex items-center gap-2 text-sm font-semibold" style="color: var(--brand-700);">
                                    {{ __('Open profile') }}
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </a>
                            </div>
                        </div>
                    </section>
                </div>
            </article>

            <article class="brand-panel p-6 sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Case summary') }}</p>
                        <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Report details') }}</h2>
                    </div>

                    @if ($report->order !== null)
                        <a href="{{ route('admin.orders', ['search' => $report->order->id]) }}" wire:navigate class="brand-button-secondary">
                            {{ __('Open linked order') }}
                        </a>
                    @endif
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <article class="brand-panel-muted p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Reason') }}</p>
                        <p class="mt-3 text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ $report->reason->label() }}</p>
                    </article>

                    <article class="brand-panel-muted p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Submitted') }}</p>
                        <p class="mt-3 text-sm text-neutral-700 dark:text-zinc-300">{{ $report->created_at->format('M j, Y g:i A') }}</p>
                    </article>

                    <article class="brand-panel-muted p-4 sm:col-span-2">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Linked order') }}</p>
                        @if ($report->order !== null)
                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                <p class="text-sm text-neutral-700 dark:text-zinc-300">
                                    {{ __('Order #:number', ['number' => str_pad((string) $report->order->id, 6, '0', STR_PAD_LEFT)]) }}
                                </p>
                                <a href="{{ route('admin.orders', ['search' => $report->order->id]) }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold" style="color: var(--brand-700);">
                                    {{ __('View in order oversight') }}
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </a>
                            </div>
                        @else
                            <p class="mt-3 text-sm text-neutral-700 dark:text-zinc-300">{{ __('No order was linked when this report was filed.') }}</p>
                        @endif
                    </article>
                </div>

                <div class="mt-6 rounded-[1.75rem] border border-stone-200 bg-stone-50/80 p-5 dark:border-white/10 dark:bg-zinc-800/70">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Description') }}</p>
                    <div class="mt-4 text-sm leading-7 text-neutral-700 dark:text-zinc-300">
                        @if (filled($report->description))
                            <p class="whitespace-pre-wrap">{{ $report->description }}</p>
                        @else
                            <p>{{ __('No additional description was provided with this report.') }}</p>
                        @endif
                    </div>
                </div>
            </article>

            @if ($report->attachment_path !== null)
                @php($attachmentUrl = $this->attachmentUrl())
                @php($attachmentName = $this->attachmentName())
                <article class="brand-panel p-6 sm:p-8">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Attachment') }}</p>
                            <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Submitted evidence') }}</h2>
                        </div>

                        @if ($attachmentUrl !== null)
                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener noreferrer" download="{{ $attachmentName }}" class="brand-button-secondary">
                                {{ __('Download attachment') }}
                            </a>
                        @endif
                    </div>

                    @if ($this->hasImageAttachment() && $attachmentUrl !== null)
                        <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener noreferrer" class="mt-6 block overflow-hidden rounded-[2rem] border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-900">
                            <img src="{{ $attachmentUrl }}" alt="{{ $attachmentName ?? __('Report attachment') }}" class="max-h-[34rem] w-full object-contain">
                        </a>
                    @endif

                    <div class="mt-6 rounded-[1.5rem] border border-dashed border-stone-300 p-4 dark:border-white/10">
                        <div class="flex flex-wrap items-center gap-3 text-sm text-neutral-700 dark:text-zinc-300">
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-stone-100 text-neutral-600 dark:bg-zinc-800 dark:text-zinc-200">
                                <i class="fa-solid fa-paperclip"></i>
                            </span>
                            <div>
                                <p class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $attachmentName }}</p>
                                <p>{{ __('Stored in the public evidence archive for moderation review.') }}</p>
                            </div>
                        </div>
                    </div>
                </article>
            @endif

            @if ($report->reviewer !== null || $report->reviewed_at !== null || filled($report->admin_notes))
                <article class="brand-panel p-6 sm:p-8">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Moderation record') }}</p>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <article class="brand-panel-muted p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Last handled by') }}</p>
                            <p class="mt-3 text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ $report->reviewer?->name ?? __('Not recorded') }}</p>
                        </article>

                        <article class="brand-panel-muted p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Handled at') }}</p>
                            <p class="mt-3 text-sm text-neutral-700 dark:text-zinc-300">
                                {{ $report->reviewed_at?->format('M j, Y g:i A') ?? __('Not recorded') }}
                            </p>
                        </article>
                    </div>

                    @if (filled($report->admin_notes))
                        <div class="mt-4 rounded-[1.75rem] border border-stone-200 bg-stone-50/80 p-5 dark:border-white/10 dark:bg-zinc-800/70">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Admin notes') }}</p>
                            <p class="mt-4 whitespace-pre-wrap text-sm leading-7 text-neutral-700 dark:text-zinc-300">{{ $report->admin_notes }}</p>
                        </div>
                    @endif
                </article>
            @endif
        </div>

        <aside class="brand-panel h-fit p-6 xl:sticky xl:top-24">
            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Action panel') }}</p>

            <div class="mt-5 space-y-4 rounded-[1.5rem] border border-stone-200 bg-stone-50/80 p-5 dark:border-white/10 dark:bg-zinc-800/70">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Current status') }}</p>
                    <span @class([
                        'mt-2 inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]',
                        $this->statusBadgeClasses($report->status),
                    ])>
                        {{ Str::headline($report->status->value) }}
                    </span>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Reported account status') }}</p>
                    <p class="mt-2 text-sm text-neutral-700 dark:text-zinc-300">
                        {{ $report->reportedUser->is_active ? __('Active and able to sign in') : __('Inactive and currently blocked from sign-in') }}
                    </p>
                </div>
            </div>

            <div class="mt-6 space-y-4">
                <a href="{{ route('messages.conversation', ['conversationReference' => $report->reportedUser->id]) }}" wire:navigate class="brand-button-secondary w-full text-center">
                    {{ __('Send message to reported user') }}
                </a>

                @if ($report->reportedUser->is_active)
                    <flux:button
                        variant="danger"
                        class="w-full justify-center"
                        type="button"
                        wire:click="toggleReportedUserActiveStatus"
                        wire:confirm="{{ __('Deactivate this account? Please confirm that you want to suspend the reported user from signing in.') }}"
                    >
                        {{ __('Suspend account') }}
                    </flux:button>
                @else
                    <button
                        type="button"
                        wire:click="toggleReportedUserActiveStatus"
                        wire:confirm="{{ __('Reactivate this account? Please confirm that you want to restore sign-in access for the reported user.') }}"
                        class="brand-button-primary w-full"
                    >
                        {{ __('Reactivate account') }}
                    </button>
                @endif
            </div>

            @if ($report->status === ReportStatus::Open)
                <form wire:submit="markReviewed" class="mt-8 rounded-[1.5rem] border border-stone-200 bg-stone-50/80 p-5 dark:border-white/10 dark:bg-zinc-800/70">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Review outcome') }}</p>

                    <div class="mt-4 space-y-2">
                        <flux:textarea
                            wire:model="reviewAdminNotes"
                            :label="__('Admin notes')"
                            :description="__('Optional notes for the moderation record.')"
                            :invalid="$errors->has('reviewAdminNotes')"
                            rows="5"
                            :placeholder="__('Add context for the decision, evidence checked, or follow-up needed.')"
                        />

                        @error('reviewAdminNotes')
                            <p class="text-xs font-medium text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-5 space-y-3">
                        <button type="submit" class="brand-button-primary w-full">
                            {{ __('Mark as reviewed') }}
                        </button>

                        <flux:button
                            variant="danger"
                            class="w-full justify-center"
                            type="button"
                            wire:click="dismiss"
                            wire:confirm="{{ __('Dismiss this report? This action will mark the case as handled.') }}"
                        >
                            {{ __('Dismiss report') }}
                        </flux:button>
                    </div>
                </form>
            @else
                <div class="mt-8 rounded-[1.75rem] border border-stone-200 bg-white/80 p-5 dark:border-white/10 dark:bg-zinc-900/80">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Re-open case') }}</p>
                    <p class="mt-3 text-sm leading-7 text-neutral-600 dark:text-zinc-300">
                        {{ __('Use this if new context appears or the report needs to move back into the open moderation queue.') }}
                    </p>

                    <button
                        type="button"
                        wire:click="reopen"
                        wire:confirm="{{ __('Re-open this report and return it to the open queue?') }}"
                        class="brand-button-primary mt-5 w-full"
                    >
                        {{ __('Re-open report') }}
                    </button>
                </div>
            @endif
        </aside>
    </section>
</div>
