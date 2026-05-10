<?php

use App\Enums\UserRole;
use App\Enums\VendorStatus;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Setup')] class extends Component
{
    public function mount(): void
    {
        $user = auth()->user();

        if ($user->effectiveMarketplaceRole() === UserRole::Admin) {
            abort(403);
        }

        if ($user->effectiveMarketplaceRole() === UserRole::Rider) {
            $this->redirectRoute('rider.dashboard', navigate: true);

            return;
        }

        if ($user->effectiveMarketplaceRole() === UserRole::Vendor) {
            $this->redirectRoute('vendor.dashboard', navigate: true);

            return;
        }
    }

    #[Computed]
    public function vendorStatus(): ?string
    {
        $profile = auth()->user()->vendorProfile;

        if ($profile === null) {
            return null;
        }

        return $profile->status instanceof VendorStatus
            ? $profile->status->value
            : (string) $profile->status;
    }

    #[Computed]
    public function riderStatus(): ?string
    {
        $profile = auth()->user()->riderProfile;

        return $profile?->status;
    }
}; ?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-3">
        <a href="{{ route('shop.home') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-neutral-500 transition hover:text-neutral-900 dark:text-zinc-400 dark:hover:text-zinc-100">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            {{ __('Return to Storefront') }}
        </a>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">
            {{ __('Expand your role on LocalPalengke') }}
        </h1>
        <p class="max-w-2xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Choose a path below to register as a vendor or delivery rider. Each application is reviewed by our admin team before activation.') }}
        </p>
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        {{-- Vendor Registration Card --}}
        <a
            href="{{ route('vendor.registration') }}"
            wire:navigate
            class="brand-panel group relative flex flex-col overflow-hidden p-0 transition-all duration-200 hover:-translate-y-1 hover:shadow-xl"
        >
            <div class="relative flex h-44 items-center justify-center bg-gradient-to-br from-amber-50 via-orange-50 to-rose-50 dark:from-amber-500/10 dark:via-orange-500/10 dark:to-rose-500/10">
                <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-white/90 shadow-lg ring-1 ring-black/5 transition-transform duration-300 group-hover:scale-110 dark:bg-zinc-800/90 dark:ring-white/10">
                    <i class="fa-solid fa-shop text-3xl text-amber-600 dark:text-amber-400"></i>
                </div>

                @if ($this->vendorStatus === 'pending')
                    <span class="absolute right-4 top-4 inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500 dark:bg-amber-400"></span>
                        {{ __('Pending') }}
                    </span>
                @elseif ($this->vendorStatus === 'rejected')
                    <span class="absolute right-4 top-4 inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500 dark:bg-rose-400"></span>
                        {{ __('Needs changes') }}
                    </span>
                @endif
            </div>

            <div class="flex flex-1 flex-col gap-4 p-6 sm:p-8">
                <div>
                    <h2 class="brand-serif text-2xl font-bold text-neutral-900 dark:text-zinc-100">
                        {{ __('Vendor Registration') }}
                    </h2>
                    <p class="mt-2 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                        {{ __('Open your own stall on the marketplace. List products, manage orders, and track sales from your vendor dashboard.') }}
                    </p>
                </div>

                <div class="mt-auto space-y-3 border-t border-stone-100 pt-4 dark:border-white/5">
                    @foreach ([
                        __('Publish your product catalog'),
                        __('Receive and manage customer orders'),
                        __('Track revenue and sales insights'),
                    ] as $feature)
                        <div class="flex items-center gap-3 text-sm text-neutral-600 dark:text-zinc-300">
                            <i class="fa-solid fa-circle-check text-xs text-emerald-500 dark:text-emerald-400"></i>
                            <span>{{ $feature }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-2 flex items-center gap-2 text-sm font-semibold text-[var(--brand-700)] dark:text-[var(--brand-400)]">
                    <span>{{ $this->vendorStatus !== null ? __('View application') : __('Start application') }}</span>
                    <i class="fa-solid fa-arrow-right text-xs transition-transform duration-200 group-hover:translate-x-1"></i>
                </div>
            </div>
        </a>

        {{-- Rider Registration Card --}}
        <a
            href="{{ route('rider.registration') }}"
            wire:navigate
            class="brand-panel group relative flex flex-col overflow-hidden p-0 transition-all duration-200 hover:-translate-y-1 hover:shadow-xl"
        >
            <div class="relative flex h-44 items-center justify-center bg-gradient-to-br from-sky-50 via-indigo-50 to-violet-50 dark:from-sky-500/10 dark:via-indigo-500/10 dark:to-violet-500/10">
                <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-white/90 shadow-lg ring-1 ring-black/5 transition-transform duration-300 group-hover:scale-110 dark:bg-zinc-800/90 dark:ring-white/10">
                    <i class="fa-solid fa-motorcycle text-3xl text-sky-600 dark:text-sky-400"></i>
                </div>

                @if ($this->riderStatus === 'pending')
                    <span class="absolute right-4 top-4 inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500 dark:bg-amber-400"></span>
                        {{ __('Pending') }}
                    </span>
                @endif
            </div>

            <div class="flex flex-1 flex-col gap-4 p-6 sm:p-8">
                <div>
                    <h2 class="brand-serif text-2xl font-bold text-neutral-900 dark:text-zinc-100">
                        {{ __('Rider Registration') }}
                    </h2>
                    <p class="mt-2 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                        {{ __('Join the delivery team. Pick up prepared orders from vendors and deliver them to customers across the market.') }}
                    </p>
                </div>

                <div class="mt-auto space-y-3 border-t border-stone-100 pt-4 dark:border-white/5">
                    @foreach ([
                        __('Claim orders ready for delivery'),
                        __('Share live location with customers'),
                        __('Manage your availability schedule'),
                    ] as $feature)
                        <div class="flex items-center gap-3 text-sm text-neutral-600 dark:text-zinc-300">
                            <i class="fa-solid fa-circle-check text-xs text-emerald-500 dark:text-emerald-400"></i>
                            <span>{{ $feature }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-2 flex items-center gap-2 text-sm font-semibold text-[var(--brand-700)] dark:text-[var(--brand-400)]">
                    <span>{{ $this->riderStatus !== null ? __('View application') : __('Start application') }}</span>
                    <i class="fa-solid fa-arrow-right text-xs transition-transform duration-200 group-hover:translate-x-1"></i>
                </div>
            </div>
        </a>
    </div>
</div>