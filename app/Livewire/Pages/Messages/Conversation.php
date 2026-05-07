<?php

namespace App\Livewire\Pages\Messages;

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Enums\VideoCallStatus;
use App\Events\MessageSent;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Order;
use App\Models\User;
use App\Models\VideoCall;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
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

    public function mount(string $conversationReference): void
    {
        $otherUser = User::query()->findOrFail((int) $conversationReference);

        abort_if($otherUser->getKey() === auth()->id(), 403);

        $this->otherUserId = $otherUser->getKey();
        $this->linkedOrderId = $this->resolveLinkedOrderId();
        $this->incomingCallId = $this->resolveIncomingCallId();

        $this->markMessagesAsRead();

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

        try {
            event(new MessageSent($message));
        } catch (\Throwable $broadcastException) {
            Log::warning('MessageSent broadcast failed (Reverb may be down): '.$broadcastException->getMessage());
        }

        AuditLogger::log(AuditEvent::MessageSent, auth()->user()->name.' sent a message to user #'.$this->otherUserId.'.', $message, null, ['length' => strlen($trimmedMessage)]);

        $this->newMessage = '';
        $this->attachmentUploads = [];
        $this->resetValidation(['newMessage', 'attachmentUploads', 'attachmentUploads.*']);

        unset($this->threadMessages);

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

    public function refreshThread(bool $shouldScroll = false): void
    {
        $this->markMessagesAsRead();

        unset($this->threadMessages);
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

    #[Computed]
    public function threadMessages(): Collection
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
            })
            ->with(['sender:id,name,profile_image', 'order:id,order_status', 'attachments'])
            ->orderBy('created_at')
            ->get();
    }

    public function conversationSubtitle(): string
    {
        return __('Active now');
    }

    public function latestOwnMessageId(): ?int
    {
        return $this->threadMessages
            ->where('sender_id', auth()->id())
            ->last()
            ?->getKey();
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

    private function resolveLinkedOrderId(): ?int
    {
        $orderReference = (int) request()->integer('order');

        if ($orderReference <= 0) {
            return null;
        }

        $order = Order::query()->with('vendor:id,user_id')->findOrFail($orderReference);

        $isParticipant = $order->customer_id === auth()->id() || $order->vendor?->user_id === auth()->id();

        abort_if(! $isParticipant, 403);

        return $order->getKey();
    }

    private function resolveIncomingCallId(): ?int
    {
        if (! request()->boolean('incoming_call')) {
            return null;
        }

        $requestedCallId = (int) request()->integer('call_id');

        if ($requestedCallId > 0) {
            $call = VideoCall::query()
                ->whereKey($requestedCallId)
                ->where('caller_id', $this->otherUserId)
                ->where('receiver_id', auth()->id())
                ->where('is_group_call', false)
                ->where('status', VideoCallStatus::Pending)
                ->first();

            if ($call !== null) {
                return $call->getKey();
            }
        }

        return VideoCall::query()
            ->where('caller_id', $this->otherUserId)
            ->where('receiver_id', auth()->id())
            ->where('is_group_call', false)
            ->where('status', VideoCallStatus::Pending)
            ->latest('created_at')
            ->value('id');
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
        return view('pages::messages.⚡conversation');
    }
}
