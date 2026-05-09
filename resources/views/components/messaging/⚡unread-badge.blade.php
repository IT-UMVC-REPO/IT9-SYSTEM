<?php

use App\Models\ConversationGroupMember;
use App\Models\GroupMessage;
use App\Models\Message;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $isActive = false;

    public function mount(bool $isActive = false): void
    {
        $this->isActive = $isActive;
    }

    #[On('message-marked-read')]
    #[On('message-sent')]
    #[On('group-message-sent')]
    public function refreshBadge(): void
    {
        unset($this->unreadCount);
    }

    #[Computed]
    public function unreadCount(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        $directUnread = Message::query()
            ->where('receiver_id', auth()->id())
            ->where('is_read', false)
            ->count();

        $groupUnread = ConversationGroupMember::query()
            ->where('user_id', auth()->id())
            ->get(['group_id', 'last_read_at'])
            ->sum(function (ConversationGroupMember $member): int {
                return GroupMessage::query()
                    ->where('group_id', $member->group_id)
                    ->where('sender_id', '!=', auth()->id())
                    ->when(
                        $member->last_read_at !== null,
                        fn ($query) => $query->where('created_at', '>', $member->last_read_at),
                    )
                    ->count();
            });

        return $directUnread + $groupUnread;
    }
};
?>

<a
    href="{{ route('messages.inbox') }}"
    title="{{ __('Messages') }}"
    wire:navigate
    wire:poll.15s
    class="relative flex h-9 w-9 items-center justify-center rounded-xl transition-all duration-150 active:scale-90 {{ $isActive ? 'quick-action-active' : 'text-stone-500 hover:bg-stone-100 hover:text-stone-900 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white' }}"
>
    <i class="fa-solid fa-comments text-sm"></i>

    @if ($this->unreadCount > 0)
        <span
            x-data
            x-show="$wire.unreadCount > 0"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-50"
            x-transition:enter-end="opacity-100 scale-100"
            class="absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-[var(--brand-600)] px-1.5 py-0.5 text-[10px] font-semibold leading-none text-white shadow-sm transition-all duration-300"
        >
            {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
        </span>
    @endif
</a>
