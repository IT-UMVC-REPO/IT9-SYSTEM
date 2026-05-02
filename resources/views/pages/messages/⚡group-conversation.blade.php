<?php

use App\Events\GroupMessageSent;
use App\Models\ConversationGroup;
use App\Models\ConversationGroupMember;
use App\Models\GroupMessage;
use App\Models\GroupMessageAttachment;
use App\Models\User;
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

new #[Title('Group conversation')] class extends Component
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

    public ?int $incomingCallId = null;

    public function mount(int $groupId): void
    {
        abort_unless($this->isMember($groupId), 403);

        $this->groupId = $groupId;
        $this->incomingCallId = request()->boolean('incoming_call')
            ? (int) request()->integer('call_id')
            : null;

        $this->markRead();

        if ($this->incomingCallId !== null && $this->incomingCallId > 0) {
            $this->dispatch('group-conversation-auto-answer', callId: $this->incomingCallId);
        }
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

    #[Computed]
    public function group(): ConversationGroup
    {
        return ConversationGroup::query()
            ->with(['memberUsers:id,name,profile_image', 'members.user:id,name,profile_image'])
            ->findOrFail($this->groupId);
    }

    #[Computed]
    public function threadMessages(): Collection
    {
        return GroupMessage::query()
            ->where('group_id', $this->groupId)
            ->with(['sender:id,name,profile_image', 'attachments'])
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
};
?>

@php
    $pusherBroadcastConfig = config('broadcasting.connections.pusher', []);
    $realtimeEnabled = filled($pusherBroadcastConfig['key'] ?? null) && filled($pusherBroadcastConfig['app_id'] ?? null);
    $groupDisplayName = $this->group->displayName((int) auth()->id());
@endphp

<div
    wire:poll.8s="refreshThread"
    x-data="{
        showInfo: window.innerWidth >= 1024,
        isDesktop() {
            return window.innerWidth >= 1024;
        },
        initInfoPanel() {
            const stored = window.localStorage.getItem('sukimarket_group_info_open');

            if (this.isDesktop() && stored !== null) {
                this.showInfo = stored === 'true';
            }

            if (! this.isDesktop()) {
                this.showInfo = false;
            }

            this.$watch('showInfo', (value) => {
                if (this.isDesktop()) {
                    window.localStorage.setItem('sukimarket_group_info_open', value ? 'true' : 'false');
                }
            });

            window.addEventListener('resize', () => {
                if (! this.isDesktop()) {
                    this.showInfo = false;
                    return;
                }

                const latestStored = window.localStorage.getItem('sukimarket_group_info_open');
                this.showInfo = latestStored === null ? true : latestStored === 'true';
            });
        },
    }"
    x-init="initInfoPanel()"
    class="flex h-[calc(100vh-52px)] flex-col overflow-hidden px-4 py-4 sm:px-6 lg:px-8"
>
    <div
        wire:key="group-video-call-{{ $groupId }}"
        wire:ignore.self
        data-group-video-call
        x-data="window.groupConversationVideoCall({
            authUserId: @js((int) auth()->id()),
            groupId: @js($groupId),
            groupName: @js($groupDisplayName),
            realtimeEnabled: @js($realtimeEnabled),
            routes: {
                iceServers: @js(route('calls.ice-servers')),
                initiate: @js(route('calls.group.initiate')),
                signal: @js(route('calls.group.signal', ['call' => '__CALL_ID__'])),
                answer: @js(route('calls.group.answer', ['call' => '__CALL_ID__'])),
                end: @js(route('calls.group.end', ['call' => '__CALL_ID__'])),
            },
        })"
        x-init="$el.__groupConversationVideoCall = $data;
        init();
        if (@js($incomingCallId) !== null) {
            callId = @js($incomingCallId);
            callStatus = 'incoming';
            window.setTimeout(() => acceptCall());
        }"
        x-on:beforeunload.window="leaveCall()"
        x-on:livewire:navigating.window="leaveCall()"
        x-on:group-call-start.window="startCall()"
        x-on:group-conversation-auto-answer.window="callId = $event.detail.callId; acceptCall()"
        class="contents"
    >
        <div wire:ignore x-cloak x-show="callStatus !== 'idle'" x-transition.opacity class="fixed inset-0 z-[70] bg-neutral-950/95 backdrop-blur-sm">
            <div class="mx-auto grid h-screen max-w-[1600px] grid-rows-[auto_minmax(0,1fr)] p-4 sm:p-6">
                <section class="mb-4 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-white/10 bg-zinc-900 p-4 text-white shadow-2xl shadow-black/35">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-zinc-400">{{ __('GROUP VIDEO CALL') }}</p>
                        <h2 class="mt-2 text-xl font-semibold text-white sm:text-2xl">{{ $groupDisplayName }}</h2>
                        <p class="mt-1 text-sm text-zinc-400" x-text="statusMessage || 'Waiting for members to join...'"></p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <template x-if="callStatus === 'incoming'">
                            <button type="button" x-on:click="acceptCall()" class="brand-button-primary inline-flex items-center gap-2">
                                <i class="fa-solid fa-phone text-sm"></i>
                                {{ __('Accept') }}
                            </button>
                        </template>

                        <button type="button" x-on:click="leaveCall()" class="inline-flex items-center gap-2 rounded-[1.25rem] border border-rose-400/35 bg-rose-500/15 px-5 py-3 text-sm font-semibold text-rose-100 transition hover:border-rose-300/50 hover:bg-rose-500/20">
                            <i class="fa-solid fa-phone-slash text-sm"></i>
                            {{ __('Leave call') }}
                        </button>
                    </div>
                </section>

                <div class="grid min-h-0 gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <section class="relative min-h-0 overflow-hidden rounded-2xl bg-zinc-900 shadow-2xl shadow-black/40">
                        <div class="grid h-full min-h-[24rem] gap-3 p-3" x-bind:class="remoteParticipants.length <= 1 ? 'grid-cols-1' : (remoteParticipants.length <= 4 ? 'grid-cols-2' : 'grid-cols-2 lg:grid-cols-3')">
                            <template x-for="participant in remoteParticipants" :key="participant.id">
                                <video autoplay playsinline x-bind:id="participant.elementId" class="h-full min-h-40 w-full rounded-2xl bg-black object-cover"></video>
                            </template>

                            <template x-if="remoteParticipants.length === 0">
                                <div class="flex h-full items-center justify-center rounded-2xl border border-white/10 text-zinc-400">
                                    {{ __('Waiting for other members...') }}
                                </div>
                            </template>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-white/10 bg-zinc-900 p-4">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-zinc-400">{{ __('LOCAL PREVIEW') }}</p>
                        <video id="group-call-local-video" autoplay muted playsinline class="aspect-video w-full rounded-xl bg-black object-cover"></video>
                    </section>
                </div>
            </div>
        </div>

        <div class="mx-auto grid h-full w-full max-w-[1600px] min-h-0 gap-6 lg:grid-cols-[20rem_minmax(0,1fr)]">
            <aside class="hidden min-h-0 flex-col overflow-hidden lg:flex">
                <div class="shrink-0 pb-4">
                    <span class="brand-kicker">{{ __('Conversations') }}</span>
                    <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('All threads') }}</h2>
                </div>

                <div class="min-h-0 flex-1 overflow-hidden">
                    <livewire:messages.conversation-sidebar :active-group-id="$groupId" :key="'group-sidebar-' . $groupId" />
                </div>
            </aside>

            <div
                class="relative grid min-h-0 gap-4"
                x-bind:class="showInfo ? 'lg:grid-cols-[minmax(0,1fr)_22rem]' : 'lg:grid-cols-[minmax(0,1fr)]'"
            >
                <section class="brand-panel flex min-h-0 flex-col overflow-hidden">
                    <header class="shrink-0 border-b border-stone-200 p-5 dark:border-white/10">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <a href="{{ route('messages.inbox') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-[var(--brand-700)] dark:text-[var(--brand-400)] lg:hidden">
                                    <i class="fa-solid fa-arrow-left text-xs"></i>
                                    {{ __('All conversations') }}
                                </a>
                                <h1 class="brand-serif mt-3 truncate text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ $groupDisplayName }}</h1>
                                <p class="mt-2 text-sm text-neutral-500 dark:text-zinc-400">{{ __(':count members', ['count' => $this->members->count()]) }}</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <button type="button" x-on:click="$dispatch('group-call-start')" x-bind:disabled="callStatus !== 'idle' || !supportsVideoCalling()" class="brand-button-secondary inline-flex items-center gap-2 text-sm disabled:cursor-not-allowed disabled:opacity-60">
                                    <i class="fa-solid fa-video text-sm"></i>
                                    {{ __('Start call') }}
                                </button>

                                <button
                                    type="button"
                                    x-on:click="showInfo = ! showInfo"
                                    x-bind:aria-pressed="showInfo.toString()"
                                    class="brand-button-secondary inline-flex h-11 w-11 items-center justify-center p-0"
                                    title="{{ __('Toggle group info') }}"
                                    aria-label="{{ __('Toggle group info') }}"
                                >
                                    <i class="fa-solid fa-circle-info text-sm"></i>
                                </button>
                            </div>
                        </div>
                    </header>

                    <div class="scrollbar-none min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-5 sm:px-6" x-data x-init="$el.scrollTop = $el.scrollHeight" @group-message-sent.window="$nextTick(() => { $el.scrollTop = $el.scrollHeight })">
                        @forelse ($this->threadMessages as $message)
                            @php($isOwnMessage = $message->sender_id === auth()->id())
                            <div wire:key="group-message-{{ $message->id }}" class="group mb-4 flex {{ $isOwnMessage ? 'justify-end' : 'justify-start' }}">
                                <div class="flex max-w-[76%] flex-col gap-1 {{ $isOwnMessage ? 'items-end' : 'items-start' }}">
                                    @unless ($isOwnMessage)
                                        <p class="px-1 text-xs font-semibold text-neutral-400 dark:text-zinc-500">{{ $this->memberDisplayName($message->sender) }}</p>
                                    @endunless

                                    <div class="flex max-w-full items-end gap-3 {{ $isOwnMessage ? 'flex-row-reverse' : '' }}">
                                        @unless ($isOwnMessage)
                                            <x-user-avatar :user="$message->sender" size="sm" class="shrink-0" />
                                        @endunless

                                        <div class="min-w-0 w-fit max-w-full overflow-hidden rounded-2xl px-4 py-2 text-left text-sm leading-relaxed break-words {{ $isOwnMessage ? 'bg-emerald-600 text-white' : 'bg-zinc-800 text-zinc-100' }}">
                                            @if (filled($message->content))
                                                <p class="break-words [overflow-wrap:anywhere]">{{ $message->content }}</p>
                                            @endif

                                            @if ($message->attachments->isNotEmpty())
                                                <div class="{{ $message->attachments->count() > 1 ? 'mt-2 grid grid-cols-2 gap-2' : 'mt-2 grid gap-2' }}">
                                                    @foreach ($message->attachments as $attachment)
                                                        @php($attachmentUrl = asset('storage/'.$attachment->path))
                                                        @if (Str::startsWith($attachment->mime, 'image/'))
                                                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener noreferrer" class="block overflow-hidden rounded-2xl border {{ $isOwnMessage ? 'border-white/25' : 'border-stone-300 dark:border-zinc-600' }}">
                                                                <img src="{{ $attachmentUrl }}" alt="{{ __('Attached image') }}" class="max-h-52 w-full object-cover" loading="lazy">
                                                            </a>
                                                        @else
                                                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center rounded-xl border px-3 py-2 text-xs font-medium {{ $isOwnMessage ? 'border-white/30 bg-white/10 text-white hover:bg-white/15' : 'border-stone-300 bg-white/70 text-neutral-700 hover:bg-white dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 dark:hover:bg-zinc-600' }}">
                                                                <i class="fa-solid {{ Str::contains($attachment->mime, 'pdf') ? 'fa-file-pdf' : (Str::contains($attachment->mime, 'word') ? 'fa-file-word' : 'fa-file') }}"></i>
                                                            </a>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <p class="px-1 text-xs text-neutral-400 opacity-0 transition-opacity group-hover:opacity-100 dark:text-zinc-500">{{ $message->timeAgo() }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="flex h-full min-h-80 items-center justify-center rounded-[1.75rem] border border-dashed border-stone-200 px-6 py-12 text-center dark:border-white/10">
                                <div>
                                    <span class="brand-kicker">{{ __('No messages yet') }}</span>
                                    <p class="mt-4 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">{{ __('Start the group conversation here.') }}</p>
                                </div>
                            </div>
                        @endforelse
                    </div>

                    <form
                        x-data="{
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
                        class="sticky bottom-0 shrink-0 border-t border-stone-200 bg-white p-4 pb-[max(1rem,env(safe-area-inset-bottom))] dark:border-white/10 dark:bg-zinc-900 lg:relative lg:bottom-auto"
                    >
                        <div class="flex items-end gap-2">
                            <div class="min-w-0 flex-1">
                                <flux:textarea wire:model="newMessage" :label="__('Reply')" rows="1" :placeholder="__('Write your message here')" x-on:paste="handlePaste($event)" x-on:keydown.enter.prevent="if (($wire.newMessage || '').trim() || ($wire.attachmentUploads || []).length) $wire.send()" class="scrollbar-none resize-none overflow-hidden" style="min-height: 2.75rem; max-height: 160px; overflow-y: auto;" />
                               
                            </div>

                            <label for="group-attachment" class="brand-button-secondary inline-flex min-h-[2.75rem] shrink-0 cursor-pointer items-center justify-center gap-2 px-4 py-3 text-sm">
                                <i class="fa-solid fa-paperclip text-xs"></i>
                                {{ __('Attach') }}
                            </label>
                            <input id="group-attachment" x-ref="attachments" type="file" multiple wire:model="attachmentUploads" class="sr-only">

                            <button type="submit" x-bind:disabled="!($wire.newMessage || '').trim() && !($wire.attachmentUploads || []).length" wire:loading.attr="disabled" wire:target="send,attachmentUploads" class="brand-button-primary min-h-[2.75rem] shrink-0 px-5 py-3">
                                <span wire:loading.remove wire:target="send">{{ __('Send') }}</span>
                                <span wire:loading wire:target="send">{{ __('Sending...') }}</span>
                            </button>
                        </div>

                        @if ($attachmentUploads !== [])
                            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                @foreach ($attachmentUploads as $index => $upload)
                                    <div wire:key="group-pending-attachment-{{ $index }}" class="relative rounded-2xl border border-stone-200 bg-stone-50 p-2 dark:border-white/10 dark:bg-zinc-800">
                                        <button type="button" wire:click="removeAttachmentUpload({{ $index }})" class="absolute right-2 top-2 z-10 flex h-6 w-6 items-center justify-center rounded-full bg-black/60 text-xs text-white">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                        @if (Str::startsWith($upload->getMimeType() ?? '', 'image/'))
                                            <img src="{{ $upload->temporaryUrl() }}" alt="{{ __('Attachment preview') }}" class="aspect-video w-full rounded-xl object-cover">
                                        @else
                                            <div class="flex aspect-video items-center justify-center rounded-xl bg-white text-neutral-500 dark:bg-zinc-900 dark:text-zinc-300">
                                                <i class="fa-solid fa-file text-lg"></i>
                                            </div>
                                        @endif
                                        <p class="mt-2 truncate text-xs text-neutral-500 dark:text-zinc-400">{{ $upload->getClientOriginalName() }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @error('newMessage') <p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        @error('attachmentUploads') <p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        @error('attachmentUploads.*') <p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </form>
                </section>

                <div
                    x-cloak
                    x-show="showInfo && ! isDesktop()"
                    x-transition.opacity
                    x-on:click="showInfo = false"
                    class="fixed inset-0 z-50 bg-neutral-950/50 backdrop-blur-sm lg:hidden"
                ></div>

                <aside
                    x-cloak
                    x-show="showInfo"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="translate-x-full opacity-0 lg:translate-x-0"
                    x-transition:enter-end="translate-x-0 opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="translate-x-0 opacity-100"
                    x-transition:leave-end="translate-x-full opacity-0 lg:translate-x-0"
                    class="brand-panel fixed bottom-0 right-0 top-[52px] z-[60] min-h-0 w-[min(24rem,calc(100vw-1rem))] overflow-y-auto p-5 shadow-2xl lg:static lg:z-auto lg:block lg:w-auto lg:shadow-none"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <span class="brand-kicker">{{ __('Group') }}</span>
                            <h2 class="mt-3 text-lg font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Members') }}</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" x-on:click="showInfo = false" class="brand-button-secondary inline-flex h-9 w-9 items-center justify-center p-0 lg:hidden" aria-label="{{ __('Close group info') }}">
                                <i class="fa-solid fa-xmark text-xs"></i>
                            </button>
                            <button type="button" wire:click="leaveGroup" wire:confirm="{{ __('Leave this group?') }}" class="text-xs font-semibold text-rose-600 transition hover:text-rose-700 dark:text-rose-400">
                                {{ __('Leave') }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        @foreach ($this->members as $member)
                            <div wire:key="group-member-{{ $member->user_id }}" class="flex items-center gap-3">
                                <x-user-avatar :user="$member->user" size="sm" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ $this->memberDisplayName($member->user) }}</p>
                                    <p class="text-xs uppercase tracking-[0.16em] text-neutral-400 dark:text-zinc-500">{{ $member->role }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($this->isGroupAdmin())
                        <div class="mt-6 border-t border-stone-200 pt-5 dark:border-white/10">
                            <flux:field>
                                <flux:label>{{ __('Add member') }}</flux:label>
                                <flux:input wire:model.live.debounce.250ms="memberSearch" :placeholder="__('Search users')" />
                            </flux:field>

                            @if ($this->availableMembers->isNotEmpty())
                                <div class="mt-3 space-y-2">
                                    @foreach ($this->availableMembers as $user)
                                        <button type="button" wire:click="addMember({{ $user->id }})" wire:key="available-member-{{ $user->id }}" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left transition hover:bg-stone-50 dark:hover:bg-white/10">
                                            <x-user-avatar :user="$user" size="sm" />
                                            <span class="truncate text-sm font-semibold text-neutral-800 dark:text-zinc-100">{{ $user->name }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </aside>
            </div>
        </div>
    </div>
</div>
