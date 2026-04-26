<?php

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Conversation')] class extends Component
{
    use WithFileUploads;

    public int $otherUserId;

    public ?int $linkedOrderId = null;

    #[Validate('nullable|string|max:2000')]
    public string $newMessage = '';

    #[Validate('nullable|file|max:10240|mimetypes:image/jpeg,image/png,image/webp,image/gif,application/pdf,video/mp4,video/quicktime,audio/mpeg,audio/wav,audio/ogg|extensions:jpg,jpeg,png,webp,gif,pdf,mp4,mov,mp3,wav,ogg')]
    public $attachmentUpload = null;

    public function mount(string $conversationReference): void
    {
        $otherUser = User::query()->findOrFail((int) $conversationReference);

        abort_if($otherUser->getKey() === auth()->id(), 403);

        $this->otherUserId = $otherUser->getKey();
        $this->linkedOrderId = $this->resolveLinkedOrderId();

        $this->markMessagesAsRead();
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

        $trimmedMessage = trim($this->newMessage);

        if ($trimmedMessage === '' && $this->attachmentUpload === null) {
            $this->addError('newMessage', __('Write a message or attach a file before sending.'));

            return;
        }

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentMime = null;
        $attachmentSize = null;

        if ($this->attachmentUpload !== null) {
            $attachmentPath = $this->attachmentUpload->store('message-attachments', 'public');
            $attachmentName = $this->attachmentUpload->getClientOriginalName();
            $attachmentMime = $this->attachmentUpload->getMimeType();
            $attachmentSize = $this->attachmentUpload->getSize();
        }

        try {
            $message = DB::transaction(function () use ($attachmentMime, $attachmentName, $attachmentPath, $attachmentSize, $trimmedMessage): Message {
                return Message::query()->create([
                    'sender_id' => auth()->id(),
                    'receiver_id' => $this->otherUserId,
                    'order_id' => $this->linkedOrderId,
                    'content' => $trimmedMessage,
                    'attachment_path' => $attachmentPath,
                    'attachment_name' => $attachmentName,
                    'attachment_mime' => $attachmentMime,
                    'attachment_size' => $attachmentSize,
                ]);
            });
        } catch (\Throwable $exception) {
            if ($attachmentPath !== null) {
                Storage::disk('public')->delete($attachmentPath);
            }

            throw $exception;
        }

        event(new MessageSent($message));

        $this->newMessage = '';
        $this->attachmentUpload = null;
        $this->resetValidation(['newMessage', 'attachmentUpload']);

        unset($this->threadMessages);

        $this->dispatch('message-sent');
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
        return User::query()->findOrFail($this->otherUserId);
    }

    #[Computed]
    public function linkedOrder(): ?Order
    {
        if ($this->linkedOrderId === null) {
            return null;
        }

        return Order::query()
            ->with([
                'customer:id,name',
                'vendor.user:id,name',
            ])
            ->find($this->linkedOrderId);
    }

    #[Computed]
    public function threadMessages(): Collection
    {
        return Message::query()
            ->where(function ($query): void {
                $query
                    ->where(function ($innerQuery): void {
                        $innerQuery
                            ->where('sender_id', auth()->id())
                            ->where('receiver_id', $this->otherUserId);
                    })
                    ->orWhere(function ($innerQuery): void {
                        $innerQuery
                            ->where('sender_id', $this->otherUserId)
                            ->where('receiver_id', auth()->id());
                    });
            })
            ->with([
                'sender:id,name,profile_image',
                'order:id,order_status',
            ])
            ->orderBy('created_at')
            ->get();
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
            || $order->vendor?->user_id === auth()->id();

        abort_if(! $isParticipant, 403);

        return $order->getKey();
    }

    private function markMessagesAsRead(): void
    {
        DB::transaction(function (): void {
            Message::query()
                ->where('sender_id', $this->otherUserId)
                ->where('receiver_id', auth()->id())
                ->where('is_read', false)
                ->update(['is_read' => true]);
        });
    }
};
?>

<div wire:poll.5s="refreshThread" class="mx-auto flex min-h-[calc(100vh-6.5rem)] max-w-[1500px] flex-col gap-8 px-4 py-6 sm:px-6 lg:px-8">
    <section class="flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('messages.inbox') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold" style="color: var(--brand-700);">
                <i class="fa-solid fa-arrow-left text-xs"></i>
                {{ __('Back to inbox') }}
            </a>

            <div class="mt-4 flex items-center gap-4">
                <x-user-avatar :user="$this->otherUser" size="lg" />
                <div>
                    <p class="brand-kicker !mb-0">{{ __('Conversation') }}</p>
                    <h1 class="brand-serif mt-2 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ $this->otherUser->name }}</h1>
                </div>
            </div>
        </div>
    </section>

    @if ($this->linkedOrder !== null)
        <section class="brand-panel-muted p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="brand-kicker !mb-0">{{ __('Linked order') }}</p>
                    <h2 class="mt-3 text-lg font-semibold text-neutral-900 dark:text-zinc-100">
                        {{ __('Order #:number', ['number' => str_pad((string) $this->linkedOrder->id, 6, '0', STR_PAD_LEFT)]) }}
                    </h2>
                    <p class="mt-2 text-sm text-neutral-500 dark:text-zinc-400">
                        {{ __('Status: :status', ['status' => \Illuminate\Support\Str::headline($this->linkedOrder->order_status->value)]) }}
                    </p>
                </div>

                <p class="text-sm text-neutral-500 dark:text-zinc-400">
                    {{ __('Customer: :customer - Vendor: :vendor', [
                        'customer' => $this->linkedOrder->customer->name,
                        'vendor' => $this->linkedOrder->vendor->user->name,
                    ]) }}
                </p>
            </div>
        </section>
    @endif

    <section class="brand-panel flex min-h-0 flex-1 flex-col overflow-hidden">
        <div
            class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-5 sm:px-6"
            x-data
            x-init="$el.scrollTop = $el.scrollHeight"
            @message-sent.window="$nextTick(() => { $el.scrollTop = $el.scrollHeight })"
        >
            @forelse ($this->threadMessages as $message)
                @php($isOwnMessage = $message->sender_id === auth()->id())
                    <div
                        wire:key="conversation-message-{{ $message->id }}"
                        class="flex {{ $isOwnMessage ? 'justify-end' : 'justify-start' }}"
                    >
                        <div class="max-w-[80%] {{ $isOwnMessage ? 'items-end' : 'items-start' }} flex flex-col gap-2">
                            <div class="flex items-end gap-3 {{ $isOwnMessage ? 'flex-row-reverse' : '' }}">
                                @unless ($isOwnMessage)
                                    <x-user-avatar :user="$message->sender" size="sm" />
                                @endunless

                                <div
                                    class="rounded-[1.5rem] px-4 py-3 text-sm leading-7 {{ $isOwnMessage ? 'bg-[var(--brand-600)] text-white' : 'bg-stone-100 text-neutral-900 dark:bg-zinc-800 dark:text-zinc-100' }}"
                                >
                                    <?php if (filled($message->content)): ?>
                                        <p class="whitespace-pre-wrap break-words">{{ $message->content }}</p>
                                    <?php endif; ?>

                                    <?php if ($message->attachment_path): ?>
                                        <?php $attachmentUrl = asset('storage/'.$message->attachment_path); ?>

                                        <?php if (\Illuminate\Support\Str::startsWith($message->attachment_mime ?? '', 'image/')): ?>
                                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener noreferrer" class="mt-2 block overflow-hidden rounded-2xl border {{ $isOwnMessage ? 'border-white/25' : 'border-stone-300 dark:border-zinc-600' }}">
                                                <img
                                                    src="{{ $attachmentUrl }}"
                                                    alt="{{ $message->attachment_name ?? __('Attached image') }}"
                                                    class="max-h-72 w-full object-cover"
                                                    loading="lazy"
                                                >
                                            </a>
                                        <?php elseif (\Illuminate\Support\Str::startsWith($message->attachment_mime ?? '', 'video/')): ?>
                                            <div class="mt-2 overflow-hidden rounded-2xl border {{ $isOwnMessage ? 'border-white/25' : 'border-stone-300 dark:border-zinc-600' }}">
                                                <video
                                                    controls
                                                    preload="metadata"
                                                    class="max-h-72 w-full bg-black"
                                                >
                                                    <source src="{{ $attachmentUrl }}" type="{{ $message->attachment_mime }}">
                                                    {{ __('Your browser does not support the video tag.') }}
                                                </video>
                                            </div>
                                        <?php elseif (\Illuminate\Support\Str::startsWith($message->attachment_mime ?? '', 'audio/')): ?>
                                            <div class="mt-2 rounded-2xl border px-3 py-2 {{ $isOwnMessage ? 'border-white/25 bg-white/10' : 'border-stone-300 bg-white/70 dark:border-zinc-600 dark:bg-zinc-700' }}">
                                                <audio controls preload="metadata" class="w-full">
                                                    <source src="{{ $attachmentUrl }}" type="{{ $message->attachment_mime }}">
                                                    {{ __('Your browser does not support the audio element.') }}
                                                </audio>
                                            </div>
                                        <?php endif; ?>

                                        <a
                                            href="{{ $attachmentUrl }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="mt-2 inline-flex max-w-full items-center gap-2 rounded-xl border px-3 py-2 text-xs font-medium {{ $isOwnMessage ? 'border-white/30 bg-white/10 text-white hover:bg-white/15' : 'border-stone-300 bg-white/70 text-neutral-700 hover:bg-white dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 dark:hover:bg-zinc-600' }}"
                                        >
                                            <i class="fa-solid fa-paperclip"></i>
                                            <span class="truncate">{{ $message->attachment_name ?? __('Attachment') }}</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <p class="px-1 text-xs text-neutral-400 dark:text-zinc-500">{{ $message->timeAgo() }}</p>
                        </div>
                    </div>
            @empty
                <div class="flex h-full min-h-80 items-center justify-center rounded-[1.75rem] border border-dashed border-stone-200 px-6 py-12 text-center dark:border-white/10">
                    <div>
                        <span class="brand-kicker">{{ __('No messages yet') }}</span>
                        <p class="mt-4 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                            {{ __('Start the conversation here to coordinate availability, pickup timing, or order questions.') }}
                        </p>
                    </div>
                </div>
            @endforelse
        </div>

        <form wire:submit="send" class="border-t border-stone-200 p-5 dark:border-white/10 sm:p-6">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_9rem]">
                <flux:textarea
                    wire:model="newMessage"
                    :label="__('Reply')"
                    rows="3"
                    :placeholder="__('Write your message here')"
                    x-on:keydown.enter.prevent="$wire.send()"
                />

                <div class="flex h-full flex-col justify-end gap-2">
                    <label for="conversation-attachment" class="brand-button-secondary inline-flex w-full cursor-pointer items-center justify-center gap-2 text-sm">
                        <i class="fa-solid fa-paperclip text-xs"></i>
                        {{ __('Attach file') }}
                    </label>
                    <input id="conversation-attachment" type="file" wire:model="attachmentUpload" class="sr-only">

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="send,attachmentUpload"
                        class="brand-button-primary h-full min-h-[3.5rem] w-full self-end"
                    >
                        <span wire:loading.remove wire:target="send">{{ __('Send') }}</span>
                        <span wire:loading wire:target="send">{{ __('Sending...') }}</span>
                    </button>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-3">
                <p class="text-xs text-neutral-500 dark:text-zinc-400">{{ __('Press Enter to send.') }}</p>

                @if ($attachmentUpload)
                    <span class="inline-flex items-center gap-2 rounded-full bg-stone-100 px-3 py-1 text-xs text-stone-700 dark:bg-zinc-800 dark:text-zinc-200">
                        <i class="fa-solid fa-file"></i>
                        <span class="max-w-[12rem] truncate">{{ $attachmentUpload->getClientOriginalName() }}</span>
                    </span>
                @endif
            </div>

            @error('newMessage')
                <p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
            @error('attachmentUpload')
                <p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </form>
    </section>
</div>
