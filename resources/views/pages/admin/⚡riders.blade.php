<?php

use App\Enums\AuditEvent;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\RiderProfile;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Rider approvals')] class extends Component
{
    use WithPagination;

    #[Url(except: 'pending')]
    public string $status = 'pending';

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

    public function approve(int $riderProfileId): void
    {
        $profile = RiderProfile::query()->with('user:id,name,email')->findOrFail($riderProfileId);

        abort_if($profile->status === 'approved', 403);

        DB::transaction(function () use ($profile): void {
            $profile->forceFill([
                'status' => 'approved',
                'approved_at' => now(),
            ])->save();

            $profile->user()->update([
                'role' => UserRole::Rider->value,
            ]);

            $notification = Notification::query()->create([
                'user_id' => $profile->user_id,
                'type' => NotificationType::System,
                'title' => 'Rider account approved',
                'message' => 'Your rider application has been approved. You can now access your rider dashboard.',
                'data' => [
                    'route' => 'rider.dashboard',
                ],
            ]);

            event(new NotificationCreated($notification));

            AuditLogger::log(AuditEvent::RiderApproved, "Admin approved rider '{$profile->user->name}'.", $profile);
        });

        unset($this->riders, $this->counts);

        Flux::toast(variant: 'success', text: __('Rider approved.'));
    }

    public function deactivate(int $riderProfileId): void
    {
        $profile = RiderProfile::query()->with('user:id,name,email')->findOrFail($riderProfileId);

        abort_if($profile->status === 'inactive', 403);

        DB::transaction(function () use ($profile): void {
            $profile->forceFill([
                'status' => 'inactive',
                'is_available' => false,
            ])->save();

            $profile->user()->update([
                'role' => UserRole::Customer->value,
            ]);

            $notification = Notification::query()->create([
                'user_id' => $profile->user_id,
                'type' => NotificationType::System,
                'title' => 'Rider account deactivated',
                'message' => 'Your rider access has been deactivated. You can continue using your customer account.',
                'data' => [
                    'route' => 'customer.dashboard',
                ],
            ]);

            event(new NotificationCreated($notification));

            AuditLogger::log(AuditEvent::RiderDeactivated, "Admin deactivated rider '{$profile->user->name}'.", $profile);
        });

        unset($this->riders, $this->counts);

        Flux::toast(variant: 'warning', text: __('Rider deactivated.'));
    }

    #[Computed]
    public function riders(): LengthAwarePaginator
    {
        return RiderProfile::query()
            ->with('user:id,name,email,phone')
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when(
                $this->search !== '',
                function ($query): void {
                    $searchTerm = trim($this->search);

                    $query->where(function ($builder) use ($searchTerm): void {
                        $builder
                            ->where('vehicle_type', 'like', '%'.$searchTerm.'%')
                            ->orWhere('plate_number', 'like', '%'.$searchTerm.'%')
                            ->orWhere('contact_number', 'like', '%'.$searchTerm.'%')
                            ->orWhereHas('user', function ($userQuery) use ($searchTerm): void {
                                $userQuery
                                    ->where('name', 'like', '%'.$searchTerm.'%')
                                    ->orWhere('email', 'like', '%'.$searchTerm.'%');
                            });
                    });
                },
            )
            ->latest('created_at')
            ->paginate(15);
    }

    #[Computed]
    public function counts(): array
    {
        $counts = RiderProfile::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'pending' => (int) ($counts['pending'] ?? 0),
            'approved' => (int) ($counts['approved'] ?? 0),
            'inactive' => (int) ($counts['inactive'] ?? 0),
        ];
    }

    public function paginationView(): string
    {
        return 'layouts.app.livewire-paginate';
    }
}; ?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <a href="{{ route('admin.dashboard') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-neutral-500 transition hover:text-neutral-900 dark:text-zinc-400 dark:hover:text-zinc-100">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            {{ __('Return to dashboard') }}
        </a>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Rider applications') }}</h1>
        <p class="max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Review rider applicants, approve delivery access, and deactivate rider accounts when needed.') }}
        </p>
    </section>

    <section class="brand-panel p-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="flex flex-wrap gap-3">
                @foreach ([
                    ['value' => 'pending', 'label' => __('Pending')],
                    ['value' => 'approved', 'label' => __('Approved')],
                    ['value' => 'inactive', 'label' => __('Inactive')],
                ] as $tab)
                    <button type="button" wire:click="$set('status', '{{ $tab['value'] }}')" @class([
                        'inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition-all duration-150 active:scale-[0.97]',
                        'border-transparent bg-[var(--brand-600)] text-white' => $status === $tab['value'],
                        'border-stone-200 bg-white text-neutral-700 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-100' => $status !== $tab['value'],
                    ])>
                        <span>{{ $tab['label'] }}</span>
                        <span class="rounded-full bg-black/10 px-2 py-0.5 text-xs text-current dark:bg-white/10">{{ $this->counts[$tab['value']] }}</span>
                    </button>
                @endforeach
            </div>

            <div class="w-full max-w-md">
                <flux:input wire:model.live.debounce.250ms="search" :label="__('Search riders')" type="search" :placeholder="__('Search by rider, email, vehicle, or plate')" />
            </div>
        </div>
    </section>

    <section wire:loading.class="opacity-60 blur-[0.5px]" wire:target="status,search,gotoPage,previousPage,nextPage" class="transition duration-200">
        @if ($this->riders->isNotEmpty())
            <div class="hidden lg:block">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Rider') }}</flux:table.column>
                        <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
                        <flux:table.column>{{ __('Contact') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Submitted') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->riders as $profile)
                            <flux:table.row :key="$profile->id" wire:transition>
                                <flux:table.cell>
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-neutral-900 dark:text-zinc-100">{{ $profile->user->name }}</p>
                                        <p class="truncate text-sm text-neutral-500 dark:text-zinc-400">{{ $profile->user->email }}</p>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>{{ Str::headline($profile->vehicle_type) }}{{ $profile->plate_number ? ' - '.$profile->plate_number : '' }}</flux:table.cell>
                                <flux:table.cell>{{ $profile->contact_number ?: $profile->user->phone ?: __('Not provided') }}</flux:table.cell>
                                <flux:table.cell>
                                    <span @class([
                                        'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]',
                                        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => $profile->status === 'pending',
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $profile->status === 'approved',
                                        'bg-neutral-100 text-neutral-600 dark:bg-white/10 dark:text-zinc-300' => $profile->status === 'inactive',
                                    ])>{{ $profile->status }}</span>
                                </flux:table.cell>
                                <flux:table.cell>{{ $profile->created_at->format('M j, Y') }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.riders.show', $profile) }}" wire:navigate class="brand-button-secondary active:scale-[0.96]">{{ __('Review') }}</a>
                                        @if ($profile->status !== 'approved')
                                            <button type="button" wire:click="approve({{ $profile->id }})" class="brand-button-primary active:scale-[0.96]">{{ __('Approve') }}</button>
                                        @else
                                            <button type="button" wire:click="deactivate({{ $profile->id }})" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition active:scale-[0.96] dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200">{{ __('Deactivate') }}</button>
                                        @endif
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            <div class="grid gap-4 lg:hidden">
                @foreach ($this->riders as $profile)
                    <article class="brand-panel p-5" wire:key="mobile-rider-{{ $profile->id }}" wire:transition>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="truncate text-lg font-semibold text-neutral-900 dark:text-zinc-100">{{ $profile->user->name }}</h2>
                                <p class="truncate text-sm text-neutral-500 dark:text-zinc-400">{{ $profile->user->email }}</p>
                            </div>
                            <span class="brand-badge">{{ $profile->status }}</span>
                        </div>

                        <div class="mt-4 grid gap-2 text-sm text-neutral-500 dark:text-zinc-400">
                            <p>{{ Str::headline($profile->vehicle_type) }}{{ $profile->plate_number ? ' - '.$profile->plate_number : '' }}</p>
                            <p>{{ $profile->contact_number ?: __('No contact provided') }}</p>
                        </div>

                        <div class="mt-5 grid gap-2 sm:grid-cols-2">
                            <a href="{{ route('admin.riders.show', $profile) }}" wire:navigate class="brand-button-secondary active:scale-[0.96]">{{ __('Review') }}</a>
                            @if ($profile->status !== 'approved')
                                <button type="button" wire:click="approve({{ $profile->id }})" class="brand-button-primary active:scale-[0.96]">{{ __('Approve') }}</button>
                            @else
                                <button type="button" wire:click="deactivate({{ $profile->id }})" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 transition active:scale-[0.96] dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200">{{ __('Deactivate') }}</button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($this->riders->hasPages())
                <div class="mt-8">{{ $this->riders->onEachSide(1)->links() }}</div>
            @endif
        @else
            <div class="brand-panel px-6 py-14 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-neutral-400 dark:bg-zinc-800 dark:text-zinc-400">
                    <i class="fa-solid fa-motorcycle text-xl"></i>
                </span>
                <h2 class="brand-serif mt-5 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('No riders match this view') }}</h2>
                <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">{{ __('Try a different status or search term to find the rider application you need.') }}</p>
            </div>
        @endif
    </section>
</div>
