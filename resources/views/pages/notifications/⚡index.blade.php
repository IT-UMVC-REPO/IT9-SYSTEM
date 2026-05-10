<?php

use App\Concerns\HasPaginationView;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\Notification;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Notifications')] class extends Component
{
    use HasPaginationView;
    use WithPagination;

    #[Url(except: 'all')]
    public string $filter = 'all';

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'unread', 'order_update', 'message', 'system'], true)
            ? $filter
            : 'all';

        $this->resetPage();
    }

    public function markAllAsRead(): void
    {
        Notification::query()
            ->where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        unset($this->notifications);

        Flux::toast(variant: 'success', text: __('All notifications marked as read.'));
    }

    public function openNotification(int $notificationId): void
    {
        $notification = $this->resolveNotification($notificationId);

        if (! $notification->is_read) {
            $notification->forceFill(['is_read' => true])->save();
        }

        $this->redirect($this->targetUrl($notification), navigate: true);
    }

    public function deleteNotification(int $notificationId): void
    {
        $this->resolveNotification($notificationId)->delete();

        unset($this->notifications);

        Flux::toast(variant: 'success', text: __('Notification deleted.'));
    }

    #[Computed]
    public function notifications(): LengthAwarePaginator
    {
        return Notification::query()
            ->where('user_id', auth()->id())
            ->when($this->filter === 'unread', fn ($query) => $query->where('is_read', false))
            ->when(
                in_array($this->filter, ['order_update', 'message', 'system'], true),
                fn ($query) => $query->where('type', $this->filter),
            )
            ->latest('created_at')
            ->paginate(15);
    }

    public function notificationIcon(NotificationType $type): string
    {
        return match ($type) {
            NotificationType::NewProduct => 'fa-solid fa-store',
            NotificationType::OrderUpdate => 'fa-solid fa-bag-shopping',
            NotificationType::Message => 'fa-solid fa-comments',
            NotificationType::System => 'fa-solid fa-bell',
        };
    }

    public function targetUrl(Notification $notification): string
    {
        $data = $notification->data ?? [];
        $routeName = $data['route'] ?? null;
        $routeParameters = $data['route_parameters'] ?? [];

        if (is_string($routeName) && Route::has($routeName)) {
            return route($routeName, is_array($routeParameters) ? $routeParameters : []);
        }

        $user = auth()->user();

        if ($notification->type === NotificationType::OrderUpdate) {
            $orderReference = isset($data['order_id']) ? (int) $data['order_id'] : null;

            if ($orderReference !== null && $orderReference > 0) {
                return match ($user->effectiveMarketplaceRole()) {
                    UserRole::Vendor => route('vendor.orders.show', ['orderReference' => $orderReference]),
                    UserRole::Admin => route('admin.orders'),
                    default => route('shop.orders.show', ['orderReference' => $orderReference]),
                };
            }

            return match ($user->effectiveMarketplaceRole()) {
                UserRole::Vendor => route('vendor.orders'),
                UserRole::Admin => route('admin.orders'),
                default => route('shop.orders'),
            };
        }

        if ($notification->type === NotificationType::Message) {
            return route('messages.inbox');
        }

        return route($user->homeRoute());
    }

    private function resolveNotification(int $notificationId): Notification
    {
        return Notification::query()
            ->whereKey($notificationId)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }
};
?>

<div wire:poll.30s class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <x-page-heading
        :kicker="__('Notification center')"
        icon="fa-solid fa-bell"
        :title="__('Notifications')"
        :description="__('Review order updates, messages, and SukiMarket system notices in one tidy feed.')"
    />

    <section class="brand-panel p-4 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex gap-2 overflow-x-auto pb-1">
                @foreach ([
                    'all' => __('All'),
                    'unread' => __('Unread'),
                    'order_update' => __('Order updates'),
                    'message' => __('Messages'),
                    'system' => __('System'),
                ] as $filterKey => $label)
                    <button
                        type="button"
                        wire:click="setFilter('{{ $filterKey }}')"
                        @class([
                            'shrink-0 rounded-full border px-4 py-2 text-sm font-semibold transition-all duration-150 active:scale-[0.97]',
                            'border-[var(--brand-300)] bg-[var(--brand-50)] text-[var(--brand-700)] dark:border-[var(--brand-500)] dark:bg-[color:oklch(from_var(--brand-500)_l_c_h_/_0.14)] dark:text-[var(--brand-300)]' => $filter === $filterKey,
                            'border-stone-200 bg-white text-neutral-600 hover:border-[var(--brand-300)] hover:text-[var(--brand-700)] dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-300' => $filter !== $filterKey,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <button type="button" wire:click="markAllAsRead" wire:loading.attr="disabled" class="brand-button-secondary active:scale-[0.96]">
                <i class="fa-solid fa-check-double text-xs"></i>
                {{ __('Mark all as read') }}
            </button>
        </div>
    </section>

    <section wire:loading.class="opacity-60 blur-[0.5px]" wire:target="filter,setFilter,markAllAsRead,openNotification,deleteNotification,gotoPage,previousPage,nextPage" class="space-y-4 transition">
        @forelse ($this->notifications->groupBy(fn ($notification) => $notification->created_at?->format('F j, Y') ?? __('Earlier')) as $date => $notifications)
            <div class="space-y-3" wire:key="notification-date-{{ Str::slug($date) }}">
                <p class="px-1 text-xs font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ $date }}</p>

                @foreach ($notifications as $notification)
                    @php($modalName = 'delete-notification-'.$notification->getKey())

                    <article
                        wire:key="notification-row-{{ $notification->getKey() }}"
                        wire:transition
                        style="transition-delay: {{ min($loop->index * 60, 400) }}ms"
                        @class([
                            'brand-panel suki-reveal flex flex-col gap-4 p-5 transition-all duration-300 sm:flex-row sm:items-start',
                            'border-2 border-[var(--brand-500)] bg-[color:color-mix(in_oklab,var(--brand-50),white_35%)] dark:border-[var(--brand-500)] dark:bg-zinc-800/90' => ! $notification->is_read,
                        ])
                    >
                        <button type="button" wire:click="openNotification({{ $notification->getKey() }})" class="flex min-w-0 flex-1 items-start gap-4 text-left">
                            <span class="brand-soft-surface flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl">
                                <i class="{{ $this->notificationIcon($notification->type) }}"></i>
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $notification->title }}</span>
                                    @unless ($notification->is_read)
                                        <span class="h-2.5 w-2.5 rounded-full bg-[var(--brand-600)]"></span>
                                    @endunless
                                </span>
                                <span class="mt-2 block line-clamp-2 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                                    {{ $notification->message }}
                                </span>
                                <span class="mt-3 block text-xs font-medium text-neutral-400 dark:text-zinc-500">
                                    {{ $notification->timeAgo() }}
                                </span>
                            </span>
                        </button>

                        <button
                            type="button"
                            x-data
                            x-on:click="$flux.modal('{{ $modalName }}').show()"
                            class="self-start rounded-full p-2 text-neutral-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-300"
                            aria-label="{{ __('Delete notification') }}"
                        >
                            <i class="fa-solid fa-trash-can text-sm"></i>
                        </button>

                        <x-confirmation-modal
                            :name="$modalName"
                            :heading="__('Delete notification?')"
                            :body="__('This notification will be removed from your feed.')"
                            :confirm-label="__('Delete')"
                            :confirm-action="'deleteNotification('.$notification->getKey().')'"
                        />
                    </article>
                @endforeach
            </div>
        @empty
            <x-empty-state
                icon="fa-regular fa-bell"
                :heading="__('No notifications here')"
                :body="__('When there is something new for this filter, it will appear here.')"
                :action-label="__('Back to dashboard')"
                :action-route="route(auth()->user()->homeRoute())"
            />
        @endforelse

        @if ($this->notifications->hasPages())
            <div class="pt-4">
                {{ $this->notifications->onEachSide(1)->links() }}
            </div>
        @endif
    </section>
</div>
