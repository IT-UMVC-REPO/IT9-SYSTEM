<?php

use App\Models\ConversationGroup;
use App\Models\ConversationGroupMember;
use App\Models\GroupMessage;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $activeConversationUserId = null;

    public ?int $activeGroupId = null;

    #[Computed]
    public function threads(): Collection
    {
        return $this->directThreads()
            ->concat($this->groupThreads())
            ->sortByDesc('latest_at')
            ->values();
    }

    public function otherParticipant(Message $message): User
    {
        return $message->sender_id === auth()->id()
            ? $message->receiver
            : $message->sender;
    }

    public function displayNameFor(User $user): string
    {
        return $user->nicknameFor((int) auth()->id()) ?? $user->name;
    }

    /**
     * @return array<int, array{userId: int, key: string}>
     */
    public function directPresenceConversations(): array
    {
        return $this->threads
            ->where('type', 'direct')
            ->map(fn (array $thread): array => [
                'userId' => (int) $thread['id'],
                'key' => Message::conversationKey((int) $thread['id']),
            ])
            ->values()
            ->all();
    }

    private function directThreads(): Collection
    {
        return Message::query()
            ->where(function ($query): void {
                $query
                    ->where('sender_id', auth()->id())
                    ->orWhere('receiver_id', auth()->id());
            })
            ->with([
                'sender:id,name,profile_image',
                'receiver:id,name,profile_image',
                'order:id,order_status',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn (Message $message): string => Message::conversationKey(
                $message->sender_id === auth()->id() ? $message->receiver_id : $message->sender_id,
            ))
            ->map(function (Collection $messages): array {
                $message = $messages->first();
                $otherUser = $this->otherParticipant($message);
                $unreadCount = Message::query()
                    ->where('sender_id', $otherUser->getKey())
                    ->where('receiver_id', auth()->id())
                    ->where('is_read', false)
                    ->count();

                return [
                    'type' => 'direct',
                    'id' => $otherUser->getKey(),
                    'title' => $this->displayNameFor($otherUser),
                    'preview' => Str::limit($message->content ?: __('Attachment'), 60),
                    'latest_at' => $message->created_at,
                    'time' => $message->timeAgo(),
                    'unread_count' => $unreadCount,
                    'message' => $message,
                    'user' => $otherUser,
                ];
            })
            ->values();
    }

    private function groupThreads(): Collection
    {
        return ConversationGroupMember::query()
            ->where('user_id', auth()->id())
            ->with([
                'group.memberUsers:id,name,profile_image',
                'group.latestMessage.sender:id,name',
            ])
            ->get()
            ->map(function (ConversationGroupMember $membership): array {
                $group = $membership->group;
                $latestMessage = $group?->latestMessage;
                $unreadCount = $this->groupUnreadCount($membership);
                $preview = __('No messages yet');

                if ($latestMessage instanceof GroupMessage) {
                    $senderName = Str::before($latestMessage->sender?->name ?? __('Someone'), ' ');
                    $preview = $senderName.': '.Str::limit($latestMessage->content ?: __('Attachment'), 54);
                }

                return [
                    'type' => 'group',
                    'id' => $group?->getKey(),
                    'title' => $group?->displayName((int) auth()->id()) ?? __('Group conversation'),
                    'preview' => $preview,
                    'latest_at' => $latestMessage?->created_at ?? $membership->joined_at,
                    'time' => $latestMessage?->timeAgo() ?? $membership->joined_at?->diffForHumans(),
                    'unread_count' => $unreadCount,
                    'group' => $group,
                ];
            })
            ->filter(fn (array $thread): bool => $thread['id'] !== null)
            ->values();
    }

    private function groupUnreadCount(ConversationGroupMember $membership): int
    {
        return GroupMessage::query()
            ->where('group_id', $membership->group_id)
            ->where('sender_id', '!=', auth()->id())
            ->when(
                $membership->last_read_at !== null,
                fn ($query) => $query->where('created_at', '>', $membership->last_read_at),
            )
            ->count();
    }
};
?>

<div
    wire:poll.15s
    x-data="conversationSidebarPresence({
        conversations: @js($this->directPresenceConversations()),
    })"
    x-init="init()"
    x-on:destroy="destroy()"
    class="h-full"
>
    @if ($this->threads->isNotEmpty())
        <section class="brand-panel flex h-full flex-col overflow-hidden p-3 sm:p-4">
            <div class="min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
                @foreach ($this->threads as $thread)
                    @php($isGroup = $thread['type'] === 'group')
                    @php($isActiveConversation = ! $isGroup && $activeConversationUserId === $thread['id'])
                    @php($isActiveGroup = $isGroup && $activeGroupId === $thread['id'])

                    <a
                        href="{{ $isGroup ? route('messages.group', ['groupId' => $thread['id']]) : route('messages.conversation', ['conversationReference' => $thread['id']]) }}"
                        wire:key="message-thread-{{ $thread['type'] }}-{{ $thread['id'] }}"
                        wire:navigate
                        @if ($isActiveConversation || $isActiveGroup) aria-current="page" @endif
                        @class([
                            'flex items-start gap-4 rounded-[1.5rem] border px-4 py-4 transition',
                            'border-[var(--brand-200)] bg-[color:color-mix(in_oklab,var(--brand-50),white_30%)] dark:border-[var(--brand-500)] dark:bg-zinc-800/90' => $isActiveConversation || $isActiveGroup,
                            'border-transparent hover:border-stone-200 hover:bg-stone-50/70 dark:hover:border-white/10 dark:hover:bg-white/5' => ! $isActiveConversation && ! $isActiveGroup,
                        ])
                    >
                        @if ($isGroup)
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[var(--brand-100)] text-[var(--brand-700)] dark:bg-white/10 dark:text-[var(--brand-300)]">
                                <i class="fa-solid fa-user-group text-sm"></i>
                            </span>
                        @else
                            <div class="relative shrink-0">
                                <x-user-avatar :user="$thread['user']" size="md" />
                                <span
                                    x-cloak
                                    x-show="isOnline(@js($thread['id']))"
                                    x-transition.opacity
                                    class="absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-white bg-emerald-500 dark:border-zinc-900"
                                    title="{{ __('Online') }}"
                                ></span>
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-base font-semibold text-neutral-900 dark:text-zinc-100">{{ $thread['title'] }}</p>
                                    <p @class([
                                        'mt-1 truncate text-sm',
                                        'font-semibold text-neutral-900 dark:text-zinc-100' => $thread['unread_count'] > 0,
                                        'text-neutral-500 dark:text-zinc-400' => $thread['unread_count'] === 0,
                                    ])>
                                        {{ $thread['preview'] }}
                                    </p>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    @if ($thread['unread_count'] > 0)
                                        <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-[var(--brand-600)] px-1.5 py-0.5 text-[10px] font-semibold leading-none text-white">
                                            {{ $thread['unread_count'] > 99 ? '99+' : $thread['unread_count'] }}
                                        </span>
                                    @endif

                                    <span class="text-xs font-medium text-neutral-400 dark:text-zinc-500">{{ $thread['time'] }}</span>
                                </div>
                            </div>

                            @if (! $isGroup && $thread['message']->order !== null)
                                <p class="mt-2 text-xs font-medium text-neutral-400 dark:text-zinc-500">
                                    {{ __('Order context: :status', ['status' => Str::headline($thread['message']->order->order_status->value)]) }}
                                </p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @else
        <section class="brand-panel px-6 py-16 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl text-neutral-600 dark:text-zinc-300" style="background-color: color-mix(in oklab, var(--brand-50) 72%, white 28%);">
                <i class="fa-regular fa-comments text-xl"></i>
            </span>
            <h2 class="brand-serif mt-5 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('No conversations yet') }}</h2>
            <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                {{ __('Start from a product or vendor page when you are ready to message a stall.') }}
            </p>
        </section>
    @endif
</div>
