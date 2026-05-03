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
    class="flex h-[calc(100dvh-116px)] flex-col overflow-hidden bg-white dark:bg-neutral-950 lg:h-full"
>
    <div
        wire:key="group-video-call-{{ $groupId }}"
        wire:ignore.self
        data-group-video-call
        x-data="window.groupConversationVideoCall({
            authUserId: @js((int) auth()->id()),
            groupId: @js($groupId),
            groupName: @js($groupDisplayName),
            participantSummaries: @js($this->groupParticipantSummaries()),
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
        x-on:group-conversation-auto-answer.window="callId = $event.detail.callId; callStatus = 'incoming'; acceptCall()"
        class="contents"
    >
        <div wire:ignore x-cloak x-show="callStatus !== 'idle'" x-transition.opacity
            x-on:mousemove="showCallChrome()" x-on:click="showCallChrome()" x-on:touchstart.passive="showCallChrome()"
            class="fixed inset-0 z-[70] overflow-hidden bg-neutral-950 text-white">
            <div class="relative h-full w-full overflow-hidden">
                <header x-cloak x-show="callChromeVisible" x-transition.opacity
                    class="absolute left-0 right-0 top-0 z-20 bg-gradient-to-b from-black/70 to-transparent px-4 pb-8 pt-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <a href="{{ route('messages.inbox') }}" wire:navigate x-on:click="leaveCall()"
                                class="mt-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition hover:bg-white/20"
                                aria-label="{{ __('Back to messages') }}">
                                <flux:icon.arrow-left variant="mini" />
                            </a>
                            <div class="min-w-0">
                                <p class="truncate text-lg font-semibold text-white">{{ $groupDisplayName }}</p>
                                <p class="mt-1 text-xs text-white/70">
                                    {{ __('Group call') }}
                                    <span>&middot;</span>
                                    <span x-text="formattedCallDuration()"></span>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <div class="inline-flex items-center gap-1 rounded-full bg-white/10 px-3 py-2 text-sm font-semibold text-white backdrop-blur">
                                <flux:icon.users variant="micro" />
                                <span x-text="activeParticipantCount()"></span>
                            </div>
                            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition hover:bg-white/20" aria-label="{{ __('Add participant') }}">
                                <flux:icon.user-plus variant="mini" />
                            </button>
                            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition hover:bg-white/20" aria-label="{{ __('More call options') }}">
                                <flux:icon.ellipsis-horizontal variant="mini" />
                            </button>
                        </div>
                    </div>
                </header>

                <video id="group-call-local-background-video" autoplay muted playsinline
                    x-cloak x-show="remoteParticipants.length === 0"
                    class="absolute inset-0 h-full w-full bg-neutral-950 object-cover"></video>
                <video id="group-call-speaker-video" autoplay playsinline
                    x-cloak x-show="remoteParticipants.length > 0"
                    class="absolute inset-0 h-full w-full bg-neutral-950 object-cover"></video>
                <div class="absolute inset-0 bg-black/45"></div>

                <div x-cloak x-show="remoteParticipants.length === 0"
                    class="absolute inset-0 z-10 flex flex-col items-center justify-center px-8 text-center">
                    <div class="relative flex h-28 w-28 items-center justify-center">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full border-2 border-green-500/50"></span>
                        <span class="relative flex h-24 w-24 items-center justify-center rounded-full bg-green-600 text-3xl font-bold text-white">
                            {{ auth()->user()->initials() }}
                        </span>
                    </div>
                    <p class="mt-4 text-sm text-white/60">{{ __('Waiting for others to join...') }}</p>
                </div>

                <div x-cloak x-show="remoteParticipants.length > 0"
                    class="absolute bottom-28 left-4 z-10 max-w-[65vw] text-sm font-medium text-white drop-shadow-lg">
                    <p class="truncate" x-text="speakerParticipant()?.name ?? groupName"></p>
                    <p class="mt-1 text-xs text-white/70">
                        <span>{{ __('Connected') }}</span>
                        <span>&middot;</span>
                        <span x-text="formattedCallDuration()"></span>
                    </p>
                </div>

                <div x-cloak x-show="remoteParticipants.length > 1"
                    data-thumbnail-prefix="group-call-thumbnail-video"
                    class="scrollbar-none absolute bottom-24 left-4 right-4 z-20 flex gap-3 overflow-x-auto pb-1 sm:left-auto sm:right-4 sm:top-24 sm:bottom-24 sm:w-24 sm:flex-col sm:overflow-y-auto sm:overflow-x-hidden">
                    <template x-for="participant in thumbnailParticipants()" :key="participant.id">
                        <button type="button" x-on:click="selectSpeaker(participant.id)"
                            class="group relative h-24 w-24 shrink-0 overflow-hidden rounded-xl border-2 border-white/20 bg-neutral-950 text-left shadow-lg transition hover:border-white/40 sm:h-32">
                            <video autoplay playsinline x-bind:id="participant.thumbnailElementId" class="h-full w-full bg-neutral-950 object-cover"></video>
                            <span class="absolute bottom-0 left-0 right-0 bg-black/45 px-1 py-1 text-center text-[10px] font-medium text-white" x-text="participant.name"></span>
                        </button>
                    </template>
                </div>

                <div class="absolute z-30 h-36 w-28 touch-none overflow-hidden rounded-2xl border-2 border-white/30 bg-neutral-950 shadow-xl"
                    x-bind:style="callPreviewStyle()" x-on:mousedown.prevent="startPreviewDrag($event)" x-on:touchstart.prevent="startPreviewDrag($event)">
                    <video id="group-call-local-video" autoplay muted playsinline class="h-full w-full bg-neutral-950 object-cover"></video>
                </div>

                <div class="absolute bottom-0 left-0 right-0 z-20 flex items-center justify-center gap-3 bg-gradient-to-t from-black/80 to-transparent px-4 pb-6 pt-10">
                    <div class="flex items-center gap-3 rounded-full bg-white/60 px-5 py-3 backdrop-blur-md dark:bg-black/60">
                        <button type="button" x-on:click="toggleMicrophone()"
                            x-bind:class="microphoneMuted ? 'bg-red-500/20 text-red-400' : 'bg-neutral-200 text-neutral-700 hover:bg-neutral-300 dark:bg-white/15 dark:text-white dark:hover:bg-white/20'"
                            class="relative flex h-12 w-12 items-center justify-center rounded-full transition" aria-label="{{ __('Toggle microphone') }}">
                            <flux:icon.microphone variant="mini" />
                            <span x-cloak x-show="microphoneMuted" class="absolute h-7 w-0.5 rotate-45 rounded-full bg-red-400"></span>
                        </button>
                        <button type="button" x-on:click="toggleCamera()"
                            x-bind:class="cameraDisabled ? 'bg-red-500/20 text-red-400' : 'bg-neutral-200 text-neutral-700 hover:bg-neutral-300 dark:bg-white/15 dark:text-white dark:hover:bg-white/20'"
                            class="flex h-12 w-12 items-center justify-center rounded-full transition" aria-label="{{ __('Toggle camera') }}">
                            <template x-if="! cameraDisabled"><flux:icon.video-camera variant="mini" /></template>
                            <template x-if="cameraDisabled"><flux:icon.video-camera-slash variant="mini" /></template>
                        </button>
                        <button type="button" class="flex h-12 w-12 items-center justify-center rounded-full bg-neutral-200 text-neutral-700 transition hover:bg-neutral-300 dark:bg-white/15 dark:text-white dark:hover:bg-white/20" aria-label="{{ __('Speaker') }}">
                            <flux:icon.speaker-wave variant="mini" />
                        </button>
                        <button type="button" x-on:click="leaveCall()" class="flex h-16 w-16 items-center justify-center rounded-full bg-red-500 text-white transition hover:scale-105 hover:bg-red-600" aria-label="{{ __('End call') }}">
                            <flux:icon.phone-x-mark variant="solid" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mx-auto grid h-full min-h-0 w-full max-w-[1600px] lg:grid-cols-[20rem_minmax(0,1fr)]">
            <aside class="hidden min-h-0 flex-col overflow-hidden border-r border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-950 lg:flex">
                <div class="shrink-0 pb-4">
                    <span class="brand-kicker">{{ __('Conversations') }}</span>
                    <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('All threads') }}</h2>
                </div>

                <div class="min-h-0 flex-1 overflow-hidden">
                    <livewire:messages.conversation-sidebar :active-group-id="$groupId" :key="'group-sidebar-' . $groupId" />
                </div>
            </aside>

            <div
                class="relative grid min-h-0 overflow-hidden"
                x-bind:class="showInfo ? 'lg:grid-cols-[minmax(0,1fr)_22rem]' : 'lg:grid-cols-[minmax(0,1fr)]'"
            >
                <section class="flex min-h-0 flex-col overflow-hidden bg-white dark:bg-neutral-950">
                    <header class="sticky top-0 z-10 shrink-0 border-b border-neutral-200 bg-white px-3 py-3 dark:border-neutral-800 dark:bg-neutral-900">
                        <div class="flex items-center gap-3">
                            <a href="{{ route('messages.inbox') }}" wire:navigate class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white lg:hidden" aria-label="{{ __('All conversations') }}">
                                <flux:icon.arrow-left variant="mini" />
                            </a>

                            <div class="flex h-11 w-14 shrink-0 items-center">
                                @foreach ($this->members->take(3) as $index => $member)
                                    <x-user-avatar :user="$member->user" size="sm" @class([
                                        'border-2 border-white dark:border-neutral-900',
                                        '-ml-3' => $index > 0,
                                    ]) />
                                @endforeach
                            </div>

                            <div class="min-w-0 flex-1">
                                <h1 class="truncate text-base font-bold text-neutral-900 dark:text-white">{{ $groupDisplayName }}</h1>
                                <p class="mt-0.5 truncate text-xs text-neutral-500 dark:text-neutral-400">{{ __(':count members', ['count' => $this->members->count()]) }}</p>
                            </div>

                            <button type="button" x-on:click="$dispatch('group-call-start')" x-bind:disabled="callStatus !== 'idle' || !supportsVideoCalling()" class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-[var(--brand-600)] px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[var(--brand-700)] disabled:cursor-not-allowed disabled:bg-neutral-300 disabled:text-neutral-500 dark:disabled:bg-neutral-800 dark:disabled:text-neutral-500">
                                <flux:icon.video-camera variant="micro" />
                                {{ __('Start call') }}
                            </button>

                            <button
                                type="button"
                                x-on:click="showInfo = ! showInfo"
                                x-bind:aria-pressed="showInfo.toString()"
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white"
                                title="{{ __('Toggle group info') }}"
                                aria-label="{{ __('Toggle group info') }}"
                            >
                                <flux:icon.information-circle variant="mini" />
                            </button>
                        </div>
                    </header>

                    <div class="scrollbar-none min-h-0 flex-1 overflow-y-auto px-3 py-4 sm:px-5" x-data x-init="$el.scrollTop = $el.scrollHeight" @group-message-sent.window="$nextTick(() => { $el.scrollTop = $el.scrollHeight })">
                        @forelse ($this->threadMessages as $message)
                            @php
                                $isOwnMessage = $message->sender_id === auth()->id();
                                $previousMessage = $this->threadMessages->get($loop->index - 1);
                                $startsNewDate = ! $previousMessage || ! $message->created_at?->isSameDay($previousMessage->created_at);
                                $isGroupedWithPrevious = $previousMessage
                                    && $previousMessage->sender_id === $message->sender_id
                                    && $message->created_at?->isSameDay($previousMessage->created_at);
                                $showSenderLabel = ! $isOwnMessage && ! $isGroupedWithPrevious;
                            @endphp

                            @if ($startsNewDate)
                                <div class="my-4 flex justify-center">
                                    <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-medium text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
                                        {{ $this->messageDateLabel($message) }}
                                    </span>
                                </div>
                            @endif

                            <div wire:key="group-message-{{ $message->id }}" @class([
                                'mb-1 flex',
                                'mt-4' => ! $isGroupedWithPrevious,
                                'justify-end' => $isOwnMessage,
                                'justify-start' => ! $isOwnMessage,
                            ])>
                                <div @class([
                                    'flex max-w-[75%] gap-2',
                                    'items-end' => ! $showSenderLabel,
                                    'items-start' => $showSenderLabel,
                                    'flex-row-reverse' => $isOwnMessage,
                                ])>
                                    @unless ($isOwnMessage)
                                        @if ($showSenderLabel)
                                            <x-user-avatar :user="$message->sender" size="xs" class="mt-5 shrink-0" />
                                        @else
                                            <span class="w-7 shrink-0"></span>
                                        @endif
                                    @endunless

                                    <div @class([
                                        'min-w-0 flex flex-col',
                                        'items-end' => $isOwnMessage,
                                        'items-start' => ! $isOwnMessage,
                                    ])>
                                        @if ($showSenderLabel)
                                            <p class="mb-1 px-1 text-xs font-semibold text-neutral-500 dark:text-neutral-400">{{ $this->memberDisplayName($message->sender) }}</p>
                                        @endif

                                        <div @class([
                                            'w-fit max-w-full overflow-hidden px-4 py-2 text-left text-sm leading-relaxed break-words',
                                            'rounded-2xl rounded-br-sm bg-[var(--brand-600)] text-white' => $isOwnMessage,
                                            'rounded-2xl rounded-bl-sm bg-neutral-100 text-neutral-900 dark:bg-neutral-800 dark:text-white' => ! $isOwnMessage,
                                        ])>
                                            @if (filled($message->content))
                                                <p class="break-words [overflow-wrap:anywhere]">{{ $message->content }}</p>
                                            @endif

                                            @if ($message->attachments->isNotEmpty())
                                                <div class="{{ $message->attachments->count() > 1 ? 'mt-2 grid grid-cols-2 gap-2' : 'mt-2 grid gap-2' }}">
                                                    @foreach ($message->attachments as $attachment)
                                                        @php($attachmentUrl = asset('storage/'.$attachment->path))
                                                        @if (\Illuminate\Support\Str::startsWith($attachment->mime, 'image/'))
                                                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener noreferrer" class="block overflow-hidden rounded-2xl border {{ $isOwnMessage ? 'border-white/25' : 'border-neutral-200 dark:border-neutral-700' }}">
                                                                <img src="{{ $attachmentUrl }}" alt="{{ __('Attached image') }}" class="max-h-52 w-full object-cover" loading="lazy">
                                                            </a>
                                                        @else
                                                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex max-w-full items-center justify-center gap-2 rounded-xl border px-3 py-2 text-xs font-medium {{ $isOwnMessage ? 'border-white/30 bg-white/10 text-white hover:bg-white/15' : 'border-neutral-200 bg-white/70 text-neutral-700 hover:bg-white dark:border-neutral-700 dark:bg-neutral-700 dark:text-white dark:hover:bg-neutral-600' }}">
                                                                <i class="fa-solid {{ \Illuminate\Support\Str::contains($attachment->mime, 'pdf') ? 'fa-file-pdf' : (\Illuminate\Support\Str::contains($attachment->mime, 'word') ? 'fa-file-word' : 'fa-file') }}"></i>
                                                            </a>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>

                                        <p class="mt-1 w-full px-1 text-right text-xs text-neutral-500 dark:text-neutral-500">
                                            {{ $this->messageTimestamp($message) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="flex h-full min-h-80 items-center justify-center rounded-2xl border border-dashed border-neutral-200 px-6 py-12 text-center dark:border-neutral-800">
                                <div>
                                    <span class="brand-kicker">{{ __('No messages yet') }}</span>
                                    <p class="mt-4 max-w-md text-sm leading-7 text-neutral-500 dark:text-neutral-400">{{ __('Start the group conversation here.') }}</p>
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
                            messageLength() {
                                return ($wire.newMessage || '').length;
                            },
                        }"
                        x-on:submit.prevent="if (($wire.newMessage || '').trim() || ($wire.attachmentUploads || []).length) $wire.send()"
                        class="shrink-0 overflow-hidden border-t border-neutral-200 bg-white px-3 py-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        <div class="flex items-end gap-2">
                            <button type="button" class="mb-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-800 dark:hover:text-white" aria-label="{{ __('Emoji') }}">
                                <flux:icon.face-smile variant="mini" />
                            </button>

                            <label for="group-attachment" class="mb-1 inline-flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-full text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-800 dark:hover:text-white" aria-label="{{ __('Attach file') }}">
                                <flux:icon.paper-clip variant="mini" />
                            </label>
                            <input id="group-attachment" x-ref="attachments" type="file" multiple wire:model="attachmentUploads" class="sr-only">

                            <div class="min-w-0 flex-1">
                                <flux:textarea wire:model="newMessage" :label="__('Message')" label:sr-only rows="1" :placeholder="__('Write a message...')" x-on:paste="handlePaste($event)" x-on:keydown.enter.prevent="if (($wire.newMessage || '').trim() || ($wire.attachmentUploads || []).length) $wire.send()" class="scrollbar-none resize-none overflow-hidden rounded-2xl bg-neutral-100 px-4 py-2 text-sm dark:bg-neutral-800" style="min-height: 2.5rem; max-height: 7.5rem; overflow-y: auto;" />
                                <p class="mt-1 text-right text-[10px] font-medium text-neutral-400 dark:text-neutral-500" x-text="`${messageLength()}/2000`"></p>
                            </div>

                            <button type="submit" x-bind:disabled="!($wire.newMessage || '').trim() && !($wire.attachmentUploads || []).length" wire:loading.attr="disabled" wire:target="send,attachmentUploads" x-bind:class="(($wire.newMessage || '').trim() || ($wire.attachmentUploads || []).length) ? 'bg-[var(--brand-600)] text-white shadow-sm hover:bg-[var(--brand-700)]' : 'bg-neutral-200 text-neutral-400 dark:bg-neutral-800 dark:text-neutral-500'" class="mb-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full transition disabled:cursor-not-allowed" aria-label="{{ __('Send') }}">
                                <span wire:loading.remove wire:target="send"><flux:icon.arrow-up variant="mini" /></span>
                                <span wire:loading wire:target="send" class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                            </button>
                        </div>

                        @if ($attachmentUploads !== [])
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($attachmentUploads as $index => $upload)
                                    <span wire:key="group-pending-attachment-{{ $index }}" class="inline-flex max-w-full items-center gap-2 rounded-full border border-stone-200 bg-stone-50 px-3 py-1.5 text-xs font-medium text-neutral-700 dark:border-white/10 dark:bg-zinc-800 dark:text-zinc-200">
                                        <i class="fa-solid fa-paperclip text-xs text-neutral-400"></i>
                                        <span class="max-w-[12rem] truncate">{{ $upload->getClientOriginalName() }}</span>
                                        <button type="button" wire:click="removeAttachmentUpload({{ $index }})" class="text-neutral-400 transition hover:text-neutral-700 dark:hover:text-zinc-100" aria-label="{{ __('Remove attachment') }}">
                                            <i class="fa-solid fa-xmark text-xs"></i>
                                        </button>
                                    </span>
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
                    x-transition:leave="transition ease-in duration-150"
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
