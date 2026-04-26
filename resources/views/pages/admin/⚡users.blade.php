<?php

use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\VendorProfile;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('User management')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $roleFilter = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function toggleActiveStatus(int $userId): void
    {
        $user = $this->resolveUser($userId);

        if (auth()->id() === $user->getKey()) {
            Flux::toast(variant: 'warning', text: __('You cannot deactivate your own account.'));

            return;
        }

        $user->forceFill([
            'is_active' => ! $user->is_active,
        ])->save();

        Flux::toast(
            variant: $user->is_active ? 'success' : 'warning',
            text: $user->is_active
                ? __('Account activated. The user can sign in again.')
                : __('Account deactivated. The user will be unable to log in.'),
        );
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with([
                'vendorProfile:id,user_id,store_name,status,approved_at',
            ])
            ->withCount('orders')
            ->when(
                filled($this->search),
                function ($query): void {
                    $searchTerm = trim($this->search);

                    $query->where(function ($builder) use ($searchTerm): void {
                        $builder
                            ->where('name', 'like', '%'.$searchTerm.'%')
                            ->orWhere('email', 'like', '%'.$searchTerm.'%');
                    });
                },
            )
            ->when(
                filled($this->roleFilter),
                fn ($query) => $query->where('role', $this->roleFilter),
            )
            ->when(
                filled($this->statusFilter),
                fn ($query) => $query->where('is_active', $this->statusFilter === 'active'),
            )
            ->latest('created_at')
            ->paginate(20);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total_users' => User::query()->count(),
            'total_customers' => User::query()->where('role', UserRole::Customer)->count(),
            'total_vendors' => VendorProfile::query()->where('status', VendorStatus::Approved)->count(),
            'total_admins' => User::query()->where('role', UserRole::Admin)->count(),
        ];
    }

    public function paginationView(): string
    {
        return 'layouts.app.livewire-paginate';
    }

    private function resolveUser(int $userId): User
    {
        return User::query()
            ->with('vendorProfile:id,user_id,store_name,status,approved_at')
            ->withCount('orders')
            ->findOrFail($userId);
    }
}; ?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <span class="brand-kicker">{{ __('Admin controls') }}</span>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">
            {{ __('User management') }}
        </h1>
        <p class="max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Search marketplace accounts, review vendor enrollment context, and control who can sign in to the platform.') }}
        </p>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => __('Total users'), 'value' => $this->stats['total_users']],
            ['label' => __('Total customers'), 'value' => $this->stats['total_customers']],
            ['label' => __('Approved vendors'), 'value' => $this->stats['total_vendors']],
            ['label' => __('Total admins'), 'value' => $this->stats['total_admins']],
        ] as $stat)
            <article class="brand-panel-muted p-5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">
                    {{ $stat['label'] }}
                </p>
                <p class="mt-3 text-3xl font-semibold text-neutral-900 dark:text-zinc-100">{{ number_format($stat['value']) }}</p>
            </article>
        @endforeach
    </section>

    <section class="brand-panel p-6">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,0.7fr)_minmax(0,0.7fr)]">
            <flux:input
                wire:model.live.debounce.250ms="search"
                :label="__('Search users')"
                type="search"
                :placeholder="__('Search by name or email')"
            />

            <flux:select wire:model.live="roleFilter" :label="__('Role')">
                <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
                @foreach (UserRole::cases() as $role)
                    <flux:select.option :value="$role->value" :label="ucfirst($role->value)" />
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statusFilter" :label="__('Account status')">
                <flux:select.option value="">{{ __('All accounts') }}</flux:select.option>
                <flux:select.option value="active" :label="__('Active')" />
                <flux:select.option value="inactive" :label="__('Inactive')" />
            </flux:select>
        </div>
    </section>

    <section wire:loading.class="opacity-60" wire:target="search,roleFilter,statusFilter,gotoPage,previousPage,nextPage" class="transition duration-200">
        @if ($this->users->isNotEmpty())
            <div class="hidden lg:block">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('User') }}</flux:table.column>
                        <flux:table.column>{{ __('Role') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Registered') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->users as $user)
                            <flux:table.row :key="$user->id">
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <x-user-avatar :user="$user" size="sm" />

                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-neutral-900 dark:text-zinc-100">{{ $user->name }}</p>
                                            <p class="truncate text-sm text-neutral-500 dark:text-zinc-400">{{ $user->email }}</p>

                                            @if ($user->vendorProfile !== null)
                                                <p class="mt-1 truncate text-xs text-neutral-500 dark:text-zinc-400">
                                                    {{ $user->vendorProfile->store_name }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="settings-role-badge">{{ ucfirst($user->role->value) }}</span>

                                        @if ($user->vendorProfile !== null)
                                            <span @class([
                                                'inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]',
                                                'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => $user->vendorProfile->status === VendorStatus::Pending,
                                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $user->vendorProfile->status === VendorStatus::Approved,
                                                'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' => $user->vendorProfile->status === VendorStatus::Rejected,
                                            ])>
                                                {{ __('Vendor: :status', ['status' => ucfirst($user->vendorProfile->status->value)]) }}
                                            </span>
                                        @endif
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <span class="inline-flex items-center gap-2 text-sm font-medium text-neutral-700 dark:text-zinc-300">
                                        <span class="{{ $user->is_active ? 'bg-emerald-500' : 'bg-rose-500' }} h-2.5 w-2.5 rounded-full"></span>
                                        {{ $user->is_active ? __('Active') : __('Inactive') }}
                                    </span>
                                </flux:table.cell>

                                <flux:table.cell>{{ $user->created_at->format('M j, Y') }}</flux:table.cell>

                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.users.show', $user) }}" wire:navigate class="brand-button-secondary">
                                            {{ __('View profile') }}
                                        </a>

                                        @if ($user->is_active)
                                            <flux:button
                                                variant="danger"
                                                type="button"
                                                wire:click="toggleActiveStatus({{ $user->id }})"
                                                wire:confirm="{{ __('Deactivate this account? They will be unable to log in.') }}"
                                                :disabled="auth()->id() === $user->id"
                                            >
                                                {{ __('Deactivate') }}
                                            </flux:button>
                                        @else
                                            <button
                                                type="button"
                                                wire:click="toggleActiveStatus({{ $user->id }})"
                                                class="brand-button-primary"
                                            >
                                                {{ __('Activate') }}
                                            </button>
                                        @endif
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            <div class="grid gap-4 lg:hidden">
                @foreach ($this->users as $user)
                    <article class="brand-panel p-5" wire:key="mobile-user-{{ $user->id }}">
                        <div class="flex items-start gap-3">
                            <x-user-avatar :user="$user" size="sm" />

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-lg font-semibold text-neutral-900 dark:text-zinc-100">{{ $user->name }}</h2>
                                    <span class="settings-role-badge">{{ ucfirst($user->role->value) }}</span>
                                </div>
                                <p class="truncate text-sm text-neutral-500 dark:text-zinc-400">{{ $user->email }}</p>

                                @if ($user->vendorProfile !== null)
                                    <p class="mt-2 text-sm text-neutral-600 dark:text-zinc-300">{{ $user->vendorProfile->store_name }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center gap-2 text-sm font-medium text-neutral-700 dark:text-zinc-300">
                                <span class="{{ $user->is_active ? 'bg-emerald-500' : 'bg-rose-500' }} h-2.5 w-2.5 rounded-full"></span>
                                {{ $user->is_active ? __('Active') : __('Inactive') }}
                            </span>
                            <span class="text-sm text-neutral-500 dark:text-zinc-400">{{ $user->created_at->format('M j, Y') }}</span>
                        </div>

                        <div class="mt-4 grid gap-2 sm:grid-cols-2">
                            <a href="{{ route('admin.users.show', $user) }}" wire:navigate class="brand-button-secondary text-center">
                                {{ __('View profile') }}
                            </a>

                            @if ($user->is_active)
                                <flux:button
                                    variant="danger"
                                    type="button"
                                    wire:click="toggleActiveStatus({{ $user->id }})"
                                    wire:confirm="{{ __('Deactivate this account? They will be unable to log in.') }}"
                                    :disabled="auth()->id() === $user->id"
                                >
                                    {{ __('Deactivate') }}
                                </flux:button>
                            @else
                                <button
                                    type="button"
                                    wire:click="toggleActiveStatus({{ $user->id }})"
                                    class="brand-button-primary"
                                >
                                    {{ __('Activate') }}
                                </button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($this->users->hasPages())
                <div class="mt-8">
                    {{ $this->users->onEachSide(1)->links() }}
                </div>
            @endif
        @else
            <div class="brand-panel px-6 py-14 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-neutral-400 dark:bg-zinc-800 dark:text-zinc-400">
                    <i class="fa-solid fa-users text-xl"></i>
                </span>
                <h2 class="brand-serif mt-5 text-3xl font-bold text-neutral-900 dark:text-zinc-100">
                    {{ __('No users match this view') }}
                </h2>
                <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                    {{ __('Adjust the filters to surface the accounts you need to review.') }}
                </p>
            </div>
        @endif
    </section>
</div>
