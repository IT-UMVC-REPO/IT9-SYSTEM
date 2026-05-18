<?php

namespace App\Livewire\Pages\Messages;

use App\Enums\VideoCallStatus;
use App\Events\GroupMessageSent;
use App\Models\ConversationGroup;
use App\Models\ConversationGroupMember;
use App\Models\GroupMessage;
use App\Models\GroupMessageAttachment;
use App\Models\GroupMessageReaction;
use App\Models\User;
use App\Models\VideoCall;
use App\Services\GroupManagementService;
use App\Services\MessageMutationService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Group conversation')]
class GroupConversation extends Component
{
    use WithFileUploads;

    public int $groupId;

    #[Validate('nullable|string|max:2000')]
    public string $newMessage = '';

    /**
     * @var array<int, mixed>
     */
    #[Validate([
        'attachmentUploads' => ['array', 'max:5'],
        'attachmentUploads.*' => ['file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,application/pdf,video/mp4,video/quicktime,audio/mpeg,audio/wav,audio/ogg', 'extensions:jpg,jpeg,png,webp,gif,pdf,mp4,mov,mp3,wav,ogg'],
    ])]
    public array $attachmentUploads = [];

    public bool $showInfoPanel = true;

    public string $memberSearch = '';

    public string $groupName = '';

    public ?string $groupDescription = null;

    public ?int $maxMembers = null;

    public bool $approvalRequired = false;

    public ?int $replyingToId = null;

    public ?string $replyingToContent = null;

    public ?string $replyingToSender = null;

    public ?int $incomingCallId = null;

    public bool $callInProgress = false;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $messages = [];

    public function mount(int $groupId): void
    {
        abort_unless($this->isMember($groupId), 403);

        $this->groupId = $groupId;
        $this->incomingCallId = $this->resolveIncomingGroupCallId();
        $group = ConversationGroup::query()->findOrFail($groupId);
        $this->groupName = $group->name ?? '';
        $this->groupDescription = $group->description;
        $this->maxMembers = $group->max_members;
        $this->approvalRequired = (bool) $group->approval_required;

        unset($this->activeGroupCall);

        $this->markRead();
        $this->loadMessages();
    }

    public function getListeners(): array
    {
        return [
            'echo-private:group.'.$this->groupId.',.GroupMessageSent' => 'handleIncomingMessage',
        ];
    }

    public function handleIncomingMessage(): void
    {
        $this->loadMessages();
        $this->markRead();
        $this->dispatch('group-message-sent');
    }

    public function send(?string $messageText = null): void
    {
        if ($messageText !== null) {
            $this->newMessage = $messageText;
        }

        $this->validate();
        $this->validateAttachmentTotalSize();

        $trimmedMessage = trim($this->newMessage);

        if ($trimmedMessage === '' && $this->attachmentUploads === []) {
            return;
        }

        $storedPaths = [];

        try {
            $message = DB::transaction(function () use (&$storedPaths, $trimmedMessage): GroupMessage {
                $message = GroupMessage::query()->create([
                    'group_id' => $this->groupId,
                    'sender_id' => auth()->id(),
                    'content' => $trimmedMessage,
                    'reply_to_id' => $this->replyingToId,
                ]);

                foreach ($this->attachmentUploads as $upload) {
                    $path = $upload->store('group-message-attachments', 'public');
                    $storedPaths[] = $path;

                    GroupMessageAttachment::query()->create([
                        'group_message_id' => $message->getKey(),
                        'path' => $path,
                        'name' => $upload->getClientOriginalName(),
                        'mime' => $upload->getMimeType(),
                        'size' => $upload->getSize(),
                    ]);
                }

                return $message;
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);

            throw $exception;
        }

        $this->messages[] = $this->messageToArray($message);

        try {
            event(new GroupMessageSent($message));
        } catch (\Throwable $broadcastException) {
            Log::warning('GroupMessageSent broadcast failed (Pusher may be unavailable): '.$broadcastException->getMessage());
        }

        $this->newMessage = '';
        $this->attachmentUploads = [];
        $this->clearReply();
        $this->resetValidation(['newMessage', 'attachmentUploads', 'attachmentUploads.*']);

        $this->markRead();
        $this->dispatch('group-message-sent');
    }

    public function refreshThread(bool $shouldScroll = false): void
    {
        if ($this->callInProgress) {
            $this->skipRender();

            return;
        }

        $this->markRead();
        $this->loadMessages();
        unset($this->group);
        unset($this->activeGroupCall);

        if ($shouldScroll) {
            $this->dispatch('group-message-sent');
        }
    }

    public function removeAttachmentUpload(int $index): void
    {
        if (! array_key_exists($index, $this->attachmentUploads)) {
            return;
        }

        unset($this->attachmentUploads[$index]);
        $this->attachmentUploads = array_values($this->attachmentUploads);
        $this->resetValidation(['attachmentUploads', 'attachmentUploads.*']);
    }

    public function addMember(int $userId): void
    {
        abort_unless($this->isGroupAdmin(), 403);

        if ($userId === auth()->id() || $this->isMember($this->groupId, $userId)) {
            return;
        }

        if ($this->group->max_members !== null && $this->members->count() >= $this->group->max_members) {
            throw ValidationException::withMessages([
                'memberSearch' => __('This group has reached its member limit.'),
            ]);
        }

        ConversationGroupMember::query()->create([
            'group_id' => $this->groupId,
            'user_id' => $userId,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $this->memberSearch = '';
        unset($this->members);
        unset($this->availableMembers);
    }

    public function saveGroupDetails(): void
    {
        app(GroupManagementService::class)->updateDetails(auth()->user(), $this->group, [
            'name' => $this->groupName,
            'description' => $this->groupDescription,
            'max_members' => $this->maxMembers,
            'approval_required' => $this->approvalRequired,
        ]);

        unset($this->group);
        $this->dispatch('message-sent');
    }

    public function setMemberNickname(int $userId, ?string $nickname): void
    {
        app(GroupManagementService::class)->setNickname(auth()->user(), $this->group, $userId, $nickname);

        unset($this->members);
        $this->loadMessages();
        $this->dispatch('message-sent');
    }

    public function promoteMember(int $userId): void
    {
        app(GroupManagementService::class)->promote(auth()->user(), $this->group, $userId);

        unset($this->members);
        $this->dispatch('message-sent');
    }

    public function demoteMember(int $userId): void
    {
        app(GroupManagementService::class)->demote(auth()->user(), $this->group, $userId);

        unset($this->members);
        $this->dispatch('message-sent');
    }

    public function removeMember(int $userId): void
    {
        app(GroupManagementService::class)->remove(auth()->user(), $this->group, $userId);

        unset($this->members);
        unset($this->availableMembers);
        $this->dispatch('message-sent');
    }

    public function transferOwnership(int $userId): void
    {
        app(GroupManagementService::class)->transferOwnership(auth()->user(), $this->group, $userId);

        unset($this->group);
        unset($this->members);
        $this->dispatch('message-sent');
    }

    public function regenerateInviteLink(?int $expiresInMinutes = null, ?int $usageLimit = null): void
    {
        $group = app(GroupManagementService::class)->regenerateInvite(auth()->user(), $this->group, $expiresInMinutes, $usageLimit);

        unset($this->group);
        $this->dispatch('copy-invite-link', url: route('messages.group', ['groupId' => $this->groupId]).'?invite='.$group->invite_token);
        $this->dispatch('message-sent');

        Flux::toast(variant: 'success', text: __('Invite Link Copied'));
    }

    public function leaveGroup(): void
    {
        app(GroupManagementService::class)->leave(auth()->user(), $this->group);

        $this->redirect(route('messages.inbox'), navigate: true);
    }

    public function markRead(): void
    {
        $latestMessageAt = GroupMessage::query()
            ->where('group_id', $this->groupId)
            ->latest('created_at')
            ->latest('id')
            ->value('created_at');

        if ($latestMessageAt === null) {
            return;
        }

        $updatedCount = ConversationGroupMember::query()
            ->where('group_id', $this->groupId)
            ->where('user_id', auth()->id())
            ->where(function (Builder $query) use ($latestMessageAt): void {
                $query
                    ->whereNull('last_read_at')
                    ->orWhere('last_read_at', '<', $latestMessageAt);
            })
            ->update(['last_read_at' => now()]);

        if ($updatedCount > 0) {
            $this->dispatch('message-marked-read');
        }
    }

    public function setReplyTo(int $messageId): void
    {
        $message = GroupMessage::query()
            ->withTrashed()
            ->where('group_id', $this->groupId)
            ->with('sender:id,name,profile_image')
            ->findOrFail($messageId);

        $this->replyingToId = $message->getKey();
        $this->replyingToContent = Str::limit($message->content ?: __('Attachment'), 80);
        $this->replyingToSender = $this->memberDisplayName($message->sender);
    }

    public function clearReply(): void
    {
        $this->replyingToId = null;
        $this->replyingToContent = null;
        $this->replyingToSender = null;
    }

    public function toggleReaction(int $messageId, string $emoji): void
    {
        $allowedReactions = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

        if (! in_array($emoji, $allowedReactions, true)) {
            return;
        }

        abort_unless(
            GroupMessage::query()
                ->where('group_id', $this->groupId)
                ->whereKey($messageId)
                ->exists(),
            404,
        );

        $existingReaction = GroupMessageReaction::query()
            ->where('group_message_id', $messageId)
            ->where('user_id', auth()->id())
            ->where('emoji', $emoji)
            ->first();

        if ($existingReaction !== null) {
            $existingReaction->delete();
        } else {
            GroupMessageReaction::query()->create([
                'group_message_id' => $messageId,
                'user_id' => auth()->id(),
                'emoji' => $emoji,
            ]);
        }

        $this->loadMessages();
    }

    public function editMessage(int $messageId, string $content): void
    {
        $message = GroupMessage::query()
            ->withTrashed()
            ->where('group_id', $this->groupId)
            ->findOrFail($messageId);

        app(MessageMutationService::class)->editGroup(auth()->user(), $message, $content);

        $this->loadMessages();
        $this->dispatch('group-message-sent');
    }

    public function pinMessage(int $messageId): void
    {
        $message = GroupMessage::query()
            ->where('group_id', $this->groupId)
            ->findOrFail($messageId);

        app(MessageMutationService::class)->pinGroup(auth()->user(), $message);

        $this->loadMessages();
        $this->dispatch('group-message-sent');
    }

    public function deleteMessage(int $messageId, bool $forEveryone = true): void
    {
        $message = GroupMessage::query()
            ->where('group_id', $this->groupId)
            ->findOrFail($messageId);

        app(MessageMutationService::class)->deleteGroup(auth()->user(), $message, $forEveryone);

        $this->loadMessages();
        $this->dispatch('group-message-sent');
    }

    #[Computed]
    public function group(): ConversationGroup
    {
        return ConversationGroup::query()
            ->with(['memberUsers:id,name,profile_image', 'members.user:id,name,profile_image'])
            ->findOrFail($this->groupId);
    }

    #[Computed]
    public function activeGroupCall(): ?VideoCall
    {
        if (! isset($this->groupId)) {
            return null;
        }

        $this->expireInactiveGroupCalls();

        return VideoCall::query()
            ->where('group_id', $this->groupId)
            ->where('is_group_call', true)
            ->whereIn('status', [
                VideoCallStatus::Active->value,
                VideoCallStatus::Pending->value,
            ])
            ->whereNull('ended_at')
            ->where('created_at', '>=', now()->subMinutes(90))
            ->whereHas('participants', function (Builder $query): void {
                $query->whereNull('left_at');
            })
            ->latest('created_at')
            ->first();
    }

    #[Computed]
    public function members(): Collection
    {
        return ConversationGroupMember::query()
            ->where('group_id', $this->groupId)
            ->with('user:id,name,profile_image')
            ->orderByRaw("role = 'admin' desc")
            ->orderBy('joined_at')
            ->get();
    }

    #[Computed]
    public function availableMembers(): Collection
    {
        if (! $this->isGroupAdmin() || trim($this->memberSearch) === '') {
            return collect();
        }

        return User::query()
            ->whereKeyNot(auth()->id())
            ->whereNotIn('id', $this->members->pluck('user_id'))
            ->where('name', 'like', '%'.trim($this->memberSearch).'%')
            ->orderBy('name')
            ->limit(6)
            ->get(['id', 'name', 'profile_image']);
    }

    public function memberDisplayName(User $user): string
    {
        $membership = $this->members
            ->first(fn (ConversationGroupMember $member): bool => $member->user_id === $user->getKey());

        if ($membership instanceof ConversationGroupMember && filled($membership->nickname)) {
            return $membership->nickname;
        }

        return $user->nicknameFor((int) auth()->id()) ?? $user->name;
    }

    /**
     * @return array<int, array{name: string, initials: string}>
     */
    public function groupParticipantSummaries(): array
    {
        return $this->members
            ->mapWithKeys(fn (ConversationGroupMember $member): array => [
                $member->user_id => [
                    'name' => $this->memberDisplayName($member->user),
                    'initials' => $member->user->initials(),
                ],
            ])
            ->all();
    }

    public function messageDateLabel(GroupMessage $message): string
    {
        if ($message->created_at?->isToday()) {
            return __('Today');
        }

        if ($message->created_at?->isYesterday()) {
            return __('Yesterday');
        }

        return $message->created_at?->format('M j, Y') ?? __('Today');
    }

    public function messageTimestamp(GroupMessage $message): string
    {
        return $message->created_at?->format('g:i A') ?? __('Now');
    }

    /**
     * @return Collection<int, GroupMessageAttachment>
     */
    public function attachmentsForDisplay(GroupMessage $message): Collection
    {
        return $message->attachments
            ->map(function (GroupMessageAttachment $attachment): GroupMessageAttachment {
                $attachment->setAttribute('public_url', $this->attachmentPublicUrl($attachment));

                return $attachment;
            });
    }

    public function isGroupAdmin(): bool
    {
        return ConversationGroupMember::query()
            ->where('group_id', $this->groupId)
            ->where('user_id', auth()->id())
            ->where('role', 'admin')
            ->exists();
    }

    private function isMember(int $groupId, ?int $userId = null): bool
    {
        return ConversationGroupMember::query()
            ->where('group_id', $groupId)
            ->where('user_id', $userId ?? auth()->id())
            ->exists();
    }

    private function resolveIncomingGroupCallId(): ?int
    {
        if (! request()->boolean('incoming_call')) {
            return null;
        }

        $requestedCallId = (int) request()->integer('call_id');

        if ($requestedCallId > 0) {
            $call = VideoCall::query()
                ->whereKey($requestedCallId)
                ->where('group_id', $this->groupId)
                ->where('is_group_call', true)
                ->where('status', VideoCallStatus::Pending->value)
                ->first();

            if ($call !== null) {
                return $call->getKey();
            }
        }

        return VideoCall::query()
            ->where('group_id', $this->groupId)
            ->where('is_group_call', true)
            ->where('status', VideoCallStatus::Pending->value)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->latest('created_at')
            ->value('id');
    }

    private function expireInactiveGroupCalls(): void
    {
        VideoCall::query()
            ->where('group_id', $this->groupId)
            ->where('is_group_call', true)
            ->whereIn('status', [
                VideoCallStatus::Active->value,
                VideoCallStatus::Pending->value,
            ])
            ->where(function (Builder $query): void {
                $query
                    ->where('created_at', '<', now()->subMinutes(90))
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('status', VideoCallStatus::Pending->value)
                            ->where('created_at', '<', now()->subMinutes(2));
                    })
                    ->orWhereDoesntHave('participants', function (Builder $query): void {
                        $query->whereNull('left_at');
                    });
            })
            ->update([
                'status' => VideoCallStatus::Ended->value,
                'ended_at' => now(),
            ]);
    }

    private function validateAttachmentTotalSize(): void
    {
        $totalSize = collect($this->attachmentUploads)->sum(fn ($upload): int => (int) $upload->getSize());

        if ($totalSize > 25 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'attachmentUploads' => __('Attachments cannot exceed 25 MB total.'),
            ]);
        }
    }

    private function loadMessages(): void
    {
        $this->messages = GroupMessage::query()
            ->withTrashed()
            ->where('group_id', $this->groupId)
            ->with([
                'sender:id,name,profile_image',
                'attachments',
                'replyTo.sender:id,name,profile_image',
                'reactions.user:id,name',
            ])
            ->latest('created_at')
            ->latest('id')
            ->limit(100)
            ->get()
            ->sortBy('created_at')
            ->map(fn (GroupMessage $message): array => $this->messageToArray($message))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function messageToArray(GroupMessage $message): array
    {
        $message->loadMissing([
            'sender:id,name,profile_image',
            'attachments',
            'replyTo.sender:id,name,profile_image',
            'reactions.user:id,name',
        ]);

        return [
            'id' => $message->getKey(),
            'group_id' => $message->group_id,
            'sender_id' => $message->sender_id,
            'content' => $message->content,
            'reply_to_id' => $message->reply_to_id,
            'is_system_message' => $message->is_system_message,
            'system_event' => $message->system_event,
            'edited_at' => $message->edited_at?->toIso8601String(),
            'deleted_at' => $message->deleted_at?->toIso8601String(),
            'deleted_for_everyone_at' => $message->deleted_for_everyone_at?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
            'date_key' => $message->created_at?->toDateString(),
            'time' => $this->messageTimestamp($message),
            'date_label' => $this->messageDateLabel($message),
            'sender' => [
                'id' => $message->sender?->getKey(),
                'name' => $message->sender?->name,
                'initials' => $message->sender?->initials(),
                'profile_image' => $message->sender?->profile_image,
                'profile_image_url' => $message->sender?->profile_image_url,
            ],
            'sender_display_name' => $message->sender ? $this->memberDisplayName($message->sender) : __('Someone'),
            'reply_to' => $message->replyTo ? [
                'id' => $message->replyTo->getKey(),
                'content' => $message->replyTo->content,
                'sender_display_name' => $message->replyTo->sender ? $this->memberDisplayName($message->replyTo->sender) : __('Someone'),
            ] : null,
            'attachments' => $message->attachments
                ->map(fn (GroupMessageAttachment $attachment): array => [
                    'id' => $attachment->getKey(),
                    'path' => $attachment->path,
                    'name' => $attachment->name,
                    'mime' => $attachment->mime,
                    'size' => $attachment->size,
                    'public_url' => $this->attachmentPublicUrl($attachment),
                ])
                ->values()
                ->all(),
            'reactions' => $message->reactions
                ->map(fn (GroupMessageReaction $reaction): array => [
                    'id' => $reaction->getKey(),
                    'emoji' => $reaction->emoji,
                    'user_id' => $reaction->user_id,
                    'user_name' => $reaction->user?->name,
                ])
                ->values()
                ->all(),
        ];
    }

    private function attachmentPublicUrl(GroupMessageAttachment $attachment): string
    {
        return route('messages.group-attachments.show', ['attachment' => $attachment], false);
    }

    public function render(): View
    {
        return view('pages::messages.⚡group-conversation');
    }
}
