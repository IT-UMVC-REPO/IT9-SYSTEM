<?php

use App\Enums\VendorStatus;
use App\Models\VendorProfile;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Vendor approvals')] class extends Component {
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

    #[Computed]
    public function vendors(): LengthAwarePaginator
    {
        return VendorProfile::query()
            ->with([
                'user:id,name,email',
            ])
            ->withCount('products')
            ->when(
                $this->status !== '',
                fn ($query) => $query->where('status', $this->status),
            )
            ->when(
                $this->search !== '',
                function ($query): void {
                    $searchTerm = trim($this->search);

                    $query->where(function ($builder) use ($searchTerm): void {
                        $builder
                            ->where('store_name', 'like', '%'.$searchTerm.'%')
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

    public function paginationView(): string
    {
        return 'layouts.app.livewire-paginate';
    }
}; ?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <a href="{{ route('admin.dashboard') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-neutral-500 transition hover:text-neutral-900 dark:text-zinc-400 dark:hover:text-zinc-100">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            {{ __('Return to Dashboard') }}
        </a>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">
            {{ __('Vendor applications') }}
        </h1>
        <p class="max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Review incoming seller applications, approve trusted stalls, and track how many vendors are active or waiting on changes.') }}
        </p>
    </section>

    <section class="brand-panel p-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="flex flex-wrap gap-3">
                @foreach ([
                    ['value' => VendorStatus::Pending->value, 'label' => __('Pending')],
                    ['value' => VendorStatus::Approved->value, 'label' => __('Approved')],
                    ['value' => VendorStatus::Rejected->value, 'label' => __('Rejected')],
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
                            {{ $this->counts[$tab['value']] }}
                        </span>
                    </button>
                @endforeach
            </div>

            <div class="w-full max-w-md">
                <flux:input wire:model.live.debounce.250ms="search" :label="__('Search applications')" type="search" :placeholder="__('Search by store, owner, or email')" />
            </div>
        </div>
    </section>

    <section wire:loading.class="opacity-60" wire:target="status,search,gotoPage,previousPage,nextPage" class="transition duration-200">
        @if ($this->vendors->isNotEmpty())
            <div class="hidden lg:block">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Store') }}</flux:table.column>
                        <flux:table.column>{{ __('Owner') }}</flux:table.column>
                        <flux:table.column>{{ __('Products') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Submitted') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Review') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->vendors as $vendor)
                            <flux:table.row :key="$vendor->id">
                                <flux:table.cell class="max-w-[18rem] overflow-hidden">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="h-10 w-10 shrink-0 overflow-hidden rounded-full border border-stone-200 dark:border-white/10">
                                            <img
                                                src="{{ $vendor->store_image_url }}"
                                                alt="{{ $vendor->store_name }}"
                                                class="h-full w-full object-cover"
                                            >
                                        </div>
                                        <div class="min-w-0 max-w-[16rem] overflow-hidden">
                                            <p class="truncate font-semibold text-neutral-900 dark:text-zinc-100">{{ $vendor->store_name }}</p>
                                        </div>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="max-w-[18rem] overflow-hidden">
                                    <div class="min-w-0 max-w-[16rem] overflow-hidden">
                                        <p class="truncate font-medium text-neutral-900 dark:text-zinc-100">{{ $vendor->user->name }}</p>
                                        <p class="truncate text-sm text-neutral-500 dark:text-zinc-400">{{ $vendor->user->email }}</p>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span class="brand-badge">{{ $vendor->products_count }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span @class([
                                        'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]',
                                        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => $vendor->status === VendorStatus::Pending,
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $vendor->status === VendorStatus::Approved,
                                        'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' => $vendor->status === VendorStatus::Rejected,
                                    ])>
                                        {{ $vendor->status->value }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell>{{ $vendor->created_at->format('M j, Y') }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <a href="{{ route('admin.vendors.show', $vendor) }}" wire:navigate class="brand-button-secondary">
                                        {{ __('Review') }}
                                    </a>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            <div class="grid gap-4 lg:hidden">
                @foreach ($this->vendors as $vendor)
                    <article class="brand-panel p-5" wire:key="mobile-vendor-{{ $vendor->id }}">
                        <div class="flex items-start gap-4">
                            <div class="h-12 w-12 shrink-0 overflow-hidden rounded-full border border-stone-200 dark:border-white/10">
                                <img
                                    src="{{ $vendor->store_image_url }}"
                                    alt="{{ $vendor->store_name }}"
                                    class="h-full w-full object-cover"
                                >
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex min-w-0 flex-wrap items-center gap-2">
                                    <h2 class="max-w-full truncate text-lg font-semibold text-neutral-900 dark:text-zinc-100">{{ $vendor->store_name }}</h2>
                                    <span class="brand-badge">{{ $vendor->products_count }}</span>
                                </div>
                                <p class="mt-1 truncate text-sm font-medium text-neutral-900 dark:text-zinc-100">{{ $vendor->user->name }}</p>
                                <p class="truncate text-sm text-neutral-500 dark:text-zinc-400">{{ $vendor->user->email }}</p>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <span @class([
                                'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]',
                                'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => $vendor->status === VendorStatus::Pending,
                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $vendor->status === VendorStatus::Approved,
                                'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' => $vendor->status === VendorStatus::Rejected,
                            ])>
                                {{ $vendor->status->value }}
                            </span>

                            <a href="{{ route('admin.vendors.show', $vendor) }}" wire:navigate class="brand-button-secondary">
                                {{ __('Review') }}
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($this->vendors->hasPages())
                <div class="mt-8">
                    {{ $this->vendors->onEachSide(1)->links() }}
                </div>
            @endif
        @else
            <div class="brand-panel px-6 py-14 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-neutral-400 dark:bg-zinc-800 dark:text-zinc-400">
                    <i class="fa-solid fa-store-slash text-xl"></i>
                </span>
                <h2 class="brand-serif mt-5 text-3xl font-bold text-neutral-900 dark:text-zinc-100">
                    {{ __('No applications match this view') }}
                </h2>
                <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                    {{ __('Try a different status or search term to find the vendor application you need to review.') }}
                </p>
            </div>
        @endif
    </section>
</div>
