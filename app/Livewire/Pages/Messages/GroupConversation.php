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
use Illuminate\Contracts\View\View;
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

    public ?int $replyingToId = null;

    public ?string $replyingToContent = null;

    public ?string $replyingToSender = null;

    public function mount(int $groupId): void
    {
        abort_unless($this->isMember($groupId), 403);

        $this->groupId = $groupId;

        $this->markRead();
    }

    public function getListeners(): array
    {
        return [
            'echo-private:group.'.$this->groupId.',.GroupMessageSent' => 'handleIncomingMessage',
        ];
    }

    public function handleIncomingMessage(): void
    {
        $this->refreshThread(shouldScroll: true);
    }

    public function send(): void
    {
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

        try {
            event(new GroupMessageSent($message));
        } catch (\Throwable $broadcastException) {
            Log::warning('GroupMessageSent broadcast failed (Pusher may be unavailable): '.$broadcastException->getMessage());
        }

        $this->newMessage = '';
        $this->attachmentUploads = [];
        $this->clearReply();
        $this->resetValidation(['newMessage', 'attachmentUploads', 'attachmentUploads.*']);

        unset($this->threadMessages);
        $this->markRead();
        $this->dispatch('group-message-sent');
    }

    public function refreshThread(bool $shouldScroll = false): void
    {
        $this->markRead();
        unset($this->threadMessages);
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

    public function leaveGroup(): void
    {
        ConversationGroupMember::query()
            ->where('group_id', $this->groupId)
            ->where('user_id', auth()->id())
            ->delete();

        $this->redirect(route('messages.inbox'), navigate: true);
    }

    public function markRead(): void
    {
        ConversationGroupMember::query()
            ->where('group_id', $this->groupId)
            ->where('user_id', auth()->id())
            ->update(['last_read_at' => now()]);

        $this->dispatch('message-marked-read');
    }

    public function setReplyTo(int $messageId): void
    {
        $message = GroupMessage::query()
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

        unset($this->threadMessages);
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

        return VideoCall::query()
            ->where('group_id', $this->groupId)
            ->where('is_group_call', true)
            ->whereIn('status', [
                VideoCallStatus::Active->value,
                VideoCallStatus::Pending->value,
            ])
            ->latest('created_at')
            ->first();
    }

    #[Computed]
    public function threadMessages(): Collection
    {
        return GroupMessage::query()
            ->where('group_id', $this->groupId)
            ->with([
                'sender:id,name,profile_image',
                'attachments',
                'replyTo.sender:id,name,profile_image',
                'reactions.user:id,name',
            ])
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->sortBy('created_at')
            ->values();
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

    private function validateAttachmentTotalSize(): void
    {
        $totalSize = collect($this->attachmentUploads)->sum(fn ($upload): int => (int) $upload->getSize());

        if ($totalSize > 25 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'attachmentUploads' => __('Attachments cannot exceed 25 MB total.'),
            ]);
        }
    }

    public function render(): View
    {
        return view('pages::messages.⚡group-conversation');
    }
}
