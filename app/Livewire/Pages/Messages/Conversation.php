<?php

namespace App\Livewire\Pages\Messages;

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Enums\VideoCallStatus;
use App\Events\MessageSent;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessagePin;
use App\Models\Order;
use App\Models\User;
use App\Models\VideoCall;
use App\Services\AuditLogger;
use App\Services\MessageMutationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Conversation')]
class Conversation extends Component
{
    use WithFileUploads;

    public int $otherUserId;

    public ?int $linkedOrderId = null;

    #[Validate('nullable|string|max:2000')]
    public string $newMessage = '';

    /**
     * @var array<int, mixed>
     */
    #[
        Validate([
            'attachmentUploads' => ['array', 'max:5'],
            'attachmentUploads.*' => ['file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,application/pdf,video/mp4,video/quicktime,audio/mpeg,audio/wav,audio/ogg', 'extensions:jpg,jpeg,png,webp,gif,pdf,mp4,mov,mp3,wav,ogg'],
        ]),
    ]
    public array $attachmentUploads = [];

    public ?int $incomingCallId = null;

    public bool $callInProgress = false;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $messages = [];

    public function mount(string $conversationReference): void
    {
        $otherUser = User::query()->findOrFail((int) $conversationReference);

        abort_if($otherUser->getKey() === auth()->id(), 403);

        $this->otherUserId = $otherUser->getKey();
        $this->linkedOrderId = $this->resolveLinkedOrderId();
        $this->incomingCallId = $this->resolveIncomingCallId();

        $this->markMessagesAsRead();
        $this->loadMessages();

        if ($this->incomingCallId !== null) {
            $this->dispatch('conversation-auto-answer', callId: $this->incomingCallId);
        }
    }

    public function getListeners(): array
    {
        return [
            'echo-private:messaging.'.Message::conversationKey($this->otherUserId).',.MessageSent' => 'handleIncomingMessage',
        ];
    }

    public function handleIncomingMessage(): void
    {
        $this->loadMessages();
        $this->markMessagesAsRead();
        $this->dispatch('message-sent');
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
            $message = DB::transaction(function () use (&$storedPaths, $trimmedMessage): Message {
                $message = Message::query()->create([
                    'sender_id' => auth()->id(),
                    'receiver_id' => $this->otherUserId,
                    'order_id' => $this->linkedOrderId,
                    'content' => $trimmedMessage,
                ]);

                foreach ($this->attachmentUploads as $upload) {
                    $path = $upload->store('message-attachments', 'public');
                    $storedPaths[] = $path;

                    MessageAttachment::query()->create([
                        'message_id' => $message->getKey(),
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
            event(new MessageSent($message));
        } catch (\Throwable $broadcastException) {
            Log::warning('MessageSent broadcast failed (Reverb may be down): '.$broadcastException->getMessage());
        }

        AuditLogger::log(AuditEvent::MessageSent, auth()->user()->name.' sent a message to user #'.$this->otherUserId.'.', $message, null, ['length' => strlen($trimmedMessage)]);

        $this->newMessage = '';
        $this->attachmentUploads = [];
        $this->resetValidation(['newMessage', 'attachmentUploads', 'attachmentUploads.*']);

        $this->dispatch('message-sent');
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

    public function editMessage(int $messageId, string $content): void
    {
        $message = $this->directMessageQuery()
            ->whereKey($messageId)
            ->firstOrFail();

        app(MessageMutationService::class)->editDirect(auth()->user(), $message, $content);

        $this->loadMessages();
        $this->dispatch('message-sent');
    }

    public function pinMessage(int $messageId): void
    {
        $message = $this->directMessageQuery()
            ->whereKey($messageId)
            ->firstOrFail();

        app(MessageMutationService::class)->pinDirect(auth()->user(), $message);

        $this->loadMessages();
        $this->dispatch('message-sent');
    }

    public function deleteMessage(int $messageId, bool $forEveryone = true): void
    {
        $message = $this->directMessageQuery()
            ->whereKey($messageId)
            ->firstOrFail();

        app(MessageMutationService::class)->deleteDirect(auth()->user(), $message, $forEveryone);

        $this->loadMessages();
        $this->dispatch('message-sent');
    }

    public function refreshThread(bool $shouldScroll = false): void
    {
        if ($this->callInProgress) {
            $this->skipRender();

            return;
        }

        $this->markMessagesAsRead();
        $this->loadMessages();

        $previousIncomingCallId = $this->incomingCallId;
        $this->incomingCallId = $this->resolvePendingIncomingCallId();

        if ($this->incomingCallId !== null && $this->incomingCallId !== $previousIncomingCallId) {
            $this->dispatch('conversation-auto-answer', callId: $this->incomingCallId);
        }

        unset($this->linkedOrder);

        if ($shouldScroll) {
            $this->dispatch('message-sent');
        }
    }

    #[Computed]
    public function otherUser(): User
    {
        return User::query()->with('vendorProfile:id,user_id,status')->findOrFail($this->otherUserId);
    }

    #[Computed]
    public function otherUserProfileRoute(): string
    {
        $viewer = auth()->user();
        $otherUser = $this->otherUser;

        if ($viewer->effectiveMarketplaceRole() === UserRole::Admin || $otherUser->effectiveMarketplaceRole() === UserRole::Admin) {
            return route('admin.users.show', $otherUser);
        }

        if ($otherUser->effectiveMarketplaceRole() === UserRole::Vendor && $otherUser->vendorProfile !== null) {
            return route('shop.vendors.show', $otherUser->vendorProfile);
        }

        return route('shop.customers.show', $otherUser);
    }

    #[Computed]
    public function linkedOrder(): ?Order
    {
        if ($this->linkedOrderId === null) {
            return null;
        }

        return Order::query()
            ->with(['customer:id,name', 'vendor.user:id,name'])
            ->find($this->linkedOrderId);
    }

    public function conversationSubtitle(): string
    {
        return __('Active now');
    }

    public function latestOwnMessageId(): ?int
    {
        $latestOwnMessage = collect($this->messages)
            ->where('sender_id', auth()->id())
            ->last();

        return is_array($latestOwnMessage) ? (int) $latestOwnMessage['id'] : null;
    }

    #[Computed]
    public function pinnedMessages(): Collection
    {
        $firstUserId = min((int) auth()->id(), $this->otherUserId);
        $secondUserId = max((int) auth()->id(), $this->otherUserId);

        return MessagePin::query()
            ->where('direct_user_one_id', $firstUserId)
            ->where('direct_user_two_id', $secondUserId)
            ->with('pinnable')
            ->latest('pinned_at')
            ->limit(3)
            ->get();
    }

    public function messageDateLabel(Message $message): string
    {
        if ($message->created_at?->isToday()) {
            return __('Today');
        }

        if ($message->created_at?->isYesterday()) {
            return __('Yesterday');
        }

        return $message->created_at?->format('M j, Y') ?? __('Today');
    }

    public function messageTimestamp(Message $message): string
    {
        return $message->created_at?->format('g:i A') ?? __('Now');
    }

    /**
     * @return Collection<int, MessageAttachment>
     */
    public function attachmentsForDisplay(Message $message): Collection
    {
        return $message->attachmentsForDisplay()
            ->map(function (MessageAttachment $attachment): MessageAttachment {
                $attachment->setAttribute('public_url', $this->attachmentPublicUrl($attachment));

                return $attachment;
            });
    }

    private function resolveLinkedOrderId(): ?int
    {
        $orderReference = (int) request()->integer('order');

        if ($orderReference <= 0) {
            return null;
        }

        $order = Order::query()
            ->with('vendor:id,user_id')
            ->findOrFail($orderReference);

        $isParticipant = $order->customer_id === auth()->id()
            || $order->vendor?->user_id === auth()->id()
            || $order->rider_id === auth()->id();

        abort_if(! $isParticipant, 403);

        return $order->getKey();
    }

    private function resolveIncomingCallId(): ?int
    {
        if (! request()->boolean('incoming_call')) {
            return null;
        }

        return $this->resolvePendingIncomingCallId((int) request()->integer('call_id'));
    }

    private function resolvePendingIncomingCallId(?int $requestedCallId = null): ?int
    {
        $pendingCallQuery = VideoCall::query()
            ->where('caller_id', $this->otherUserId)
            ->where('receiver_id', auth()->id())
            ->where('is_group_call', false)
            ->where('status', VideoCallStatus::Pending);

        if ($requestedCallId !== null && $requestedCallId > 0) {
            $call = (clone $pendingCallQuery)
                ->whereKey($requestedCallId)
                ->first();

            if ($call !== null) {
                return $call->getKey();
            }
        }

        $callId = $pendingCallQuery
            ->latest('created_at')
            ->value('id');

        return $callId === null ? null : (int) $callId;
    }

    private function markMessagesAsRead(): void
    {
        $updatedCount = DB::transaction(function (): int {
            return Message::query()
                ->where('sender_id', $this->otherUserId)
                ->where('receiver_id', auth()->id())
                ->where('is_read', false)
                ->update(['is_read' => true]);
        });

        if ($updatedCount > 0) {
            $this->dispatch('message-marked-read');
        }
    }

    private function loadMessages(): void
    {
        $this->messages = $this->directMessageQuery()
            ->withTrashed()
            ->with(['sender:id,name,profile_image', 'order:id,order_status', 'attachments'])
            ->latest('created_at')
            ->latest('id')
            ->limit(100)
            ->get()
            ->sortBy('created_at')
            ->map(fn (Message $message): array => $this->messageToArray($message))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function messageToArray(Message $message): array
    {
        $message->loadMissing(['sender:id,name,profile_image', 'order:id,order_status', 'attachments']);

        return [
            'id' => $message->getKey(),
            'sender_id' => $message->sender_id,
            'receiver_id' => $message->receiver_id,
            'content' => $message->content,
            'is_read' => $message->is_read,
            'edited_at' => $message->edited_at?->toIso8601String(),
            'deleted_at' => $message->deleted_at?->toIso8601String(),
            'deleted_for_everyone_at' => $message->deleted_for_everyone_at?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
            'date_key' => $message->created_at?->toDateString(),
            'time' => $this->messageTimestamp($message),
            'date_label' => $this->messageDateLabel($message),
            'sender_name' => $message->sender?->name,
            'sender_image' => $message->sender?->profile_image,
            'sender' => [
                'id' => $message->sender?->getKey(),
                'name' => $message->sender?->name,
                'initials' => $message->sender?->initials(),
                'profile_image' => $message->sender?->profile_image,
                'profile_image_url' => $message->sender?->profile_image_url,
            ],
            'order_id' => $message->order_id,
            'order_status' => $message->order?->order_status?->value,
            'attachments' => $message->attachmentsForDisplay()
                ->map(fn (MessageAttachment $attachment): array => [
                    'id' => $attachment->getKey(),
                    'path' => $attachment->path,
                    'name' => $attachment->name,
                    'mime' => $attachment->mime,
                    'size' => $attachment->size,
                    'public_url' => $this->attachmentPublicUrl($attachment),
                ])
                ->values()
                ->all(),
        ];
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

    private function attachmentPublicUrl(MessageAttachment $attachment): string
    {
        return route('messages.attachments.show', ['attachment' => $attachment], false);
    }

    private function directMessageQuery(): Builder
    {
        return Message::query()
            ->where(function ($query): void {
                $query
                    ->where(function ($innerQuery): void {
                        $innerQuery->where('sender_id', auth()->id())->where('receiver_id', $this->otherUserId);
                    })
                    ->orWhere(function ($innerQuery): void {
                        $innerQuery->where('sender_id', $this->otherUserId)->where('receiver_id', auth()->id());
                    });
            });
    }

    public function render(): View
    {
        return view('pages::messages.⚡conversation');
    }
}
