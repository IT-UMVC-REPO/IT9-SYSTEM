<?php

use App\Enums\AuditEvent;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\RiderProfile;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rider review')] class extends Component
{
    public RiderProfile $riderProfile;

    public function mount(RiderProfile $riderProfile): void
    {
        $this->riderProfile = $riderProfile->load('user:id,name,email,phone,address');
    }

    public function approve(): void
    {
        abort_if($this->riderProfile->status === 'approved', 403);

        DB::transaction(function (): void {
            $this->riderProfile->forceFill([
                'status' => 'approved',
                'approved_at' => now(),
            ])->save();

            $this->riderProfile->user()->update([
                'role' => UserRole::Rider->value,
            ]);

            $notification = Notification::query()->create([
                'user_id' => $this->riderProfile->user_id,
                'type' => NotificationType::System,
                'title' => 'Rider account approved',
                'message' => 'Your rider application has been approved. You can now access your rider dashboard.',
                'data' => [
                    'route' => 'rider.dashboard',
                ],
            ]);

            event(new NotificationCreated($notification));

            AuditLogger::log(AuditEvent::RiderApproved, "Admin approved rider '{$this->riderProfile->user->name}'.", $this->riderProfile);
        });

        Flux::toast(variant: 'success', text: __('Rider approved.'));

        $this->redirectRoute('admin.riders', navigate: true);
    }

    public function deactivate(): void
    {
        abort_if($this->riderProfile->status === 'inactive', 403);

        DB::transaction(function (): void {
            $this->riderProfile->forceFill([
                'status' => 'inactive',
                'is_available' => false,
            ])->save();

            $this->riderProfile->user()->update([
                'role' => UserRole::Customer->value,
            ]);

            $notification = Notification::query()->create([
                'user_id' => $this->riderProfile->user_id,
                'type' => NotificationType::System,
                'title' => 'Rider account deactivated',
                'message' => 'Your rider access has been deactivated. You can continue using your customer account.',
                'data' => [
                    'route' => 'customer.dashboard',
                ],
            ]);

            event(new NotificationCreated($notification));

            AuditLogger::log(AuditEvent::RiderDeactivated, "Admin deactivated rider '{$this->riderProfile->user->name}'.", $this->riderProfile);
        });

        Flux::toast(variant: 'warning', text: __('Rider deactivated.'));

        $this->redirectRoute('admin.riders', navigate: true);
    }
}; ?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="grid gap-8 xl:grid-cols-[minmax(0,1.1fr)_minmax(22rem,0.9fr)]">
        <div class="space-y-6">
            <div class="brand-panel p-6 sm:p-8">
                <a href="{{ route('admin.riders') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-neutral-500 transition hover:text-neutral-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                    <i class="fa-solid fa-arrow-left text-xs"></i>
                    {{ __('Back to riders') }}
                </a>

                <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <span class="brand-kicker">{{ __('Rider review') }}</span>
                        <h1 class="brand-serif mt-3 text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ $riderProfile->user->name }}</h1>
                        <p class="mt-3 text-base leading-8 text-neutral-500 dark:text-zinc-400">{{ $riderProfile->user->email }}</p>
                    </div>

                    <span @class([
                        'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]',
                        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => $riderProfile->status === 'pending',
                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $riderProfile->status === 'approved',
                        'bg-neutral-100 text-neutral-600 dark:bg-white/10 dark:text-zinc-300' => $riderProfile->status === 'inactive',
                    ])>{{ $riderProfile->status }}</span>
                </div>
            </div>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="brand-panel-muted p-6">
                    <p class="brand-kicker !mb-0">{{ __('Vehicle') }}</p>
                    <h2 class="mt-3 text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ Str::headline($riderProfile->vehicle_type) }}</h2>
                    <p class="mt-2 text-sm text-neutral-500 dark:text-zinc-400">{{ $riderProfile->plate_number ?: __('No plate number provided') }}</p>
                </div>

                <div class="brand-panel-muted p-6">
                    <p class="brand-kicker !mb-0">{{ __('Contact') }}</p>
                    <h2 class="mt-3 text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $riderProfile->contact_number ?: $riderProfile->user->phone ?: __('Not provided') }}</h2>
                    <p class="mt-2 text-sm text-neutral-500 dark:text-zinc-400">{{ $riderProfile->user->address ?: __('No address on profile') }}</p>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-4">
                @foreach ([
                    ['label' => __('Rating'), 'value' => $riderProfile->formattedRating().' ★'],
                    ['label' => __('Total earnings'), 'value' => $riderProfile->formattedEarnings()],
                    ['label' => __('Acceptance rate'), 'value' => number_format((float) $riderProfile->acceptance_rate, 2).'%'],
                    ['label' => __('Avg delivery'), 'value' => $riderProfile->average_delivery_minutes ? __(':minutes min', ['minutes' => $riderProfile->average_delivery_minutes]) : __('Not enough data')],
                ] as $stat)
                    <article class="brand-panel-muted p-5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ $stat['label'] }}</p>
                        <p class="mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ $stat['value'] }}</p>
                    </article>
                @endforeach
            </section>

            @if ($riderProfile->bio)
                <section class="brand-panel-muted p-6">
                    <p class="brand-kicker !mb-0">{{ __('Bio') }}</p>
                    <p class="mt-3 text-sm leading-7 text-neutral-600 dark:text-zinc-300">{{ $riderProfile->bio }}</p>
                </section>
            @endif
        </div>

        <aside class="brand-panel h-fit p-6 xl:sticky xl:top-24">
            <p class="brand-kicker !mb-0">{{ __('Decision panel') }}</p>
            <div class="mt-5 space-y-3 text-sm">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-neutral-500 dark:text-zinc-400">{{ __('Submitted') }}</span>
                    <span class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $riderProfile->created_at->format('M j, Y') }}</span>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <span class="text-neutral-500 dark:text-zinc-400">{{ __('Available') }}</span>
                    <span class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $riderProfile->is_available ? __('Yes') : __('No') }}</span>
                </div>
                @if ($riderProfile->approved_at)
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-neutral-500 dark:text-zinc-400">{{ __('Approved') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $riderProfile->approved_at->format('M j, Y') }}</span>
                    </div>
                @endif
            </div>

            <div class="mt-8 grid gap-3">
                @if ($riderProfile->status !== 'approved')
                    <flux:button type="button" variant="primary" wire:click="approve" class="w-full justify-center">
                        {{ __('Approve rider') }}
                    </flux:button>
                @else
                    <flux:button type="button" variant="danger" wire:click="deactivate" class="w-full justify-center">
                        {{ __('Deactivate rider') }}
                    </flux:button>
                @endif
            </div>
        </aside>
    </section>
</div>
