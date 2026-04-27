<?php

use App\Enums\NotificationType;
use App\Models\Notification;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    #[On('notification-created')]
    public function refreshNotifications(): void
    {
        unset($this->unreadCount);
        unset($this->notifications);
    }

    public function getListeners(): array
    {
        if (! auth()->check()) {
            return [];
        }

        return [
            'echo-private:notifications.'.auth()->id().',.NotificationCreated' => 'handleBroadcastNotification',
        ];
    }

    public function handleBroadcastNotification(): void
    {
        $this->dispatch('notification-created');
    }

    public function markAllAsRead(): void
    {
        abort_unless(auth()->check(), 403);

        DB::transaction(function (): void {
            Notification::query()
                ->where('user_id', auth()->id())
                ->where('is_read', false)
                ->update(['is_read' => true]);
        });

        unset($this->unreadCount);
        unset($this->notifications);

        Flux::toast(variant: 'success', text: __('All notifications marked as read.'));
    }

    public function markAsRead(int $notificationId): void
    {
        abort_unless(auth()->check(), 403);

        Notification::query()
            ->whereKey($notificationId)
            ->where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        unset($this->unreadCount);
        unset($this->notifications);
    }

    #[Computed]
    public function unreadCount(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        return Notification::query()
            ->where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();
    }

    #[Computed]
    public function notifications(): Collection
    {
        if (! auth()->check()) {
            return collect();
        }

        return Notification::query()
            ->where('user_id', auth()->id())
            ->latest('created_at')
            ->limit(5)
            ->get();
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

    public function notificationBadgeStyle(NotificationType $type): string
    {
        return match ($type) {
            NotificationType::NewProduct => 'background-color: color-mix(in oklab, var(--brand-100) 78%, white 22%); color: var(--brand-700);',
            NotificationType::OrderUpdate => 'background-color: color-mix(in oklab, var(--brand-200) 72%, white 28%); color: var(--brand-800);',
            NotificationType::Message => 'background-color: color-mix(in oklab, var(--brand-50) 55%, white 45%); color: var(--brand-700);',
            NotificationType::System => 'background-color: color-mix(in oklab, var(--brand-100) 70%, white 30%); color: var(--brand-700);',
        };
    }
};
?>

<div x-data="{ open: false }" x-on:keydown.escape.window="open = false" class="relative">
    <button
        type="button"
        x-on:click="open = !open"
        x-bind:aria-expanded="open.toString()"
        class="relative flex h-9 w-9 items-center justify-center rounded-xl text-stone-500 transition hover:bg-stone-100 hover:text-stone-900 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white"
        title="{{ __('Notifications') }}"
        aria-label="{{ __('Notifications') }}"
    >
        <i class="fa-regular fa-bell text-sm"></i>

        @if ($this->unreadCount > 0)
            <span class="absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold leading-none text-white shadow-sm" style="background-color: var(--brand-600);">
                {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition.origin.top.right
        x-on:click.outside="open = false"
        class="absolute right-0 top-[calc(100%+0.75rem)] z-50 w-80 overflow-hidden rounded-3xl border border-stone-200 bg-white shadow-2xl dark:border-white/10 dark:bg-zinc-900"
    >
        <div class="flex items-center justify-between border-b border-stone-200 px-5 py-4 dark:border-white/10">
            <div>
                <p class="brand-kicker !mb-0">{{ __('Updates') }}</p>
                <h2 class="brand-serif mt-2 text-xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Notifications') }}</h2>
            </div>

            <button
                type="button"
                wire:click="markAllAsRead"
                wire:loading.attr="disabled"
                wire:target="markAllAsRead"
                class="text-xs font-semibold uppercase tracking-[0.18em]"
                style="color: var(--brand-700);"
            >
                <span wire:loading.remove wire:target="markAllAsRead">{{ __('Mark all as read') }}</span>
                <span wire:loading wire:target="markAllAsRead">{{ __('Saving...') }}</span>
            </button>
        </div>

        <div class="max-h-96 space-y-2 overflow-y-auto px-3 py-3">
            @forelse ($this->notifications as $notification)
                <button
                    type="button"
                    wire:click="markAsRead({{ $notification->id }})"
                    wire:key="header-notification-{{ $notification->id }}"
                    class="w-full rounded-2xl border px-4 py-3 text-left transition"
                    style="{{ $notification->is_read
                        ? 'border-color: rgb(231 229 228); background-color: rgba(255,255,255,0.72);'
                        : 'border-color: var(--brand-200); background-color: color-mix(in oklab, var(--brand-50) 65%, white 35%);' }}"
                >
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm" style="{{ $this->notificationBadgeStyle($notification->type) }}">
                            <i class="{{ $this->notificationIcon($notification->type) }}"></i>
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ $notification->title }}</p>
                                    <p class="mt-1 text-sm leading-6 text-neutral-500 dark:text-zinc-400">
                                        {{ Str::limit($notification->message, 84) }}
                                    </p>
                                </div>

                                @if (! $notification->is_read)
                                    <span class="mt-1 inline-flex h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: var(--brand-600);"></span>
                                @endif
                            </div>

                            <div class="mt-3 flex items-center justify-end">
                                <p class="text-xs font-medium text-neutral-400 dark:text-zinc-500">{{ $notification->timeAgo() }}</p>
                            </div>
                        </div>
                    </div>
                </button>
            @empty
                <div class="px-4 py-10 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl text-neutral-500 dark:text-zinc-400" style="background-color: color-mix(in oklab, var(--brand-50) 70%, white 30%);">
                        <i class="fa-regular fa-bell text-lg"></i>
                    </span>
                    <p class="mt-4 text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ __('No notifications yet') }}</p>
                    <p class="mt-2 text-sm leading-6 text-neutral-500 dark:text-zinc-400">
                        {{ __('New order updates, vendor approvals, and system messages will show up here.') }}
                    </p>
                </div>
            @endforelse
        </div>

        <div class="border-t border-stone-200 px-5 py-4 dark:border-white/10">
            <a
                href="{{ route('notifications.index') }}"
                wire:navigate
                x-on:click="open = false"
                class="inline-flex text-sm font-semibold"
                style="color: var(--brand-700);"
            >
                {{ __('See all') }}
            </a>
        </div>
    </div>
</div>
