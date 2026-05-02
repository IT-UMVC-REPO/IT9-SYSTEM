<?php

use App\Enums\UserRole;
use App\Events\MessageSent;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Order;
use App\Models\User;
use App\Models\VideoCall;
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

new #[Title('Conversation')] class extends Component {
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
            'echo-private:messaging.' . Message::conversationKey($this->otherUserId) . ',.MessageSent' => 'handleIncomingMessage',
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
            Log::warning('MessageSent broadcast failed (Reverb may be down): ' . $broadcastException->getMessage());
        }

        $this->newMessage = '';
        $this->attachmentUploads = [];
        $this->resetValidation(['newMessage', 'attachmentUploads', 'attachmentUploads.*']);

        unset($this->threadMessages);

        $this->dispatch('message-sent');
    }

    public function removeAttachmentUpload(int $index): void
    {
        if (!array_key_exists($index, $this->attachmentUploads)) {
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

    private function resolveLinkedOrderId(): ?int
    {
        $orderReference = (int) request()->integer('order');

        if ($orderReference <= 0) {
            return null;
        }

        $order = Order::query()->with('vendor:id,user_id')->findOrFail($orderReference);

        $isParticipant = $order->customer_id === auth()->id() || $order->vendor?->user_id === auth()->id();

        abort_if(!$isParticipant, 403);

        return $order->getKey();
    }

    private function resolveIncomingCallId(): ?int
    {
        if (!request()->boolean('incoming_call')) {
            return null;
        }

        $requestedCallId = (int) request()->integer('call_id');

        if ($requestedCallId > 0) {
            $call = VideoCall::query()
                ->whereKey($requestedCallId)
                ->where('caller_id', $this->otherUserId)
                ->where('receiver_id', auth()->id())
                ->where('is_group_call', false)
                ->where('status', \App\Enums\VideoCallStatus::Pending)
                ->first();

            if ($call !== null) {
                return $call->getKey();
            }
        }

        return VideoCall::query()
            ->where('caller_id', $this->otherUserId)
            ->where('receiver_id', auth()->id())
            ->where('is_group_call', false)
            ->where('status', \App\Enums\VideoCallStatus::Pending)
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
        $totalSize = collect($this->attachmentUploads)->sum(fn($upload): int => (int) $upload->getSize());

        if ($totalSize > 25 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'attachmentUploads' => __('Attachments cannot exceed 25 MB total.'),
            ]);
        }
    }
};
?>

@php
    $pusherBroadcastConfig = config('broadcasting.connections.pusher', []);
    $realtimeEnabled =
        filled($pusherBroadcastConfig['key'] ?? null) && filled($pusherBroadcastConfig['app_id'] ?? null);
@endphp

<div wire:poll.5s="refreshThread" class="flex h-[calc(100vh-52px)] flex-col overflow-hidden px-4 py-4 sm:px-6 lg:px-8">
    <div wire:key="conversation-video-call-{{ $otherUserId }}" wire:ignore.self data-conversation-video-call
        x-data="window.conversationVideoCall({
            authUserId: @js((int) auth()->id()),
            conversationKey: @js(Message::conversationKey($otherUserId)),
            otherUserId: @js($otherUserId),
            otherUserName: @js($this->otherUser->name),
            realtimeEnabled: @js($realtimeEnabled),
            routes: {
                iceServers: @js(route('calls.ice-servers')),
                initiate: @js(route('calls.initiate')),
                signal: @js(route('calls.signal', ['call' => '__CALL_ID__'])),
                answer: @js(route('calls.answer', ['call' => '__CALL_ID__'])),
                decline: @js(route('calls.decline', ['call' => '__CALL_ID__'])),
                end: @js(route('calls.end', ['call' => '__CALL_ID__'])),
            },
        })" x-init="$el.__conversationVideoCall = $data;
        init();
        if (@js($incomingCallId) !== null) {
            callId = @js($incomingCallId);
            callStatus = 'incoming';
            window.setTimeout(() => acceptCall());
        }" x-on:beforeunload.window="disposeOnLeave()"
        x-on:livewire:navigating.window="disposeOnLeave()"
        x-on:conversation-auto-answer.window="callId = $event.detail.callId; callStatus = 'incoming'; acceptCall()"
        x-on:video-call-start.window="startCall()" class="contents">
        <div wire:ignore x-cloak x-show="isOverlayVisible()" x-transition.opacity
            class="fixed inset-0 z-[70] bg-neutral-950/95 backdrop-blur-sm">
            <div class="mx-auto grid h-screen max-w-[1600px] grid-rows-[auto_minmax(0,1fr)] p-4 sm:p-6">
                <section
                    class="mb-4 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-white/10 bg-zinc-900 p-4 text-white shadow-2xl shadow-black/35">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-zinc-400">
                            {{ __('VIDEO CALL') }}</p>
                        <h2 class="mt-2 text-xl font-semibold text-white sm:text-2xl" x-text="otherUserName"></h2>
                        <p class="mt-1 text-sm text-zinc-400" x-text="statusMessage || 'Waiting to connect...'"></p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <template x-if="callStatus === 'incoming'">
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="button" x-on:click="acceptCall()"
                                    class="brand-button-primary inline-flex items-center gap-2">
                                    <i class="fa-solid fa-phone text-sm"></i>
                                    {{ __('Accept') }}
                                </button>

                                <button type="button" x-on:click="declineCall()"
                                    class="inline-flex items-center gap-2 rounded-[1.25rem] border border-rose-400/35 bg-rose-500/15 px-5 py-3 text-sm font-semibold text-rose-100 transition hover:border-rose-300/50 hover:bg-rose-500/20">
                                    <i class="fa-solid fa-phone-slash text-sm"></i>
                                    {{ __('Decline') }}
                                </button>
                            </div>
                        </template>

                        <template x-if="callStatus === 'active' || callStatus === 'connecting'">
                            <flux:button type="button" variant="outline" x-on:click="endCall()"
                                class="border-zinc-600 text-zinc-100 hover:bg-zinc-800">
                                <span class="flex items-center gap-2">
                                    <i class="fa-solid fa-phone-slash text-sm"></i>
                                    {{ __('End call') }}
                                </span>
                            </flux:button>
                        </template>

                        <template x-if="callStatus === 'calling'">
                            <flux:button type="button" variant="outline" x-on:click="endCall('Call cancelled.')"
                                class="border-zinc-600 text-zinc-100 hover:bg-zinc-800">
                                <span class="flex items-center gap-2">
                                    <i class="fa-solid fa-xmark text-sm"></i>
                                    {{ __('Cancel call') }}
                                </span>
                            </flux:button>
                        </template>
                    </div>
                </section>

                <div class="grid min-h-0 gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <section
                        class="relative min-h-0 overflow-hidden rounded-2xl bg-zinc-900 shadow-2xl shadow-black/40">
                        <video id="conversation-call-remote-video" autoplay playsinline
                            class="h-full min-h-[22rem] w-full rounded-2xl bg-zinc-900 object-cover"></video>

                        <div
                            class="absolute left-4 top-4 inline-flex items-center gap-2 rounded-full bg-black/55 px-3 py-1.5 text-xs font-semibold text-white/85 backdrop-blur">
                            <span class="text-emerald-400"
                                :class="callStatus === 'calling' || callStatus === 'incoming' || callStatus === 'connecting' ?
                                    'animate-pulse' : ''">●</span>
                            <span
                                x-text="callStatus === 'calling' ? 'Calling...' : (callStatus === 'incoming' ? 'Incoming call' : (callStatus === 'connecting' ? 'Connecting media' : 'Live call'))"></span>
                        </div>
                    </section>

                    <div class="grid min-h-0 gap-4 content-start lg:w-[320px] lg:grid-rows-[auto_minmax(0,1fr)]">
                        <section class="rounded-2xl border border-white/10 bg-zinc-900 p-4">
                            <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-zinc-400">
                                {{ __('LOCAL PREVIEW') }}</p>

                            <div class="relative">
                                <video id="conversation-call-local-video" autoplay muted playsinline
                                    class="aspect-video w-full rounded-xl bg-black object-cover"></video>

                                <button type="button" x-cloak
                                    x-show="hasMultipleCameras && (callStatus === 'active' || callStatus === 'connecting')"
                                    x-on:click="switchCamera()"
                                    class="absolute bottom-3 right-3 flex h-10 w-10 items-center justify-center rounded-full bg-black/65 text-white shadow-lg backdrop-blur transition hover:bg-black/80"
                                    title="{{ __('Switch camera') }}">
                                    <i class="fa-solid fa-camera-rotate text-sm"></i>
                                </button>
                            </div>
                        </section>

                        <section class="min-h-0 rounded-2xl border border-white/10 bg-zinc-900 p-5 text-zinc-300">
                            <p class="text-xs font-semibold uppercase tracking-widest text-zinc-400">
                                {{ __('CALL STATUS') }}</p>
                            <p class="mt-3 text-base leading-7"
                                x-text="statusMessage || 'Camera and audio will connect as soon as both participants join.'">
                            </p>

                            <template x-if="callStatus === 'calling'">
                                <p class="mt-4 text-sm leading-7 text-zinc-400">
                                    {{ __('Keep this window open while the other person answers.') }}</p>
                            </template>

                            <template x-if="callStatus === 'incoming'">
                                <p class="mt-4 text-sm leading-7 text-zinc-400">
                                    {{ __('Accept to share your camera and microphone for this conversation.') }}</p>
                            </template>

                            <template x-if="callStatus === 'connecting'">
                                <p class="mt-4 text-sm leading-7 text-zinc-400">
                                    {{ __('The call was accepted. Keep this window open while the media connection finishes.') }}
                                </p>
                            </template>

                            <template x-if="showTurnWarning && callStatus === 'connecting' && !usesTurnServers()">
                                <p
                                    class="mt-4 rounded-2xl border border-amber-300/30 bg-amber-400/10 px-4 py-3 text-sm leading-7 text-amber-100">
                                    {{ __('Your network may require TURN server credentials for mobile or cross-network calls.') }}
                                    <a href="{{ route('support.video-calls') }}" target="_blank"
                                        rel="noopener noreferrer" class="font-semibold underline">
                                        {{ __('Read the support note') }}
                                    </a>
                                </p>
                            </template>
                        </section>
                    </div>
                </div>
            </div>
        </div>

        <div class="mx-auto grid h-full w-full max-w-[1600px] min-h-0 gap-6 lg:grid-cols-[20rem_minmax(0,1fr)]">
            <aside class="hidden lg:flex min-h-0 flex-col overflow-hidden">
                <div class="shrink-0 pb-4">
                    <span class="brand-kicker">{{ __('Conversations') }}</span>
                    <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">
                        {{ __('All threads') }}</h2>
                </div>

                <div class="min-h-0 flex-1 overflow-hidden">
                    <livewire:messages.conversation-sidebar :active-conversation-user-id="$otherUserId" :key="'conversation-sidebar-' . $otherUserId" />
                </div>
            </aside>

            <div class="flex min-h-0 flex-1 flex-col overflow-hidden gap-4">
                <section class="shrink-0 flex items-start justify-between gap-4">
                    <div>
                        <a href="{{ route('messages.inbox') }}" wire:navigate
                            class="inline-flex items-center gap-2 text-sm font-semibold text-[var(--brand-700)] dark:text-[var(--brand-400)] lg:hidden">
                            <i class="fa-solid fa-arrow-left text-xs"></i>
                            {{ __('All conversations') }}
                        </a>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <x-user-avatar :user="$this->otherUser" size="lg" />
                                <div class="min-w-0">
                                    <h1 class="brand-serif mt-2 text-3xl font-bold text-neutral-900 dark:text-zinc-100">
                                        <livewire:messaging.nickname-editor :target-user-id="$otherUserId" :profile-route="$this->otherUserProfileRoute"
                                            :key="'nickname-editor-' . $otherUserId" />
                                    </h1>
                                </div>
                            </div>
                            <div wire:ignore>
                                <button type="button" x-on:click="$dispatch('video-call-start')"
                                    x-bind:disabled="callStatus !== 'idle' || !supportsVideoCalling()"
                                    x-bind:title="supportsVideoCalling() ? 'Start video call' : videoCallDisabledReason()"
                                    x-bind:class="supportsVideoCalling() ? '' : 'cursor-not-allowed opacity-50'"
                                    class="brand-button-secondary inline-flex items-center gap-2 text-sm disabled:cursor-not-allowed disabled:opacity-60">
                                    <i class="fa-solid fa-video text-sm"></i>
                                    {{ __('Video call') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                @if ($this->linkedOrder !== null)
                    <section class="brand-panel-muted shrink-0 p-5">
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
                    <div class="scrollbar-none min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-5 sm:px-6" x-data
                        x-init="$el.scrollTop = $el.scrollHeight"
                        @message-sent.window="$nextTick(() => { $el.scrollTop = $el.scrollHeight })">
                        @forelse ($this->threadMessages as $message)
                            @php($isOwnMessage = $message->sender_id === auth()->id())
                            <div wire:key="conversation-message-{{ $message->id }}"
                                class="group mb-4 flex {{ $isOwnMessage ? 'justify-end' : 'justify-start' }}">
                                <div
                                    class="flex max-w-[70%] flex-col gap-1 {{ $isOwnMessage ? 'items-end' : 'items-start' }}">
                                    <div
                                        class="flex max-w-full items-end gap-3 {{ $isOwnMessage ? 'flex-row-reverse' : '' }}">
                                        @unless ($isOwnMessage)
                                            <x-user-avatar :user="$message->sender" size="sm" class="shrink-0" />
                                        @endunless

                                        <div
                                            class="min-w-0 w-fit max-w-full overflow-hidden rounded-2xl px-4 py-2 text-left text-sm leading-relaxed break-words {{ $isOwnMessage ? 'bg-emerald-600 text-white' : 'bg-zinc-800 text-zinc-100' }}">
                                            <?php if (filled($message->content)): ?>
                                            <p class="break-words [overflow-wrap:anywhere]">
                                                {{ $message->content }}</p>
                                            <?php endif; ?>

                                            @php($attachments = $message->attachmentsForDisplay())

                                            @if ($attachments->isNotEmpty())
                                                <div
                                                    class="{{ $attachments->count() > 1 ? 'mt-2 grid grid-cols-2 gap-2' : 'mt-2 grid gap-2' }}">
                                                    @foreach ($attachments as $attachment)
                                                        @php($attachmentUrl = asset('storage/' . $attachment->path))
                                                        @php($attachmentMime = $attachment->mime ?? 'application/octet-stream')

                                                        @if (Str::startsWith($attachmentMime, 'image/'))
                                                            <a href="{{ $attachmentUrl }}" target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="block overflow-hidden rounded-2xl border {{ $isOwnMessage ? 'border-white/25' : 'border-stone-300 dark:border-zinc-600' }}">
                                                                <img src="{{ $attachmentUrl }}"
                                                                    alt="{{ __('Attached image') }}"
                                                                    class="max-h-52 w-full object-cover"
                                                                    loading="lazy">
                                                            </a>
                                                        @elseif (Str::startsWith($attachmentMime, 'video/'))
                                                            <div
                                                                class="overflow-hidden rounded-2xl border {{ $isOwnMessage ? 'border-white/25' : 'border-stone-300 dark:border-zinc-600' }}">
                                                                <video controls preload="metadata"
                                                                    class="max-h-48 max-w-full bg-black">
                                                                    <source src="{{ $attachmentUrl }}"
                                                                        type="{{ $attachmentMime }}">
                                                                    {{ __('Your browser does not support the video tag.') }}
                                                                </video>
                                                                <a href="{{ $attachmentUrl }}" target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    class="flex items-center gap-2 px-3 py-2 text-xs font-medium">
                                                                    <i class="fa-solid fa-video"></i>
                                                                    {{ __('Video attachment') }}
                                                                </a>
                                                            </div>
                                                        @elseif (Str::startsWith($attachmentMime, 'audio/'))
                                                            <div
                                                                class="rounded-2xl border px-3 py-2 {{ $isOwnMessage ? 'border-white/25 bg-white/10' : 'border-stone-300 bg-white/70 dark:border-zinc-600 dark:bg-zinc-700' }}">
                                                                <audio controls preload="metadata" class="w-full">
                                                                    <source src="{{ $attachmentUrl }}"
                                                                        type="{{ $attachmentMime }}">
                                                                    {{ __('Your browser does not support the audio element.') }}
                                                                </audio>
                                                                <a href="{{ $attachmentUrl }}" target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    class="mt-2 inline-flex items-center gap-2 text-xs font-medium">
                                                                    <i class="fa-solid fa-volume-high"></i>
                                                                    {{ __('Audio attachment') }}
                                                                </a>
                                                            </div>
                                                        @else
                                                            <a href="{{ $attachmentUrl }}" target="_blank"
                                                                rel="noopener noreferrer"
                                                                title="{{ Str::contains($attachmentMime, 'pdf') ? __('Document') : __('Document') }}"
                                                                class="inline-flex max-w-full items-center justify-center gap-2 rounded-xl border px-3 py-2 text-xs font-medium {{ $isOwnMessage ? 'border-white/30 bg-white/10 text-white hover:bg-white/15' : 'border-stone-300 bg-white/70 text-neutral-700 hover:bg-white dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 dark:hover:bg-zinc-600' }}">
                                                                <i
                                                                    class="fa-solid {{ Str::contains($attachmentMime, 'pdf') ? 'fa-file-pdf' : (Str::contains($attachmentMime, 'word') ? 'fa-file-word' : 'fa-file') }}"></i>
                                                            </a>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <p
                                        class="px-1 text-xs text-neutral-400 opacity-0 transition-opacity group-hover:opacity-100 dark:text-zinc-500">
                                        {{ $message->timeAgo() }}</p>
                                </div>
                            </div>
                        @empty
                            <div
                                class="flex h-full min-h-80 items-center justify-center rounded-[1.75rem] border border-dashed border-stone-200 px-6 py-12 text-center dark:border-white/10">
                                <div>
                                    <span class="brand-kicker">{{ __('No messages yet') }}</span>
                                    <p class="mt-4 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                                        {{ __('Start the conversation here to coordinate availability, pickup timing, or order questions.') }}
                                    </p>
                                </div>
                            </div>
                        @endforelse
                    </div>

                    <form x-data="{
                        handlePaste(event) {
                            const imageFiles = Array.from(event.clipboardData?.files ?? []).filter((file) => file.type.startsWith('image/'));
                    
                            if (imageFiles.length === 0) {
                                return;
                            }
                    
                            event.preventDefault();
                            const transfer = new DataTransfer();
                            Array.from(this.$refs.attachments.files ?? []).forEach((file) => transfer.items.add(file));
                            imageFiles.forEach((file) => transfer.items.add(file));
                            this.$refs.attachments.files = transfer.files;
                            this.$refs.attachments.dispatchEvent(new Event('change', { bubbles: true }));
                        },
                    }"
                        x-on:submit.prevent="if (($wire.newMessage || '').trim() || ($wire.attachmentUploads || []).length) $wire.send()"
                        class="sticky bottom-0 shrink-0 border-t border-stone-200 bg-white p-4 pb-[max(1rem,env(safe-area-inset-bottom))] dark:border-white/10 dark:bg-zinc-900 lg:relative lg:bottom-auto">
                        <div class="flex items-end gap-2">
                            <div class="min-w-0 flex-1">
                                <flux:textarea wire:model="newMessage" :label="__('Reply')" rows="1"
                                    :placeholder="__('Write your message here')" x-data="{
                                        resize() {
                                            $el.style.height = 'auto';
                                            $el.style.height = $el.scrollHeight + 'px'
                                        }
                                    }"
                                    x-init="resize()" x-on:input="resize()" x-on:paste="handlePaste($event)"
                                    x-on:keydown.enter.prevent="if (($wire.newMessage || '').trim() || ($wire.attachmentUploads || []).length) $wire.send()"
                                    class="scrollbar-none resize-none overflow-hidden"
                                    style="min-height: 2.75rem; max-height: 160px; overflow-y: auto;" />
                            </div>

                            <label for="conversation-attachment"
                                class="brand-button-secondary inline-flex min-h-[2.75rem] shrink-0 cursor-pointer items-center justify-center gap-2 px-4 py-3 text-sm">
                                <i class="fa-solid fa-paperclip text-xs"></i>
                                {{ __('Attach') }}
                            </label>
                            <input id="conversation-attachment" x-ref="attachments" type="file" multiple
                                wire:model="attachmentUploads" class="sr-only">

                            <button type="submit"
                                x-bind:disabled="!($wire.newMessage || '').trim() && !($wire.attachmentUploads || []).length"
                                wire:loading.attr="disabled" wire:target="send,attachmentUploads"
                                class="brand-button-primary min-h-[2.75rem] shrink-0 px-5 py-3">
                                <span wire:loading.remove wire:target="send">{{ __('Send') }}</span>
                                <span wire:loading wire:target="send">{{ __('Sending...') }}</span>
                            </button>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <p class="text-xs text-neutral-500 dark:text-zinc-400">{{ __('Press Enter to send.') }}
                            </p>

                        </div>

                        @if ($attachmentUploads !== [])
                            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                @foreach ($attachmentUploads as $index => $upload)
                                    <div wire:key="pending-attachment-{{ $index }}"
                                        class="relative rounded-2xl border border-stone-200 bg-stone-50 p-2 dark:border-white/10 dark:bg-zinc-800">
                                        <button type="button"
                                            wire:click="removeAttachmentUpload({{ $index }})"
                                            class="absolute right-2 top-2 z-10 flex h-6 w-6 items-center justify-center rounded-full bg-black/60 text-xs text-white">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                        @if (Str::startsWith($upload->getMimeType() ?? '', 'image/'))
                                            <img src="{{ $upload->temporaryUrl() }}"
                                                alt="{{ __('Attachment preview') }}"
                                                class="aspect-video w-full rounded-xl object-cover">
                                        @else
                                            <div
                                                class="flex aspect-video items-center justify-center rounded-xl bg-white text-neutral-500 dark:bg-zinc-900 dark:text-zinc-300">
                                                <i class="fa-solid fa-file text-lg"></i>
                                            </div>
                                        @endif
                                        <p class="mt-2 truncate text-xs text-neutral-500 dark:text-zinc-400">
                                            {{ $upload->getClientOriginalName() }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @error('newMessage')
                            <p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                        @error('attachmentUploads')
                            <p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                        @error('attachmentUploads.*')
                            <p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </form>
                </section>
            </div>
        </div>
    </div>
</div>
