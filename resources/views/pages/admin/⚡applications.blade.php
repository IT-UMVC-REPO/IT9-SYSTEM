<?php

use App\Enums\VendorStatus;
use App\Models\RiderProfile;
use App\Models\VendorProfile;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Applications')] class extends Component
{
    /**
     * @return array<string, int>
     */
    #[Computed]
    public function vendorCounts(): array
    {
        $counts = VendorProfile::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            VendorStatus::Pending->value => (int) ($counts[VendorStatus::Pending->value] ?? 0),
            VendorStatus::Approved->value => (int) ($counts[VendorStatus::Approved->value] ?? 0),
            VendorStatus::Rejected->value => (int) ($counts[VendorStatus::Rejected->value] ?? 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function riderCounts(): array
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
};
?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <a href="{{ route('admin.dashboard') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-neutral-500 transition hover:text-neutral-900 dark:text-zinc-400 dark:hover:text-zinc-100">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            {{ __('Return to Dashboard') }}
        </a>

        <div>
            <span class="brand-kicker">{{ __('Applications') }}</span>
            <h1 class="brand-serif mt-3 text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Applications') }}</h1>
            <p class="mt-3 max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
                {{ __('Review vendor and rider applications from one place, then jump into the dedicated queue when a profile needs attention.') }}
            </p>
        </div>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
        <a href="{{ route('admin.vendors') }}" wire:navigate class="brand-panel group flex min-h-64 flex-col justify-between p-6 transition hover:-translate-y-0.5 hover:shadow-xl dark:hover:shadow-black/30">
            <div>
                <span class="brand-soft-surface inline-flex h-12 w-12 items-center justify-center rounded-2xl text-[var(--brand-700)] dark:text-[var(--brand-300)]">
                    <i class="fa-solid fa-user-check"></i>
                </span>
                <h2 class="brand-serif mt-5 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Vendor applications') }}</h2>
                <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                    {{ __('Review seller profiles, sample products, store details, and approval status.') }}
                </p>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                @foreach ([
                    ['label' => __('Pending'), 'value' => $this->vendorCounts[VendorStatus::Pending->value], 'class' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'],
                    ['label' => __('Approved'), 'value' => $this->vendorCounts[VendorStatus::Approved->value], 'class' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'],
                    ['label' => __('Rejected'), 'value' => $this->vendorCounts[VendorStatus::Rejected->value], 'class' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'],
                ] as $stat)
                    <div class="rounded-2xl px-4 py-3 {{ $stat['class'] }}">
                        <p class="text-2xl font-bold tabular-nums">{{ number_format($stat['value']) }}</p>
                        <p class="mt-1 text-xs font-semibold uppercase tracking-[0.16em]">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </div>

            <span class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-[var(--brand-700)] dark:text-[var(--brand-300)]">
                {{ __('Open vendor queue') }}
                <i class="fa-solid fa-arrow-right text-xs transition group-hover:translate-x-0.5"></i>
            </span>
        </a>

        <a href="{{ route('admin.riders') }}" wire:navigate class="brand-panel group flex min-h-64 flex-col justify-between p-6 transition hover:-translate-y-0.5 hover:shadow-xl dark:hover:shadow-black/30">
            <div>
                <span class="brand-soft-surface inline-flex h-12 w-12 items-center justify-center rounded-2xl text-[var(--brand-700)] dark:text-[var(--brand-300)]">
                    <i class="fa-solid fa-motorcycle"></i>
                </span>
                <h2 class="brand-serif mt-5 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Rider applications') }}</h2>
                <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                    {{ __('Review delivery applicants, vehicle details, availability, and access state.') }}
                </p>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                @foreach ([
                    ['label' => __('Pending'), 'value' => $this->riderCounts['pending'], 'class' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'],
                    ['label' => __('Approved'), 'value' => $this->riderCounts['approved'], 'class' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'],
                    ['label' => __('Inactive'), 'value' => $this->riderCounts['inactive'], 'class' => 'bg-neutral-100 text-neutral-600 dark:bg-white/10 dark:text-zinc-300'],
                ] as $stat)
                    <div class="rounded-2xl px-4 py-3 {{ $stat['class'] }}">
                        <p class="text-2xl font-bold tabular-nums">{{ number_format($stat['value']) }}</p>
                        <p class="mt-1 text-xs font-semibold uppercase tracking-[0.16em]">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </div>

            <span class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-[var(--brand-700)] dark:text-[var(--brand-300)]">
                {{ __('Open rider queue') }}
                <i class="fa-solid fa-arrow-right text-xs transition group-hover:translate-x-0.5"></i>
            </span>
        </a>
    </section>
</div>
