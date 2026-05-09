@php
    $pusherBroadcastConfig = config('broadcasting.connections.pusher', []);
    $realtimeEnabled =
        filled($pusherBroadcastConfig['key'] ?? null) && filled($pusherBroadcastConfig['app_id'] ?? null);
    $authUser = auth()->user();
@endphp

<div wire:poll.5s="refreshThread" class="flex h-[calc(100dvh-116px)] flex-col overflow-hidden bg-white dark:bg-neutral-950 lg:h-full">
    <div wire:key="conversation-video-call-{{ $otherUserId }}" wire:ignore.self data-conversation-video-call
        x-data="{ volume: 1.0, ...window.conversationVideoCall({
            authUserId: @js((int) auth()->id()),
            conversationKey: @js(\App\Models\Message::conversationKey($otherUserId)),
            otherUserId: @js($otherUserId),
            otherUserName: @js($this->otherUser->name),
            otherUserInitials: @js($this->otherUser->initials()),
            realtimeEnabled: @js($realtimeEnabled),
            routes: {
                iceServers: @js(route('calls.ice-servers')),
                initiate: @js(route('calls.initiate')),
                signal: @js(route('calls.signal', ['call' => '__CALL_ID__'])),
                answer: @js(route('calls.answer', ['call' => '__CALL_ID__'])),
                decline: @js(route('calls.decline', ['call' => '__CALL_ID__'])),
                end: @js(route('calls.end', ['call' => '__CALL_ID__'])),
            },
        }) }" x-init="$el.__conversationVideoCall = $data;
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
            x-on:mousemove="showCallChrome()" x-on:click="showCallChrome()" x-on:touchstart.passive="showCallChrome()"
            class="fixed inset-0 z-[70] overflow-hidden bg-neutral-950 text-white">
            <div class="relative h-full w-full overflow-hidden">
                <header x-cloak x-show="callChromeVisible" x-transition.opacity
                    class="absolute left-0 right-0 top-0 z-20 bg-gradient-to-b from-black/70 to-transparent px-4 pb-8 pt-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <a href="{{ route('messages.inbox') }}" wire:navigate x-on:click="disposeOnLeave()"
                                class="mt-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition hover:bg-white/20"
                                aria-label="{{ __('Back to messages') }}">
                                <flux:icon.arrow-left variant="mini" />
                            </a>
                            <div class="min-w-0">
                                <p class="truncate text-lg font-semibold text-white" x-text="otherUserName"></p>
                                <p class="mt-1 text-xs text-white/70">
                                    <span x-text="callStatus === 'active' ? 'Connected' : (callStatus === 'incoming' ? 'Incoming call' : 'Calling')"></span>
                                    <span>&middot;</span>
                                    <span x-text="formattedCallDuration()"></span>
                                </p>
                            </div>
                        </div>

                        <div class="relative" x-data="{
                            open: false,
                            devicesOpen: false,
                            devices: [],
                            async loadDevices() {
                                try {
                                    const allDevices = await navigator.mediaDevices.enumerateDevices();
                                    this.devices = allDevices.map((device) => ({
                                        kind: device.kind,
                                        label: device.label || device.kind,
                                        id: device.deviceId,
                                    }));
                                } catch (error) {
                                    this.devices = [];
                                }
                            },
                        }">
                            <button type="button" x-on:click="open = !open" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition hover:bg-white/20" aria-label="{{ __('More call options') }}">
                                <flux:icon.ellipsis-horizontal variant="mini" />
                            </button>
                            <div x-cloak x-show="open" x-on:click.away="open = false" class="absolute right-0 top-full mt-2 w-48 rounded-lg bg-white p-1 shadow-lg ring-1 ring-black/5 dark:bg-zinc-800 dark:ring-white/10">
                                <button type="button" x-on:click="devicesOpen = true; open = false; loadDevices()" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-neutral-700 transition hover:bg-neutral-100 dark:text-zinc-200 dark:hover:bg-white/10">
                                    <flux:icon.device-phone-mobile variant="micro" class="h-4 w-4" />
                                    {{ __('Device settings') }}
                                </button>
                                <button type="button" x-on:click="
                                    const el = $el.closest('.fixed.inset-0');
                                    if (! document.fullscreenElement) {
                                        el?.requestFullscreen?.();
                                    } else {
                                        document.exitFullscreen?.();
                                    }
                                    open = false;
                                " class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-neutral-700 transition hover:bg-neutral-100 dark:text-zinc-200 dark:hover:bg-white/10">
                                    <flux:icon.arrows-pointing-out variant="micro" class="h-4 w-4" />
                                    {{ __('Full screen') }}
                                </button>
                            </div>
                            <div x-cloak x-show="devicesOpen" x-on:click.away="devicesOpen = false" class="absolute right-0 top-full z-50 mt-2 w-72 rounded-2xl border border-white/10 bg-zinc-900 p-4 shadow-xl">
                                <p class="mb-3 text-sm font-semibold text-white">{{ __('Available devices') }}</p>
                                <template x-for="device in devices.filter((device) => device.kind === 'audioinput')" :key="'audio-' + device.id">
                                    <div class="flex items-center gap-2 py-1 text-xs text-zinc-300">
                                        <flux:icon.microphone variant="micro" class="h-3 w-3" />
                                        <span x-text="device.label"></span>
                                    </div>
                                </template>
                                <template x-for="device in devices.filter((device) => device.kind === 'videoinput')" :key="'video-' + device.id">
                                    <div class="flex items-center gap-2 py-1 text-xs text-zinc-300">
                                        <flux:icon.video-camera variant="micro" class="h-3 w-3" />
                                        <span x-text="device.label"></span>
                                    </div>
                                </template>
                                <button type="button" x-on:click="devicesOpen = false" class="mt-3 text-xs text-zinc-400 transition hover:text-white">
                                    {{ __('Close') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </header>

                <video id="conversation-call-local-background-video" autoplay muted playsinline
                    x-cloak x-show="callStatus === 'calling' || callStatus === 'incoming'"
                    x-bind:class="cameraDisabled ? 'opacity-0' : 'opacity-100 blur-2xl'"
                    class="absolute inset-0 h-full w-full bg-neutral-950 object-cover transition-opacity duration-200"
                    style="transform: scaleX(-1) scale(1.1);"></video>
                <div class="absolute inset-0" x-cloak x-show="callStatus === 'active' || callStatus === 'connecting'">
                    <div
                        x-show="! remoteVideoActive"
                        class="absolute inset-0 flex flex-col items-center justify-center bg-neutral-900"
                    >
                        <div
                            class="flex h-24 w-24 items-center justify-center rounded-full text-3xl font-bold text-white ring-4 ring-white/20"
                            style="background-color: var(--brand-700);"
                            x-text="otherUserInitials"
                        ></div>
                        <p class="mt-3 text-sm font-semibold text-white/80" x-text="otherUserName"></p>
                        <p class="mt-1 text-xs text-white/50">{{ __('Camera is off') }}</p>
                    </div>

                    <video
                        id="conversation-call-remote-video"
                        autoplay
                        playsinline
                        x-effect="$el.volume = Number(volume)"
                        x-bind:class="remoteVideoActive ? 'opacity-100' : 'opacity-0'"
                        class="absolute inset-0 h-full w-full bg-neutral-950 object-cover transition-opacity duration-300"
                    ></video>
                </div>
                <div class="absolute inset-0 bg-black/45"></div>

                <div x-cloak x-show="callStatus === 'calling' || callStatus === 'incoming'"
                    class="absolute inset-0 z-10 flex flex-col items-center justify-center px-8 text-center">
                    <div class="flex h-24 w-24 items-center justify-center rounded-full bg-[var(--brand-600)] text-3xl font-bold text-white ring-4 ring-white/20"
                        x-bind:class="callStatus === 'calling' ? 'animate-pulse' : ''"
                        x-text="otherUserInitials"></div>
                    <h2 class="mt-4 max-w-full truncate text-2xl font-bold text-white" x-text="otherUserName"></h2>
                    <p class="mt-2 text-sm text-white/60">
                        <span x-text="callStatus === 'incoming' ? 'Incoming call' : 'Calling'"></span><span class="animate-pulse">...</span>
                    </p>
                </div>

                <div x-cloak x-show="callStatus === 'active' || callStatus === 'connecting'"
                    class="absolute bottom-28 left-4 z-10 text-sm font-medium text-white drop-shadow-lg">
                    <p x-text="otherUserName"></p>
                    <p class="mt-1 text-xs text-white/70">
                        <span x-text="callStatus === 'active' ? 'Connected' : 'Connecting'"></span>
                        <span>&middot;</span>
                        <span x-text="formattedCallDuration()"></span>
                    </p>
                </div>

                <div class="absolute z-20 h-36 w-28 touch-none overflow-hidden rounded-2xl border-2 border-white/30 bg-neutral-950 shadow-xl"
                    x-bind:style="callPreviewStyle()" x-on:mousedown.prevent="startPreviewDrag($event)" x-on:touchstart.prevent="startPreviewDrag($event)">
                    <video id="conversation-call-local-video" autoplay muted playsinline
                        x-bind:class="cameraDisabled ? 'opacity-0' : 'opacity-100'"
                        class="h-full w-full scale-x-[-1] bg-neutral-950 object-cover transition-opacity duration-200"></video>
                    <div
                        x-cloak
                        x-show="cameraDisabled"
                        class="absolute inset-0 flex flex-col items-center justify-center transition-opacity duration-200"
                        style="background-color: var(--brand-700);"
                    >
                        @if ($authUser?->profile_image)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($authUser->profile_image) }}"
                                alt="{{ $authUser->name }}"
                                class="h-12 w-12 rounded-full object-cover ring-2 ring-white/30">
                        @else
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-white/20 text-lg font-bold text-white">
                                {{ $authUser?->initials() ?? '?' }}
                            </span>
                        @endif
                        <span class="mt-1 text-[10px] font-semibold text-white/70">{{ __('Camera off') }}</span>
                    </div>
                    <button type="button" x-cloak
                        x-show="hasMultipleCameras && (callStatus === 'active' || callStatus === 'connecting')"
                        x-on:click.stop="switchCamera()"
                        class="absolute bottom-2 right-2 flex h-8 w-8 items-center justify-center rounded-full bg-black/65 text-white shadow-lg backdrop-blur transition hover:bg-black/80"
                        title="{{ __('Switch camera') }}" aria-label="{{ __('Switch camera') }}">
                        <i class="fa-solid fa-camera-rotate text-xs"></i>
                    </button>
                </div>

                <div class="absolute bottom-0 left-0 right-0 z-20 flex items-center justify-center gap-3 bg-gradient-to-t from-black/80 to-transparent px-4 pb-6 pt-10">
                    <template x-if="callStatus === 'incoming'">
                        <div class="flex items-center gap-4">
                            <button type="button" x-on:click="acceptCall()" class="flex h-16 w-16 items-center justify-center rounded-full bg-[var(--brand-600)] text-white transition hover:bg-[var(--brand-700)]" aria-label="{{ __('Accept call') }}">
                                <flux:icon.phone variant="solid" />
                            </button>
                            <button type="button" x-on:click="declineCall()" class="flex h-16 w-16 items-center justify-center rounded-full bg-red-500 text-white transition hover:bg-red-600" aria-label="{{ __('Decline call') }}">
                                <flux:icon.phone-x-mark variant="solid" />
                            </button>
                        </div>
                    </template>

                    <template x-if="callStatus !== 'incoming'">
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
                            <div class="relative flex items-center" x-data="{ showVolume: false }">
                                <button type="button" x-on:click="showVolume = !showVolume" 
                                    x-bind:class="showVolume ? 'bg-[var(--brand-600)] text-white' : 'bg-neutral-200 text-neutral-700 hover:bg-neutral-300 dark:bg-white/15 dark:text-white dark:hover:bg-white/20'"
                                    class="flex h-12 w-12 items-center justify-center rounded-full transition shadow-sm" aria-label="{{ __('Speaker volume') }}">
                                    <template x-if="volume > 0"><flux:icon.speaker-wave variant="mini" /></template>
                                    <template x-if="volume == 0"><flux:icon.speaker-x-mark variant="mini" /></template>
                                </button>
                                
                                <div x-cloak x-show="showVolume" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-4" x-on:click.away="showVolume = false" 
                                    class="absolute bottom-full left-1/2 mb-6 flex h-48 w-14 -translate-x-1/2 flex-col items-center justify-between rounded-[2rem] bg-white/95 p-4 shadow-2xl backdrop-blur-xl ring-1 ring-black/5 dark:bg-zinc-900/95 dark:ring-white/10">
                                    <div class="relative h-full w-2.5 rounded-full bg-neutral-100 dark:bg-white/10">
                                        <!-- Progress Fill -->
                                        <div class="absolute bottom-0 w-full rounded-full bg-[var(--brand-600)] transition-all duration-150 ease-out"
                                            x-bind:style="`height: ${volume * 100}%`"></div>
                                        
                                        <!-- Thumb Dot -->
                                        <div class="absolute left-1/2 h-5 w-5 -translate-x-1/2 rounded-full border-2 border-white bg-[var(--brand-600)] shadow-xl transition-all duration-150 ease-out pointer-events-none"
                                            x-bind:style="`bottom: calc(${volume * 100}% - 10px)`"></div>
                                        
                                        <!-- Interactive Range Input (Invisible) -->
                                        <input type="range" min="0" max="1" step="0.01" x-model="volume"
                                            class="absolute inset-x-[-12px] inset-y-0 z-20 w-[calc(100%+24px)] cursor-pointer opacity-0"
                                            style="-webkit-appearance: slider-vertical; appearance: slider-vertical; writing-mode: bt-lr;"
                                            orient="vertical">
                                    </div>

                                    <div class="mt-3 flex flex-col items-center">
                                        <span class="text-[10px] font-bold text-neutral-500 dark:text-neutral-400" x-text="Math.round(volume * 100) + '%'"></span>
                                    </div>
                                </div>
                            </div>
                            <button type="button"
                                x-cloak
                                x-show="callStatus === 'active' && isPipSupported()"
                                x-on:click="enterPip()"
                                class="flex h-12 w-12 items-center justify-center rounded-full bg-neutral-200 text-neutral-700 transition hover:bg-neutral-300 dark:bg-white/15 dark:text-white dark:hover:bg-white/20"
                                title="{{ __('Picture in picture') }}"
                                aria-label="{{ __('Picture in picture') }}">
                                <flux:icon.squares-2x2 variant="mini" />
                            </button>
                            <button type="button" x-on:click="endCall(callStatus === 'calling' ? 'Call cancelled.' : 'Call ended.')" class="flex h-16 w-16 items-center justify-center rounded-full bg-red-500 text-white transition hover:scale-105 hover:bg-red-600" aria-label="{{ __('End call') }}">
                                <flux:icon.phone-x-mark variant="solid" />
                            </button>
                        </div>
                    </template>
                </div>

            </div>
        </div>

        <div class="mx-auto grid h-full min-h-0 w-full max-w-[1600px] lg:grid-cols-[20rem_minmax(0,1fr)]">
            <aside class="hidden min-h-0 flex-col overflow-hidden border-r border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-950 lg:flex">
                <div class="shrink-0 pb-4">
                    <a href="{{ route('messages.inbox') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold text-[var(--brand-600)] transition hover:text-[var(--brand-700)] dark:text-[var(--brand-400)] dark:hover:text-[var(--brand-300)]">
                        <flux:icon.arrow-left variant="micro" class="h-4 w-4" />
                        {{ __('Return to Inbox') }}
                    </a>
                    <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">
                        {{ __('All threads') }}</h2>
                </div>

                <div class="min-h-0 flex-1 overflow-hidden">
                    <livewire:messages.conversation-sidebar :active-conversation-user-id="$otherUserId" :key="'conversation-sidebar-' . $otherUserId" />
                </div>
            </aside>

            <div class="flex min-h-0 flex-1 flex-col overflow-hidden bg-white dark:bg-neutral-950">
                <header
                    x-data="onlinePresence({
                        conversationKey: @js(\App\Models\Message::conversationKey($otherUserId)),
                        authUserId: @js((int) auth()->id()),
                    })"
                    x-init="init()"
                    x-on:destroy="destroy()"
                    data-conversation-presence
                    class="sticky top-0 z-10 shrink-0 border-b border-neutral-200 bg-white px-3 py-3 dark:border-neutral-800 dark:bg-neutral-900"
                >
                    <div class="flex items-center gap-3">
                        <a href="{{ route('messages.inbox') }}" wire:navigate
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white lg:hidden"
                            aria-label="{{ __('All conversations') }}">
                            <flux:icon.arrow-left variant="mini" />
                        </a>

                        <div class="relative inline-flex shrink-0">
                            <x-user-avatar :user="$this->otherUser" size="profile" />
                            <span
                                x-cloak
                                x-show="isOnline(@js($otherUserId))"
                                x-transition.opacity
                                class="absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-white bg-emerald-500 dark:border-zinc-900"
                                title="{{ __('Online') }}"
                            ></span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <h1 class="truncate text-base font-bold text-neutral-900 dark:text-white">
                                <livewire:messaging.nickname-editor :target-user-id="$otherUserId" :profile-route="$this->otherUserProfileRoute"
                                    :key="'nickname-editor-' . $otherUserId" />
                            </h1>
                            <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ $this->conversationSubtitle() }}</p>
                        </div>

                        <a href="{{ $this->otherUserProfileRoute }}" wire:navigate
                            class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-full text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white sm:inline-flex"
                            aria-label="{{ __('Edit nickname or profile') }}">
                            <flux:icon.pencil-square variant="mini" />
                        </a>

                        <div wire:ignore>
                            <button type="button" x-on:click="$dispatch('video-call-start')"
                                x-bind:disabled="callStatus !== 'idle' || !supportsVideoCalling()"
                                x-bind:title="supportsVideoCalling() ? 'Start video call' : videoCallDisabledReason()"
                                x-bind:class="supportsVideoCalling() ? '' : 'cursor-not-allowed opacity-50'"
                                class="inline-flex h-10 shrink-0 items-center gap-2 rounded-full bg-[var(--brand-600)] px-3 text-xs font-semibold text-white transition hover:bg-[var(--brand-700)] disabled:cursor-not-allowed disabled:opacity-60">
                                <flux:icon.video-camera variant="micro" />
                                {{ __('Video call') }}
                            </button>
                        </div>
                    </div>
                </header>

                @if ($this->linkedOrder !== null)
                    <section class="brand-panel-muted shrink-0 px-5 py-3">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex min-w-0 flex-wrap items-center gap-3">
                                <span class="brand-kicker !mb-0">{{ __('Linked order') }}</span>
                                <span class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">
                                    {{ __('Order #:number', ['number' => str_pad((string) $this->linkedOrder->id, 6, '0', STR_PAD_LEFT)]) }}
                                </span>
                                <span class="text-xs text-neutral-500 dark:text-zinc-400">
                                    {{ __('Status: :status', ['status' => \Illuminate\Support\Str::headline($this->linkedOrder->order_status->value)]) }}
                                </span>
                            </div>
                            <span class="text-xs text-neutral-400 dark:text-zinc-500">
                                {{ __(':customer -> :vendor', [
                                    'customer' => $this->linkedOrder->customer->name,
                                    'vendor' => $this->linkedOrder->vendor->user->name,
                                ]) }}
                            </span>
                        </div>
                    </section>
                @endif

                <section class="flex min-h-0 flex-1 flex-col overflow-hidden bg-white dark:bg-neutral-950">
                    <div class="scrollbar-none min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-5 sm:px-6" x-data
                        x-init="$el.scrollTop = $el.scrollHeight"
                        @message-sent.window="$nextTick(() => { $el.scrollTop = $el.scrollHeight })">
                        @php($previousMessage = null)
                        @php($latestOwnMessageId = $this->latestOwnMessageId())
                        @forelse ($messages as $message)
                            @php($isOwnMessage = $message['sender_id'] === auth()->id())
                            @php($messageDateKey = $message['date_key'])
                            @php($previousDateKey = $previousMessage['date_key'] ?? null)
                            @php($nextMessage = $messages[$loop->index + 1] ?? null)
                            @php($showDateSeparator = $previousMessage === null || $messageDateKey !== $previousDateKey)
                            @php($isConsecutive = $previousMessage !== null && $previousMessage['sender_id'] === $message['sender_id'] && $messageDateKey === $previousDateKey)
                            @php($isGroupedWithNext = $nextMessage !== null && $nextMessage['sender_id'] === $message['sender_id'] && $nextMessage['date_key'] === $messageDateKey)

                            @if ($showDateSeparator)
                                <div wire:key="conversation-date-{{ $messageDateKey ?? $message['id'] }}" class="flex justify-center py-2">
                                    <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-medium text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
                                        {{ $message['date_label'] }}
                                    </span>
                                </div>
                            @endif

                            <div wire:key="conversation-message-{{ $message['id'] }}"
                                x-data="{ showTime: false }"
                                x-on:click.stop="showTime = ! showTime"
                                class="group flex {{ $isOwnMessage ? 'justify-end' : 'justify-start' }} {{ $isConsecutive ? 'mt-1' : 'mt-4' }}">
                                <div
                                    class="flex max-w-[75%] flex-col gap-1 {{ $isOwnMessage ? 'items-end' : 'items-start' }}">
                                    <div
                                        class="flex max-w-full items-end gap-2 {{ $isOwnMessage ? 'flex-row-reverse' : '' }}">
                                        @unless ($isOwnMessage)
                                            @if ($isGroupedWithNext)
                                                <span class="h-[34px] w-[34px] shrink-0"></span>
                                            @else
                                                <x-user-avatar :user="$message['sender']" size="sm" class="shrink-0" />
                                            @endif
                                        @endunless

                                        <div
                                            class="min-w-0 w-fit max-w-full overflow-hidden px-4 py-2 text-left text-sm leading-relaxed break-words {{ $isOwnMessage ? 'rounded-2xl rounded-br-sm bg-[var(--brand-600)] text-white' : 'rounded-2xl rounded-bl-sm bg-neutral-100 text-neutral-900 dark:bg-neutral-800 dark:text-white' }}">
                                            <?php if (filled($message['content'])): ?>
                                            <p class="break-words [overflow-wrap:anywhere]">
                                                {{ $message['content'] }}</p>
                                            <?php endif; ?>

                                            @php($attachments = collect($message['attachments'] ?? []))

                                            @if ($attachments->isNotEmpty())
                                                <div
                                                    class="{{ $attachments->count() > 1 ? 'mt-2 grid grid-cols-2 gap-2' : 'mt-2 grid gap-2' }}">
                                                    @foreach ($attachments as $attachment)
                                                        @php($attachmentUrl = $attachment['public_url'])
                                                        @php($attachmentMime = $attachment['mime'] ?? 'application/octet-stream')

                                                        @if (\Illuminate\Support\Str::startsWith($attachmentMime, 'image/'))
                                                            <a href="{{ $attachmentUrl }}" target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="block overflow-hidden rounded-2xl border {{ $isOwnMessage ? 'border-white/25' : 'border-stone-300 dark:border-zinc-600' }}">
                                                                <img src="{{ $attachmentUrl }}"
                                                                    alt="{{ __('Attached image') }}"
                                                                    referrerpolicy="no-referrer"
                                                                    crossorigin="anonymous"
                                                                    onerror="this.onerror=null; this.src='https://placehold.co/320x320/1f1f1f/6b7280?text=Image+unavailable';"
                                                                    class="max-h-52 w-full object-cover"
                                                                    loading="lazy">
                                                            </a>
                                                        @elseif (\Illuminate\Support\Str::startsWith($attachmentMime, 'video/'))
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
                                                        @elseif (\Illuminate\Support\Str::startsWith($attachmentMime, 'audio/'))
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
                                                                title="{{ \Illuminate\Support\Str::contains($attachmentMime, 'pdf') ? __('Document') : __('Document') }}"
                                                                class="inline-flex max-w-full items-center justify-center gap-2 rounded-xl border px-3 py-2 text-xs font-medium {{ $isOwnMessage ? 'border-white/30 bg-white/10 text-white hover:bg-white/15' : 'border-stone-300 bg-white/70 text-neutral-700 hover:bg-white dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 dark:hover:bg-zinc-600' }}">
                                                                <i
                                                                    class="fa-solid {{ \Illuminate\Support\Str::contains($attachmentMime, 'pdf') ? 'fa-file-pdf' : (\Illuminate\Support\Str::contains($attachmentMime, 'word') ? 'fa-file-word' : 'fa-file') }}"></i>
                                                            </a>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <p
                                        x-cloak
                                        x-show="showTime"
                                        x-transition:enter="transition ease-out duration-150"
                                        x-transition:enter-start="opacity-0 -translate-y-1"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        x-transition:leave="transition ease-in duration-100"
                                        x-transition:leave-start="opacity-100 translate-y-0"
                                        x-transition:leave-end="opacity-0 -translate-y-1"
                                        class="mt-0.5 px-1 text-right text-[11px] text-neutral-400 dark:text-neutral-500">
                                        {{ $message['time'] }}
                                        @if ($isOwnMessage && $message['id'] === $latestOwnMessageId)
                                            <span>&middot;</span>
                                            {{ $message['is_read'] ? __('Read') : __('Sent') }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            @php($previousMessage = $message)
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

                    <form
                        x-data="{
                            ...typingIndicator({
                                conversationKey: @js(\App\Models\Message::conversationKey($otherUserId)),
                                authUserId: @js((int) auth()->id()),
                                otherUserName: @js($this->otherUser->name),
                            }),
                        messageLength() {
                            return ($wire.newMessage || '').length;
                        },
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
                        x-init="init()"
                        x-on:destroy="destroy()"
                        x-on:submit.prevent="if (($wire.newMessage || '').trim() || ($wire.attachmentUploads || []).length) $wire.send()"
                        class="shrink-0 overflow-visible border-t border-neutral-200 bg-white px-3 py-2 pb-[max(0.75rem,env(safe-area-inset-bottom))] dark:border-neutral-800 dark:bg-neutral-900">
                        <div
                            x-cloak
                            x-show="isTyping"
                            x-transition.opacity
                            class="flex items-center gap-2 px-2 pb-2 text-sm text-neutral-500 dark:text-zinc-400"
                        >
                            <span class="flex gap-0.5">
                                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-[var(--brand-500)]" style="animation-delay:0ms"></span>
                                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-[var(--brand-500)]" style="animation-delay:150ms"></span>
                                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-[var(--brand-500)]" style="animation-delay:300ms"></span>
                            </span>
                            <span x-text="`${otherUserName} is typing...`" class="italic"></span>
                        </div>

                        <div class="flex items-center gap-2 relative" x-data="{ showEmoji: false }">
                            <button type="button" x-on:click="showEmoji = !showEmoji" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-800 dark:hover:text-white" aria-label="{{ __('Emoji') }}">
                                <flux:icon.face-smile variant="mini" />
                            </button>

                            <div x-cloak x-show="showEmoji" x-on:click.away="showEmoji = false" class="absolute bottom-12 left-0 z-50">
                                <emoji-picker class="dark" x-on:emoji-click="$wire.newMessage = ($wire.newMessage || '') + $event.detail.unicode; showEmoji = false;"></emoji-picker>
                            </div>

                            <label for="conversation-attachment"
                                class="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-full text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-800 dark:hover:text-white"
                                aria-label="{{ __('Attach file') }}">
                                <flux:icon.paper-clip variant="mini" />
                            </label>
                            <input id="conversation-attachment" x-ref="attachments" type="file" multiple
                                wire:model="attachmentUploads" class="sr-only">

                            <div class="min-w-0 flex-1">
                                <flux:textarea wire:model="newMessage" rows="1" aria-label="{{ __('Reply') }}"
                                    :placeholder="__('Write a message...')" x-data="{
                                        resize() {
                                            $el.style.height = 'auto';
                                            $el.style.height = $el.scrollHeight + 'px'
                                        }
                                    }"
                                    x-init="resize()" x-on:input="resize()" x-on:paste="handlePaste($event)"
                                    x-on:keydown="onKeydown()"
                                    x-on:keydown.enter.prevent="if (($wire.newMessage || '').trim() || ($wire.attachmentUploads || []).length) $wire.send()"
                                    class="scrollbar-none resize-none overflow-hidden rounded-2xl bg-neutral-100 px-4 py-2 text-sm text-neutral-900 dark:bg-neutral-800 dark:text-white"
                                    style="min-height: 2.5rem; max-height: 7.5rem; overflow-y: auto;" />
                            </div>

                            <button type="submit"
                                x-bind:disabled="!($wire.newMessage || '').trim() && !($wire.attachmentUploads || []).length"
                                x-bind:class="(($wire.newMessage || '').trim() || ($wire.attachmentUploads || []).length) ? 'bg-[var(--brand-600)] text-white hover:bg-[var(--brand-700)]' : 'bg-neutral-200 text-neutral-400 dark:bg-neutral-800 dark:text-neutral-500'"
                                wire:loading.attr="disabled" wire:target="send,attachmentUploads"
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full transition disabled:cursor-not-allowed">
                                <span wire:loading.remove wire:target="send"><flux:icon.arrow-up variant="mini" /></span>
                                <span wire:loading wire:target="send" class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                            </button>
                        </div>

                        @if ($attachmentUploads !== [])
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($attachmentUploads as $index => $upload)
                                    <span wire:key="pending-attachment-{{ $index }}" class="inline-flex max-w-full items-center gap-2 rounded-full border border-stone-200 bg-stone-50 px-3 py-1.5 text-xs font-medium text-neutral-700 dark:border-white/10 dark:bg-zinc-800 dark:text-zinc-200">
                                        <i class="fa-solid fa-paperclip text-xs text-neutral-400"></i>
                                        <span class="max-w-[12rem] truncate">{{ $upload->getClientOriginalName() }}</span>
                                        <button type="button" wire:click="removeAttachmentUpload({{ $index }})" class="text-neutral-400 transition hover:text-neutral-700 dark:hover:text-zinc-100" aria-label="{{ __('Remove attachment') }}">
                                            <i class="fa-solid fa-xmark text-xs"></i>
                                        </button>
                                    </span>
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
