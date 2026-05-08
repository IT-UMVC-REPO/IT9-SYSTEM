<?php

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Audit Log')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $eventFilter = '';

    #[Url(except: '')]
    public string $dateFrom = '';

    #[Url(except: '')]
    public string $dateTo = '';

    public array $liveEntries = [];

    public function getListeners(): array
    {
        return [
            'echo:admin.audit,.AuditLogCreated' => 'handleLiveEntry',
        ];
    }

    public function handleLiveEntry(array $data): void
    {
        array_unshift($this->liveEntries, $data);
        $this->liveEntries = array_slice($this->liveEntries, 0, 20);
    }

    public function clearLiveFeed(): void
    {
        $this->liveEntries = [];
        $this->resetPage();
    }

    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('user:id,name,email,role')
            ->when(filled($this->search), function ($query): void {
                $query->where(function ($inner): void {
                    $inner->where('description', 'like', '%'.$this->search.'%')
                        ->orWhere('ip_address', 'like', '%'.$this->search.'%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('email', 'like', '%'.$this->search.'%'));
                });
            })
            ->when(filled($this->eventFilter), fn ($query) => $query->where('event', $this->eventFilter))
            ->when(filled($this->dateFrom), fn ($query) => $query->where('created_at', '>=', $this->dateFrom.' 00:00:00'))
            ->when(filled($this->dateTo), fn ($query) => $query->where('created_at', '<=', $this->dateTo.' 23:59:59'))
            ->latest('created_at')
            ->paginate(50);
    }

    /**
     * @return list<AuditEvent>
     */
    #[Computed]
    public function eventOptions(): array
    {
        return AuditEvent::cases();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedEventFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }
};
?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-neutral-500 transition hover:text-neutral-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                <i class="fa-solid fa-arrow-left text-xs"></i>
                {{ __('Return to Dashboard') }}
            </a>
            <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Audit Log') }}</h1>
            <p class="mt-4 max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
                {{ __('A live, chronological record of every meaningful action taken by users across the platform.') }}
            </p>
        </div>
    </section>

    @if (count($liveEntries) > 0)
        <div class="rounded-2xl border border-[var(--brand-200)] bg-[var(--brand-50)] p-4 dark:border-[var(--brand-500)]/20 dark:bg-[var(--brand-500)]/10">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-2.5 w-2.5 animate-pulse rounded-full bg-[var(--brand-600)]"></span>
                    <p class="text-sm font-semibold text-[var(--brand-800)] dark:text-[var(--brand-200)]">
                        {{ trans_choice(':count new event since the page loaded|:count new events since the page loaded', count($liveEntries), ['count' => count($liveEntries)]) }}
                    </p>
                </div>
                <button type="button" wire:click="clearLiveFeed" class="text-xs font-semibold text-[var(--brand-700)] hover:underline dark:text-[var(--brand-300)]">
                    {{ __('Refresh list') }}
                </button>
            </div>

            <div class="mt-3 space-y-2">
                @foreach (array_slice($liveEntries, 0, 5) as $live)
                    <div class="flex items-start gap-3 rounded-xl bg-white/70 px-4 py-2.5 dark:bg-zinc-900/60">
                        <span class="mt-0.5 text-xs text-neutral-400">{{ $live['created_at'] ?? __('just now') }}</span>
                        <i class="{{ $live['icon'] ?? 'fa-solid fa-circle' }} text-[var(--brand-600)] dark:text-[var(--brand-400)]"></i>
                        <p class="text-sm text-neutral-800 dark:text-zinc-200">
                            <span class="font-semibold">{{ $live['user_name'] ?? __('System') }}</span>
                            {{ $live['description'] ?? '' }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="brand-panel p-5">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                :placeholder="__('Search description, user, IP...')"
                :label="__('Search')"
                icon="magnifying-glass"
            />

            <flux:select wire:model.live="eventFilter" :label="__('Event type')">
                <flux:select.option value="">{{ __('All events') }}</flux:select.option>
                @foreach ($this->eventOptions as $event)
                    <flux:select.option value="{{ $event->value }}">{{ $event->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:field>
                <flux:label>{{ __('From date') }}</flux:label>
                <div
                    x-data="sukiDatePicker({ wire: $wire, property: 'dateFrom', value: @js($dateFrom) })"
                    x-init="init()"
                    class="relative"
                >
                    <input
                        x-ref="datepicker"
                        type="text"
                        placeholder="{{ __('Pick a date') }}"
                        class="brand-input w-full pr-10"
                        readonly
                    >
                    <i class="fa-regular fa-calendar pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400"></i>
                </div>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('To date') }}</flux:label>
                <div
                    x-data="sukiDatePicker({ wire: $wire, property: 'dateTo', value: @js($dateTo) })"
                    x-init="init()"
                    class="relative"
                >
                    <input
                        x-ref="datepicker"
                        type="text"
                        placeholder="{{ __('Pick a date') }}"
                        class="brand-input w-full pr-10"
                        readonly
                    >
                    <i class="fa-regular fa-calendar pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400"></i>
                </div>
            </flux:field>
        </div>
    </div>

    <div class="brand-panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-stone-200 dark:border-white/10">
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-neutral-400 dark:text-zinc-500">{{ __('When') }}</th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-neutral-400 dark:text-zinc-500">{{ __('Event') }}</th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-neutral-400 dark:text-zinc-500">{{ __('User') }}</th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-neutral-400 dark:text-zinc-500">{{ __('Description') }}</th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-neutral-400 dark:text-zinc-500">{{ __('IP') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100 dark:divide-white/5">
                    @forelse ($this->entries as $entry)
                        @php
                            $colorMap = [
                                'emerald' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
                                'blue' => 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300',
                                'rose' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
                                'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                                'neutral' => 'bg-stone-100 text-stone-600 dark:bg-white/5 dark:text-zinc-400',
                            ];
                            $badgeClass = $colorMap[$entry->event->color()] ?? $colorMap['neutral'];
                        @endphp
                        <tr wire:key="audit-log-{{ $entry->id }}" class="transition hover:bg-stone-50/70 dark:hover:bg-white/[3%]">
                            <td class="whitespace-nowrap px-5 py-4 text-xs text-neutral-400 dark:text-zinc-500" title="{{ $entry->created_at?->toDateTimeString() }}">
                                {{ $entry->created_at?->diffForHumans() }}
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">
                                    <i class="{{ $entry->event->icon() }}"></i>
                                    {{ $entry->event->label() }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                @if ($entry->user !== null)
                                    <a href="{{ route('admin.users.show', $entry->user) }}" wire:navigate class="brand-hover-text font-semibold">{{ $entry->user->name }}</a>
                                    <p class="text-xs text-neutral-400 dark:text-zinc-500">{{ $entry->user->role->value }}</p>
                                @else
                                    <span class="text-neutral-400 dark:text-zinc-500">{{ __('System') }}</span>
                                @endif
                            </td>
                            <td class="max-w-sm px-5 py-4 text-neutral-700 dark:text-zinc-300">
                                {{ $entry->description }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 font-mono text-xs text-neutral-400 dark:text-zinc-500">
                                {{ $entry->ip_address ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-16 text-center text-sm text-neutral-400 dark:text-zinc-500">
                                {{ __('No audit log entries match the current filters.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->entries->hasPages())
            <div class="border-t border-stone-200 px-5 py-4 dark:border-white/10">
                {{ $this->entries->links() }}
            </div>
        @endif
    </div>
</div>
