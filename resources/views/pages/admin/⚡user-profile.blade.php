<?php

use App\Enums\AuditEvent;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('User profile')] class extends Component {
    public User $user;

    public ?int $lastActivity = null;

    public function mount(User $user): void
    {
        $this->user = User::query()
            ->with([
                'vendorProfile:id,user_id,store_name,status,approved_at',
            ])
            ->withCount('orders')
            ->findOrFail($user->getKey());

        $this->lastActivity = $this->resolveLastActivity($this->user->getKey());

        AuditLogger::log(AuditEvent::AdminUserViewed, "Admin viewed user '{$this->user->email}'.", $this->user);
    }

    public function toggleActiveStatus(): void
    {
        if (auth()->id() === $this->user->getKey()) {
            Flux::toast(variant: 'warning', text: __('You cannot deactivate your own account.'));

            return;
        }

        $this->user->forceFill([
            'is_active' => ! $this->user->is_active,
        ])->save();

        AuditLogger::log(
            $this->user->is_active ? AuditEvent::UserReactivated : AuditEvent::UserDeactivated,
            "Admin ".($this->user->is_active ? 'reactivated' : 'deactivated')." account '{$this->user->email}'.",
            $this->user,
        );

        $this->user->refresh();
        $this->lastActivity = $this->resolveLastActivity($this->user->getKey());

        Flux::toast(
            variant: $this->user->is_active ? 'success' : 'warning',
            text: $this->user->is_active
                ? __('Account activated. The user can sign in again.')
                : __('Account deactivated. The user will be unable to log in.'),
        );
    }

    private function resolveLastActivity(int $userId): ?int
    {
        $lastActivity = DB::table('sessions')
            ->where('user_id', $userId)
            ->max('last_activity');

        return $lastActivity !== null ? (int) $lastActivity : null;
    }
}; ?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="space-y-4">
        <a href="{{ route('admin.users') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-neutral-600 transition hover:text-neutral-900 dark:text-zinc-300 dark:hover:text-white">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            {{ __('Back to users') }}
        </a>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('User profile') }}</h1>
            </div>

            @if ($user->is_active)
                <flux:button
                    variant="danger"
                    type="button"
                    x-data
                    x-on:click="$flux.modal('deactivate-user-profile').show()"
                    :disabled="auth()->id() === $user->id"
                >
                    {{ __('Deactivate account') }}
                </flux:button>
            @else
                <button type="button" wire:click="toggleActiveStatus" class="brand-button-primary active:scale-[0.96]">
                    {{ __('Activate account') }}
                </button>
            @endif
        </div>
    </section>

    <flux:modal name="deactivate-user-profile" class="max-w-sm">
        <div class="p-6 space-y-4">
            <flux:heading size="lg">{{ __('Deactivate account?') }}</flux:heading>
            <flux:text>{{ __('This user will be unable to log in until an admin reactivates the account.') }}</flux:text>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" x-on:click="$flux.modal('deactivate-user-profile').close()">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button variant="danger" wire:click="toggleActiveStatus" x-on:click="$flux.modal('deactivate-user-profile').close()">
                    {{ __('Deactivate') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <section class="grid gap-8 xl:grid-cols-[minmax(0,1.2fr)_minmax(24rem,0.8fr)]">
        <div class="space-y-6">
            <article class="brand-panel p-6 sm:p-8">
                <div class="flex items-start gap-4">
                    <x-user-avatar :user="$user" size="xl" />

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="brand-serif truncate text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ $user->name }}</h2>
                            <span class="settings-role-badge">{{ ucfirst($user->role->value) }}</span>
                        </div>
                        <p class="mt-2 truncate text-sm text-neutral-500 dark:text-zinc-400">{{ $user->email }}</p>
                        <p class="mt-2 text-sm text-neutral-600 dark:text-zinc-300">
                            {{ $user->is_active ? __('Account is active') : __('Account is inactive') }}
                        </p>
                    </div>
                </div>
            </article>

            <section class="grid gap-4 sm:grid-cols-2">
                <article class="brand-panel-muted p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Phone') }}</p>
                    <p class="mt-3 text-sm text-neutral-700 dark:text-zinc-300">{{ $user->phone ?: __('Not provided') }}</p>
                </article>

                <article class="brand-panel-muted p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Orders placed') }}</p>
                    <p class="mt-3 text-sm text-neutral-700 dark:text-zinc-300">{{ number_format($user->orders_count) }}</p>
                </article>

                <article class="brand-panel-muted p-4 sm:col-span-2">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Address') }}</p>
                    <p class="mt-3 text-sm text-neutral-700 dark:text-zinc-300">{{ $user->address ?: __('Not provided') }}</p>
                </article>
            </section>
        </div>

        <aside class="brand-panel h-fit p-6 xl:sticky xl:top-24">
            <div class="space-y-4 rounded-[1.5rem] border border-stone-200 bg-stone-50/80 p-5 dark:border-white/10 dark:bg-zinc-800/70">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Registered') }}</p>
                    <p class="mt-2 text-sm text-neutral-700 dark:text-zinc-300">{{ $user->created_at->format('M j, Y g:i A') }}</p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Last recorded session') }}</p>
                    <p class="mt-2 text-sm text-neutral-700 dark:text-zinc-300">
                        {{ $lastActivity !== null ? Carbon::createFromTimestamp($lastActivity)->diffForHumans() : __('Not tracked') }}
                    </p>
                </div>
            </div>

            @if ($user->vendorProfile !== null)
                <div class="mt-6 rounded-[1.5rem] border border-stone-200 bg-stone-50/80 p-5 dark:border-white/10 dark:bg-zinc-800/70">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Vendor profile') }}</p>
                            <h3 class="mt-2 text-lg font-semibold text-neutral-900 dark:text-zinc-100">{{ $user->vendorProfile->store_name }}</h3>
                        </div>

                        <span @class([
                            'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]',
                            'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => $user->vendorProfile->status === VendorStatus::Pending,
                            'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $user->vendorProfile->status === VendorStatus::Approved,
                            'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' => $user->vendorProfile->status === VendorStatus::Rejected,
                        ])>
                            {{ ucfirst($user->vendorProfile->status->value) }}
                        </span>
                    </div>

                    <p class="mt-3 text-sm text-neutral-600 dark:text-zinc-300">
                        {{ $user->vendorProfile->approved_at !== null
                            ? __('Approved :date', ['date' => $user->vendorProfile->approved_at->format('M j, Y')])
                            : __('Awaiting final review or reapplication changes.') }}
                    </p>
                </div>
            @endif
        </aside>
    </section>
</div>
