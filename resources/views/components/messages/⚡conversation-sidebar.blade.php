<?php

use App\Models\ConversationGroup;
use App\Models\ConversationGroupMember;
use App\Models\GroupMessage;
use App\Models\ChatParticipantState;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatParticipantStateService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
            ->sortBy([
                ['is_pinned', 'desc'],
                ['latest_at', 'desc'],
            ])
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

    public function archiveDirectThread(int $directUserId): void
    {
        app(ChatParticipantStateService::class)->archive(ChatParticipantState::forDirect(auth()->user(), $directUserId));

        unset($this->threads);
    }

    public function deleteDirectThread(int $directUserId): void
    {
        app(ChatParticipantStateService::class)->delete(ChatParticipantState::forDirect(auth()->user(), $directUserId));

        unset($this->threads);
    }

    public function archiveGroupThread(int $groupId): void
    {
        ConversationGroupMember::query()
            ->where('group_id', $groupId)
            ->where('user_id', auth()->id())
            ->update(['archived_at' => now()]);

        unset($this->threads);
    }

    public function deleteGroupThread(int $groupId): void
    {
        ConversationGroupMember::query()
            ->where('group_id', $groupId)
            ->where('user_id', auth()->id())
            ->delete();

        unset($this->threads);
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
        $userId = (int) auth()->id();
        $latestMessageIds = DB::query()
            ->fromSub(
                Message::withTrashed()
                    ->select('id')
                    ->selectRaw('ROW_NUMBER() OVER (PARTITION BY LEAST(sender_id, receiver_id), GREATEST(sender_id, receiver_id) ORDER BY created_at DESC, id DESC) as thread_rank')
                    ->where(function ($query) use ($userId): void {
                        $query
                            ->where('sender_id', $userId)
                            ->orWhere('receiver_id', $userId);
                    }),
                'ranked_messages',
            )
            ->where('thread_rank', 1)
            ->pluck('id');

        if ($latestMessageIds->isEmpty()) {
            return collect();
        }

        $unreadCounts = Message::query()
            ->select('sender_id')
            ->selectRaw('count(*) as aggregate')
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->groupBy('sender_id')
            ->pluck('aggregate', 'sender_id');

        $states = ChatParticipantState::withTrashed()
            ->where('user_id', $userId)
            ->where('chat_type', 'direct')
            ->whereNotNull('direct_user_id')
            ->get()
            ->keyBy('direct_user_id');

        return Message::withTrashed()
            ->whereIn('id', $latestMessageIds)
            ->with([
                'sender:id,name,profile_image',
                'receiver:id,name,profile_image',
                'order:id,order_status',
            ])
            ->get()
            ->map(function (Message $message) use ($states, $unreadCounts): ?array {
                $otherUser = $this->otherParticipant($message);
                $state = $states->get($otherUser->getKey());
                $displayName = $this->displayNameFor($otherUser);

                if ($state?->deleted_at !== null || $state?->archived_at !== null) {
                    return null;
                }

                $unreadCount = (int) ($unreadCounts[$otherUser->getKey()] ?? 0);

                if ($state?->marked_unread_at !== null && $unreadCount === 0) {
                    $unreadCount = 1;
                }

                return [
                    'type' => 'direct',
                    'id' => $otherUser->getKey(),
                    'title' => $displayName,
                    'preview' => Str::limit($message->content ?: __('Attachment'), 60),
                    'latest_at' => $message->created_at,
                    'is_pinned' => $state?->pinned_at !== null,
                    'is_muted' => $state?->isMuted() ?? false,
                    'label' => $state?->label,
                    'time' => $message->timeAgo(),
                    'unread_count' => $unreadCount,
                    'order_status' => $message->order?->order_status?->value,
                    'user' => [
                        'id' => $otherUser->getKey(),
                        'name' => $displayName,
                        'initials' => collect(explode(' ', $displayName))
                            ->filter()
                            ->map(fn (string $part): string => mb_substr($part, 0, 1))
                            ->take(2)
                            ->implode(''),
                        'profile_image_url' => $otherUser->profile_image_url,
                    ],
                ];
            })
            ->filter()
            ->sortByDesc('latest_at')
            ->values();
    }

    private function groupThreads(): Collection
    {
        $userId = (int) auth()->id();
        $memberships = ConversationGroupMember::query()
            ->where('user_id', auth()->id())
            ->whereNull('archived_at')
            ->with([
                'group.memberUsers:id,name,profile_image',
                'group.latestMessage.sender:id,name',
            ])
            ->get();

        $unreadCounts = GroupMessage::query()
            ->select('group_messages.group_id')
            ->selectRaw('count(*) as aggregate')
            ->join('conversation_group_members as membership', 'membership.group_id', '=', 'group_messages.group_id')
            ->where('membership.user_id', $userId)
            ->where('group_messages.sender_id', '!=', $userId)
            ->where(function ($query): void {
                $query
                    ->whereNull('membership.last_read_at')
                    ->orWhereColumn('group_messages.created_at', '>', 'membership.last_read_at');
            })
            ->groupBy('group_messages.group_id')
            ->pluck('aggregate', 'group_messages.group_id');

        return $memberships
            ->map(function (ConversationGroupMember $membership) use ($unreadCounts): array {
                $group = $membership->group;
                $latestMessage = $group?->latestMessage;
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
                    'is_pinned' => $membership->pinned_at !== null,
                    'is_muted' => $membership->muted_until !== null && $membership->muted_until->isFuture(),
                    'label' => null,
                    'time' => $latestMessage?->timeAgo() ?? $membership->joined_at?->diffForHumans(),
                    'unread_count' => $membership->marked_unread_at !== null ? max(1, (int) ($unreadCounts[$membership->group_id] ?? 0)) : (int) ($unreadCounts[$membership->group_id] ?? 0),
                    'avatar_url' => $group?->avatar_url,
                    'group' => $group,
                ];
            })
            ->filter(fn (array $thread): bool => $thread['id'] !== null)
            ->values();
    }
};
?>

<div
    wire:poll.visible.60s
    x-data="conversationSidebarPresence({
        conversations: @js($this->directPresenceConversations()),
        deferPresence: true,
        presenceDelay: 2500,
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
                        wire:transition
                        wire:navigate
                        @if ($isActiveConversation || $isActiveGroup) aria-current="page" @endif
                        @class([
                            'flex items-start gap-4 rounded-[1.5rem] border px-4 py-4 transition-all duration-200',
                            'border-[var(--brand-200)] bg-[color:color-mix(in_oklab,var(--brand-50),white_30%)] dark:border-[var(--brand-500)] dark:bg-zinc-800/90' => $isActiveConversation || $isActiveGroup,
                            'border-transparent hover:border-stone-200 hover:bg-stone-50/70 dark:hover:border-white/10 dark:hover:bg-white/5' => ! $isActiveConversation && ! $isActiveGroup,
                        ])
                    >
                        @if ($isGroup)
                            @if (filled($thread['avatar_url'] ?? null))
                                <img src="{{ $thread['avatar_url'] }}" alt="{{ $thread['title'] }}" class="h-10 w-10 shrink-0 rounded-full object-cover ring-2 ring-stone-200 dark:ring-white/10" loading="lazy">
                            @else
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[var(--brand-100)] text-[var(--brand-700)] dark:bg-white/10 dark:text-[var(--brand-300)]">
                                    <i class="fa-solid fa-user-group text-sm"></i>
                                </span>
                            @endif
                        @else
                            <div
                                @class([
                                    'shrink-0 rounded-full p-0.5 transition',
                                    'ring-2 ring-emerald-400 ring-offset-2 ring-offset-white dark:ring-emerald-300 dark:ring-offset-zinc-950' => false,
                                ])
                                x-bind:class="initialized && isOnline(@js($thread['id'])) ? 'ring-2 ring-emerald-400 ring-offset-2 ring-offset-white dark:ring-emerald-300 dark:ring-offset-zinc-950' : ''"
                                title="{{ __('Online') }}"
                            >
                                <x-user-avatar :user="$thread['user']" size="md" />
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-base font-semibold text-neutral-900 dark:text-zinc-100">{{ $thread['title'] }}</p>
                                    @if ($thread['is_pinned'] || $thread['is_muted'] || filled($thread['label']))
                                        <div class="mt-1 flex flex-wrap gap-1.5 text-[10px] font-semibold uppercase tracking-wide">
                                            @if ($thread['is_pinned'])
                                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">{{ __('Pinned') }}</span>
                                            @endif
                                            @if ($thread['is_muted'])
                                                <span class="rounded-full bg-stone-100 px-2 py-0.5 text-neutral-500 dark:bg-white/10 dark:text-zinc-300">{{ __('Muted') }}</span>
                                            @endif
                                            @if (filled($thread['label']))
                                                <span class="rounded-full bg-[var(--brand-50)] px-2 py-0.5 text-[var(--brand-700)] dark:bg-white/10 dark:text-[var(--brand-300)]">{{ $thread['label'] }}</span>
                                            @endif
                                        </div>
                                    @endif
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

                            @if (! $isGroup && filled($thread['order_status']))
                                <p class="mt-2 text-xs font-medium text-neutral-400 dark:text-zinc-500">
                                    {{ __('Order context: :status', ['status' => Str::headline($thread['order_status'])]) }}
                                </p>
                            @endif

                            <div class="mt-3 flex items-center gap-1">
                                <button type="button" wire:click.prevent.stop="{{ $isGroup ? 'archiveGroupThread('.$thread['id'].')' : 'archiveDirectThread('.$thread['id'].')' }}" class="inline-flex h-7 w-7 items-center justify-center rounded-full text-neutral-400 transition hover:bg-stone-100 hover:text-neutral-700 dark:hover:bg-white/10 dark:hover:text-white" aria-label="{{ __('Archive chat') }}">
                                    <flux:icon.archive-box variant="micro" class="h-3.5 w-3.5" />
                                </button>
                                <button type="button" wire:click.prevent.stop="{{ $isGroup ? 'deleteGroupThread('.$thread['id'].')' : 'deleteDirectThread('.$thread['id'].')' }}" wire:confirm="{{ __('Delete this chat from your inbox?') }}" class="inline-flex h-7 w-7 items-center justify-center rounded-full text-rose-400 transition hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-400/10 dark:hover:text-rose-300" aria-label="{{ __('Delete chat') }}">
                                    <flux:icon.trash variant="micro" class="h-3.5 w-3.5" />
                                </button>
                            </div>
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
