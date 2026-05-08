@php
    $pusherBroadcastConfig = config('broadcasting.connections.pusher', []);
    $realtimeEnabled = filled($pusherBroadcastConfig['key'] ?? null) && filled($pusherBroadcastConfig['app_id'] ?? null);
    $authUser = auth()->user();
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
        x-data="{ volume: 1.0, ...window.groupConversationVideoCall({
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
        }) }"
        x-init="$el.__groupConversationVideoCall = $data;
        init();
        if (@js($incomingCallId) !== null) {
            window.setTimeout(() => $dispatch('group-call-join', { callId: @js($incomingCallId) }));
        }"
        x-on:beforeunload.window="disposeOnLeave()"
        x-on:livewire:navigating.window="disposeOnLeave()"
        x-on:group-call-start.window="startCall()"
        class="contents"
    >
        <div
            x-cloak
            x-show="callStatus === 'ringing'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="-translate-y-4 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="-translate-y-4 opacity-0"
            class="fixed left-1/2 top-4 z-[60] w-[min(30rem,calc(100vw-2rem))] -translate-x-1/2"
        >
            <section class="rounded-2xl border border-[var(--brand-200)] bg-white p-4 shadow-2xl shadow-stone-950/20 dark:border-[var(--brand-500)]/20 dark:bg-neutral-900 dark:shadow-black/40">
                <div class="flex items-start gap-4">
                    <span class="mt-1 flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[var(--brand-100)] text-[var(--brand-700)] dark:bg-[color:color-mix(in_oklab,var(--brand-500),transparent_82%)] dark:text-[var(--brand-300)]">
                        <span class="h-3 w-3 animate-pulse rounded-full bg-[var(--brand-600)]"></span>
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-neutral-500">
                            {{ __('Incoming group call') }}
                        </p>
                        <h2 class="mt-1 truncate text-base font-semibold text-neutral-900 dark:text-white" x-text="statusMessage"></h2>
                        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ $groupDisplayName }}</p>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <button type="button" x-on:click="acceptCall()" class="brand-button-primary px-4 py-2">
                                <flux:icon.phone variant="micro" />
                                {{ __('Accept') }}
                            </button>
                            <button type="button" x-on:click="declineGroupCall()" class="brand-button-secondary px-4 py-2">
                                <flux:icon.phone-x-mark variant="micro" />
                                {{ __('Decline') }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div wire:ignore x-cloak x-show="callStatus === 'active' || callStatus === 'connecting' || callStatus === 'ended'" x-transition.opacity
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
                    </div>
                </header>

                <video id="group-call-local-background-video" autoplay muted playsinline
                    x-cloak x-show="remoteParticipants.length === 0"
                    x-bind:class="cameraDisabled ? 'opacity-0' : 'opacity-100'"
                    class="absolute inset-0 h-full w-full scale-x-[-1] bg-neutral-950 object-cover transition-opacity duration-200"></video>
                <div class="absolute inset-0 bg-black/45"></div>

                <div class="absolute inset-0 z-10"
                    x-data="{
                        isMobileViewport: window.innerWidth < 1024,
                        viewportWidth: window.innerWidth,
                        viewportHeight: window.innerHeight,
                        updateViewport() {
                            this.isMobileViewport = window.innerWidth < 1024;
                            this.viewportWidth = window.innerWidth;
                            this.viewportHeight = window.innerHeight;
                        },
                    }"
                    x-init="updateViewport(); window.addEventListener('resize', () => { updateViewport(); })"
                >
                    <div x-cloak x-show="! isMobileViewport" class="h-full w-full" x-bind:style="gridStyle(remoteParticipants.length, viewportWidth, viewportHeight)">
                        <div class="relative overflow-hidden bg-neutral-900" x-cloak x-show="remoteParticipants.length > 0">
                            <video id="group-call-local-grid-video" autoplay muted playsinline
                                x-bind:class="cameraDisabled ? 'opacity-0' : 'opacity-100'"
                                class="relative z-10 h-full w-full scale-x-[-1] object-cover transition-opacity duration-200"></video>
                            <div
                                x-cloak
                                x-show="cameraDisabled"
                                class="absolute inset-0 flex flex-col items-center justify-center transition-opacity duration-200"
                                style="background-color: var(--brand-700);"
                            >
                                @if ($authUser?->profile_image)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($authUser->profile_image) }}"
                                        alt="{{ $authUser->name }}"
                                        class="h-16 w-16 rounded-full object-cover ring-2 ring-white/30">
                                @else
                                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-white/20 text-xl font-bold text-white">
                                        {{ $authUser?->initials() ?? '?' }}
                                    </span>
                                @endif
                                <span class="mt-2 text-xs font-semibold text-white/70">{{ __('Camera off') }}</span>
                            </div>
                            <span class="absolute bottom-2 left-2 z-20 rounded-md bg-black/50 px-1.5 py-0.5 text-xs font-semibold text-white">{{ __(':name (You)', ['name' => $authUser?->name]) }}</span>
                        </div>

                        <template x-for="participant in remoteParticipants" :key="participant.id">
                            <div class="relative overflow-hidden bg-neutral-900">
                                <div class="absolute inset-0 z-0 flex flex-col items-center justify-center bg-neutral-800">
                                    <span class="flex h-16 w-16 items-center justify-center rounded-full text-xl font-bold text-white"
                                        style="background-color: var(--brand-600);"
                                        x-text="participantInitials(participant.id)"></span>
                                    <span class="mt-2 text-xs text-white/60" x-text="participant.name"></span>
                                </div>
                                <video wire:ignore autoplay playsinline
                                    x-bind:id="participant.tileElementId"
                                    x-effect="$el.volume = Number(volume);"
                                    x-bind:class="remoteVideoActive.get(participant.id) ? 'opacity-100' : 'opacity-0'"
                                    class="relative z-10 h-full w-full object-cover transition-opacity duration-300"></video>
                                <span class="absolute bottom-2 left-2 z-20 rounded-md bg-black/50 px-1.5 py-0.5 text-xs font-semibold text-white" x-text="participant.name"></span>
                            </div>
                        </template>

                        <div class="flex flex-col items-center justify-center bg-neutral-950 px-8 text-center" x-cloak x-show="remoteParticipants.length === 0">
                            <div class="relative flex h-24 w-24 items-center justify-center">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full border-2 border-green-500/50"></span>
                                <span class="relative flex h-20 w-20 items-center justify-center rounded-full bg-green-600 text-2xl font-bold text-white">{{ auth()->user()->initials() }}</span>
                            </div>
                            <p class="mt-4 text-sm text-white/60">{{ __('Waiting for others to join...') }}</p>
                        </div>
                    </div>

                    <div x-cloak x-show="isMobileViewport" class="flex h-full w-full flex-col">
                        <div class="relative min-h-0 flex-1 overflow-hidden bg-neutral-900">
                            <div x-cloak x-show="remoteParticipants.length > 0" class="absolute inset-0 z-0 flex flex-col items-center justify-center bg-neutral-800">
                                <span class="flex h-20 w-20 items-center justify-center rounded-full text-2xl font-bold text-white"
                                    style="background-color: var(--brand-600);"
                                    x-text="speakerParticipant()?.initials ?? '?'"></span>
                                <span class="mt-2 text-sm text-white/60" x-text="speakerParticipant()?.name"></span>
                            </div>
                            <video wire:ignore id="group-call-speaker-video" autoplay playsinline
                                x-effect="$el.volume = Number(volume);"
                                x-cloak x-show="remoteParticipants.length > 0"
                                x-bind:class="speakerParticipant() && remoteVideoActive.get(speakerParticipant().id) ? 'opacity-100' : 'opacity-0'"
                                class="relative z-10 h-full w-full object-cover transition-opacity duration-300"></video>

                            <div class="flex h-full flex-col items-center justify-center bg-neutral-950 px-8 text-center" x-cloak x-show="remoteParticipants.length === 0">
                                <div class="relative flex h-24 w-24 items-center justify-center">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full border-2 border-green-500/50"></span>
                                    <span class="relative flex h-20 w-20 items-center justify-center rounded-full bg-green-600 text-2xl font-bold text-white">{{ auth()->user()->initials() }}</span>
                                </div>
                                <p class="mt-4 text-sm text-white/60">{{ __('Waiting for others to join...') }}</p>
                            </div>

                            <span x-cloak x-show="remoteParticipants.length > 0" class="absolute bottom-56 left-3 rounded-md bg-black/50 px-1.5 py-0.5 text-xs font-semibold text-white" x-text="speakerParticipant()?.name"></span>
                        </div>

                        <div x-cloak x-show="remoteParticipants.length > 0" class="absolute bottom-24 left-0 right-0 z-20 overflow-x-auto px-3">
                            <div class="flex w-max gap-2">
                                <div class="relative h-28 w-20 shrink-0 overflow-hidden rounded-xl border border-white/20 bg-neutral-900 shadow-lg">
                                    <video id="group-call-local-thumbnail-video" autoplay muted playsinline
                                        x-bind:class="cameraDisabled ? 'opacity-0' : 'opacity-100'"
                                        class="relative z-10 h-full w-full scale-x-[-1] object-cover transition-opacity duration-200"></video>
                                    <div
                                        x-cloak
                                        x-show="cameraDisabled"
                                        class="absolute inset-0 flex flex-col items-center justify-center transition-opacity duration-200"
                                        style="background-color: var(--brand-700);"
                                    >
                                        @if ($authUser?->profile_image)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($authUser->profile_image) }}"
                                                alt="{{ $authUser->name }}"
                                                class="h-10 w-10 rounded-full object-cover ring-2 ring-white/30">
                                        @else
                                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white/20 text-sm font-bold text-white">
                                                {{ $authUser?->initials() ?? '?' }}
                                            </span>
                                        @endif
                                        <span class="mt-1 text-[9px] font-semibold text-white/70">{{ __('Camera off') }}</span>
                                    </div>
                                    <span class="absolute bottom-1 left-1 right-1 z-20 truncate rounded bg-black/50 px-1 py-0.5 text-left text-[10px] font-semibold text-white">{{ __('You') }}</span>
                                </div>

                                <template x-for="participant in thumbnailParticipants()" :key="participant.id">
                                    <button type="button" x-on:click="selectSpeaker(participant.id)" class="relative h-28 w-20 shrink-0 overflow-hidden rounded-xl border border-white/20 bg-neutral-900 shadow-lg">
                                        <div class="absolute inset-0 z-0 flex flex-col items-center justify-center bg-neutral-800">
                                            <span class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold text-white"
                                                style="background-color: var(--brand-600);"
                                                x-text="participantInitials(participant.id)"></span>
                                        </div>
                                        <video wire:ignore autoplay playsinline
                                            x-bind:id="participant.thumbnailElementId"
                                            x-effect="$el.volume = Number(volume);"
                                            x-bind:class="remoteVideoActive.get(participant.id) ? 'opacity-100' : 'opacity-0'"
                                            class="relative z-10 h-full w-full object-cover transition-opacity duration-300"></video>
                                        <span class="absolute bottom-1 left-1 right-1 z-20 truncate rounded bg-black/50 px-1 py-0.5 text-left text-[10px] font-semibold text-white" x-text="participant.name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-cloak x-show="callStatus !== 'idle' && remoteParticipants.length === 0" class="absolute z-30 h-36 w-28 touch-none overflow-hidden rounded-2xl border-2 border-white/30 bg-neutral-950 shadow-xl"
                    x-bind:style="callPreviewStyle()" x-on:mousedown.prevent="startPreviewDrag($event)" x-on:touchstart.prevent="startPreviewDrag($event)">
                    <video id="group-call-local-video" autoplay muted playsinline
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
                        <button type="button" x-on:click="switchCamera()"
                            x-cloak x-show="hasMultipleCameras && (callStatus === 'active' || callStatus === 'connecting')"
                            x-bind:class="'bg-neutral-200 text-neutral-700 hover:bg-neutral-300 dark:bg-white/15 dark:text-white dark:hover:bg-white/20'"
                            class="flex h-12 w-12 items-center justify-center rounded-full transition"
                            aria-label="{{ __('Switch camera') }}">
                            <i class="fa-solid fa-camera-rotate text-sm"></i>
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
                            x-show="callStatus === 'active' && remoteParticipants.length > 0 && isPipSupported()"
                            x-on:click="enterPip()"
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-neutral-200 text-neutral-700 transition hover:bg-neutral-300 dark:bg-white/15 dark:text-white dark:hover:bg-white/20"
                            title="{{ __('Picture in picture') }}"
                            aria-label="{{ __('Picture in picture') }}">
                            <flux:icon.squares-2x2 variant="mini" />
                        </button>
                        <button type="button" x-on:click="leaveCall()" class="flex h-16 w-16 items-center justify-center rounded-full bg-red-500 text-white transition hover:scale-105 hover:bg-red-600" aria-label="{{ __('End call') }}">
                            <flux:icon.phone-x-mark variant="solid" />
                        </button>
                    </div>
                </div>

                <div x-cloak x-show="callStatus === 'ended'"
                    class="absolute inset-0 z-50 flex flex-col items-center justify-center bg-neutral-950/90 text-white">
                    <flux:icon.phone-x-mark variant="solid" class="h-16 w-16 text-red-500" />
                    <p class="mt-4 text-xl font-semibold" x-text="statusMessage || @js(__('Call ended.'))"></p>
                    <p class="mt-2 text-sm text-white/60">{{ __('Returning to chat...') }}</p>
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

                            <div class="flex h-11 shrink-0 items-center">
                                @foreach ($this->members->take(3) as $index => $member)
                                    <x-user-avatar :user="$member->user" size="sm" @class([
                                        'border-2 border-white dark:border-neutral-900 shrink-0',
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
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white"
                                title="{{ __('Toggle group info') }}"
                                aria-label="{{ __('Toggle group info') }}"
                            >
                                <flux:icon.information-circle variant="mini" />
                            </button>
                        </div>
                    </header>

                    @php
                        $activeGroupCall = $this->activeGroupCall;
                    @endphp
                    @if ($activeGroupCall !== null && $activeGroupCall->caller_id !== auth()->id())
                        <div wire:poll.10s class="shrink-0 border-b border-[var(--brand-200)] bg-[var(--brand-50)] px-4 py-2 dark:border-[var(--brand-500)]/20 dark:bg-[var(--brand-500)]/10">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex h-2 w-2 animate-pulse rounded-full bg-[var(--brand-600)]"></span>
                                    <p class="text-sm font-semibold text-[var(--brand-700)] dark:text-[var(--brand-300)]">
                                        {{ __('A group call is in progress') }}
                                    </p>
                                </div>
                                <button type="button" x-on:click="$dispatch('group-call-join', { callId: {{ $activeGroupCall->id }} })"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-[var(--brand-600)] px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-[var(--brand-700)]">
                                    <flux:icon.video-camera variant="micro" />
                                    {{ __('Join call') }}
                                </button>
                            </div>
                        </div>
                    @endif

                    <div class="scrollbar-none min-h-0 flex-1 overflow-y-auto px-3 py-4 sm:px-5" x-data x-init="$el.scrollTop = $el.scrollHeight" @group-message-sent.window="$nextTick(() => { $el.scrollTop = $el.scrollHeight })">
                        @forelse ($this->threadMessages as $message)
                            @php
                                $isOwnMessage = $message->sender_id === auth()->id();
                                $previousMessage = $this->threadMessages->get($loop->index - 1);
                                $nextMessage = $this->threadMessages->get($loop->index + 1);
                                $startsNewDate = ! $previousMessage || ! $message->created_at?->isSameDay($previousMessage->created_at);
                                $isGroupedWithPrevious = $previousMessage
                                    && ! $message->is_system_message
                                    && ! $previousMessage->is_system_message
                                    && $previousMessage->sender_id === $message->sender_id
                                    && $message->created_at?->isSameDay($previousMessage->created_at);
                                $isGroupedWithNext = $nextMessage
                                    && ! $message->is_system_message
                                    && ! $nextMessage->is_system_message
                                    && $nextMessage->sender_id === $message->sender_id
                                    && $message->created_at?->isSameDay($nextMessage->created_at);
                                $showSenderLabel = ! $isOwnMessage && ! $isGroupedWithPrevious;
                                $showSenderAvatar = ! $isOwnMessage && ! $isGroupedWithNext;
                            @endphp

                            @if ($startsNewDate)
                                <div wire:key="group-message-date-{{ $message->created_at?->toDateString() ?? $message->id }}" class="my-4 flex justify-center">
                                    <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-medium text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
                                        {{ $this->messageDateLabel($message) }}
                                    </span>
                                </div>
                            @endif

                            @if ($message->is_system_message)
                                <div class="my-2 flex justify-center">
                                    <span class="inline-flex items-center gap-2 rounded-full bg-neutral-100 px-3 py-1.5 text-xs font-medium text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
                                        @if ($message->system_event === 'call_started')
                                            <flux:icon.video-camera variant="micro" class="h-3 w-3" />
                                        @elseif ($message->system_event === 'call_ended')
                                            <flux:icon.phone-x-mark variant="micro" class="h-3 w-3" />
                                        @endif
                                        {{ $message->content }}
                                    </span>
                                </div>
                            @else
                                <div wire:key="group-message-{{ $message->id }}" x-data="{ showActions: false, showTime: false }" x-on:mouseenter="showActions = true" x-on:mouseleave="showActions = false" x-on:click.stop="showTime = ! showTime" @class([
                                'relative mb-1 flex',
                                'mt-4' => ! $isGroupedWithPrevious,
                                'justify-end' => $isOwnMessage,
                                'justify-start' => ! $isOwnMessage,
                            ])>
                                <div @class([
                                    'flex max-w-[75%] gap-2',
                                    'items-start' => true,
                                    'flex-row-reverse' => $isOwnMessage,
                                ])>
                                    @unless ($isOwnMessage)
                                        @if ($showSenderAvatar)
                                            <x-user-avatar :user="$message->sender" size="xs" class="mt-0 shrink-0" />
                                        @else
                                            <span class="w-7 shrink-0"></span>
                                        @endif
                                    @endunless

                                    <div @class([
                                        'relative min-w-0 flex flex-col',
                                        'items-end' => $isOwnMessage,
                                        'items-start' => ! $isOwnMessage,
                                    ])>
                                        @if ($showSenderLabel)
                                            <p class="mb-1 px-1 text-xs font-semibold text-neutral-500 dark:text-neutral-400">{{ $this->memberDisplayName($message->sender) }}</p>
                                        @endif

                                        <div x-cloak x-show="showActions" @class([
                                            'absolute top-1/2 -translate-y-1/2 z-20 flex items-center gap-1',
                                            'left-0 -translate-x-full pr-1' => $isOwnMessage,
                                            'right-0 translate-x-full pl-1' => ! $isOwnMessage,
                                        ])>
                                            <div
                                                class="relative"
                                                x-on:click.stop="positionPicker($event)"
                                                x-data="{
                                                    open: false,
                                                    pickerStyle: '',
                                                    positionPicker(event) {
                                                        const rect = event.currentTarget.getBoundingClientRect();
                                                        this.pickerStyle = `left: ${rect.left + rect.width / 2}px; top: ${rect.top - 10}px; transform: translate(-50%, -100%);`;
                                                    },
                                                }"
                                            >
                                                <button type="button" x-on:click="open = !open" class="flex h-7 w-7 items-center justify-center rounded-full border border-stone-200 bg-white text-sm shadow-sm dark:border-white/10 dark:bg-zinc-800" aria-label="{{ __('React') }}">😊</button>
                                                <template x-teleport="body">
                                                    <div x-cloak x-show="open" x-on:click.outside="open = false" x-bind:style="pickerStyle" class="fixed z-[120] flex gap-1 rounded-full border border-stone-200 bg-white p-1 shadow-lg dark:border-white/10 dark:bg-zinc-800">
                                                    @foreach (['👍', '❤️', '😂', '😮', '😢', '🙏'] as $emoji)
                                                        <button type="button" wire:key="group-message-{{ $message->id }}-reaction-picker-{{ crc32($emoji) }}" wire:click="toggleReaction({{ $message->id }}, @js($emoji))" x-on:click="open = false" class="text-lg transition-transform hover:scale-125" aria-label="{{ __('React with :emoji', ['emoji' => $emoji]) }}">{{ $emoji }}</button>
                                                    @endforeach
                                                    </div>
                                                </template>
                                            </div>
                                            <button type="button" wire:click="setReplyTo({{ $message->id }})" class="flex h-7 w-7 items-center justify-center rounded-full border border-stone-200 bg-white text-neutral-500 shadow-sm transition hover:text-neutral-900 dark:border-white/10 dark:bg-zinc-800 dark:hover:text-white" aria-label="{{ __('Reply') }}">
                                                <flux:icon.arrow-uturn-left variant="micro" class="h-3.5 w-3.5" />
                                            </button>
                                        </div>

                                        <div @class([
                                            'relative inline-block max-w-full',
                                            'mb-4' => $message->reactions->isNotEmpty(),
                                        ])>
                                            <div @class([
                                                'w-fit max-w-full overflow-hidden px-4 py-2 text-left text-sm leading-relaxed break-words',
                                                'rounded-2xl rounded-br-sm bg-[var(--brand-600)] text-white' => $isOwnMessage,
                                                'rounded-2xl rounded-bl-sm bg-neutral-100 text-neutral-900 dark:bg-neutral-800 dark:text-white' => ! $isOwnMessage,
                                            ])>
                                            @if ($message->replyTo)
                                                <div class="mb-1 rounded-lg border-l-2 border-current/40 bg-black/10 px-2 py-1 text-xs opacity-80">
                                                    <p class="font-semibold">{{ $this->memberDisplayName($message->replyTo->sender) }}</p>
                                                    <p class="truncate">{{ \Illuminate\Support\Str::limit($message->replyTo->content ?: __('Attachment'), 60) }}</p>
                                                </div>
                                            @endif

                                            @if (filled($message->content))
                                                <p class="break-words [overflow-wrap:anywhere]">{{ $message->content }}</p>
                                            @endif

                                            @php($attachments = $this->attachmentsForDisplay($message))

                                            @if ($attachments->isNotEmpty())
                                                <div class="{{ $attachments->count() > 1 ? 'mt-2 grid grid-cols-2 gap-2' : 'mt-2 grid gap-2' }}">
                                                    @foreach ($attachments as $attachment)
                                                        @php($attachmentUrl = $attachment->public_url)
                                                        @if (\Illuminate\Support\Str::startsWith($attachment->mime, 'image/'))
                                                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener noreferrer" class="block overflow-hidden rounded-2xl border {{ $isOwnMessage ? 'border-white/25' : 'border-neutral-200 dark:border-neutral-700' }}">
                                                                <img src="{{ $attachmentUrl }}" alt="{{ __('Attached image') }}" referrerpolicy="no-referrer" crossorigin="anonymous" onerror="this.onerror=null; this.src='https://placehold.co/320x320/1f1f1f/6b7280?text=Image+unavailable';" class="max-h-52 w-full object-cover" loading="lazy">
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

                                            @if ($message->reactions->isNotEmpty())
                                                <div class="absolute -bottom-3 left-2 flex items-center gap-0.5 rounded-full border border-stone-200 bg-white px-1.5 py-0.5 text-xs shadow-sm dark:border-white/10 dark:bg-zinc-800">
                                                    @foreach ($message->reactions->groupBy('emoji') as $emoji => $reactors)
                                                        <button type="button" wire:key="group-message-{{ $message->id }}-reaction-{{ crc32($emoji) }}" wire:click="toggleReaction({{ $message->id }}, @js($emoji))" class="inline-flex items-center gap-0.5" aria-label="{{ __('Toggle :emoji reaction', ['emoji' => $emoji]) }}">
                                                            <span>{{ $emoji }}</span>
                                                            @if ($reactors->count() > 1)
                                                                <span class="text-[10px] font-semibold text-neutral-500 dark:text-zinc-400">{{ $reactors->count() }}</span>
                                                            @endif
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif

                                            <div class="absolute -bottom-4 {{ $isOwnMessage ? 'right-0' : 'left-0' }} whitespace-nowrap">
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
                                                    {{ $this->messageTimestamp($message) }}
                                                </p>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                                </div>
                            @endif
                        @empty
                            <div class="flex h-full min-h-80 items-center justify-center rounded-2xl border border-dashed border-neutral-200 px-6 py-12 text-center dark:border-neutral-800">
                                <div>
                                    <span class="brand-kicker">{{ __('No messages yet') }}</span>
                                    <p class="mt-4 max-w-md text-sm leading-7 text-neutral-500 dark:text-neutral-400">{{ __('Start the group conversation here.') }}</p>
                                </div>
                            </div>
                        @endforelse
                    </div>

                    @if ($replyingToId)
                        <div class="flex shrink-0 items-center justify-between gap-3 border-t border-neutral-200 bg-neutral-50 px-4 py-2 dark:border-neutral-800 dark:bg-neutral-900">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-[var(--brand-700)] dark:text-[var(--brand-400)]">{{ __('Replying to :name', ['name' => $replyingToSender]) }}</p>
                                <p class="truncate text-xs text-neutral-500 dark:text-zinc-400">{{ $replyingToContent }}</p>
                            </div>
                            <button type="button" wire:click="clearReply" class="text-neutral-400 transition hover:text-neutral-700 dark:hover:text-zinc-100" aria-label="{{ __('Cancel reply') }}">
                                <flux:icon.x-mark variant="micro" class="h-4 w-4" />
                            </button>
                        </div>
                    @endif

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
                        class="shrink-0 overflow-visible border-t border-neutral-200 bg-white px-3 py-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        <div class="flex items-center gap-2 relative" x-data="{ showEmoji: false }">
                            <button type="button" x-on:click="showEmoji = !showEmoji" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-800 dark:hover:text-white" aria-label="{{ __('Emoji') }}">
                                <flux:icon.face-smile variant="mini" />
                            </button>

                            <div x-cloak x-show="showEmoji" x-on:click.away="showEmoji = false" class="absolute bottom-12 left-0 z-50">
                                <emoji-picker class="dark" x-on:emoji-click="$wire.newMessage = ($wire.newMessage || '') + $event.detail.unicode; showEmoji = false;"></emoji-picker>
                            </div>

                            <label for="group-attachment" class="inline-flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-full text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-800 dark:hover:text-white" aria-label="{{ __('Attach file') }}">
                                <flux:icon.paper-clip variant="mini" />
                            </label>
                            <input id="group-attachment" x-ref="attachments" type="file" multiple wire:model="attachmentUploads" class="sr-only">

                            <div class="min-w-0 flex-1">
                                <flux:textarea wire:model="newMessage" :label="__('Message')" label:sr-only rows="1" :placeholder="__('Write a message...')" x-on:paste="handlePaste($event)" x-on:keydown.enter.prevent="if (($wire.newMessage || '').trim() || ($wire.attachmentUploads || []).length) $wire.send()" class="scrollbar-none resize-none overflow-hidden rounded-2xl bg-neutral-100 px-4 py-2 text-sm dark:bg-neutral-800" style="min-height: 2.5rem; max-height: 7.5rem; overflow-y: auto;" />
                            </div>

                            <button type="submit" x-bind:disabled="!($wire.newMessage || '').trim() && !($wire.attachmentUploads || []).length" wire:loading.attr="disabled" wire:target="send,attachmentUploads" x-bind:class="(($wire.newMessage || '').trim() || ($wire.attachmentUploads || []).length) ? 'bg-[var(--brand-600)] text-white shadow-sm hover:bg-[var(--brand-700)]' : 'bg-neutral-200 text-neutral-400 dark:bg-neutral-800 dark:text-neutral-500'" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full transition disabled:cursor-not-allowed" aria-label="{{ __('Send') }}">
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
                    class="brand-panel fixed bottom-0 right-0 top-[52px] z-[60] min-h-0 w-[min(24rem,calc(100vw-1rem))] overflow-hidden rounded-none p-0 shadow-2xl lg:static lg:z-auto lg:block lg:w-auto lg:rounded-none lg:shadow-none"
                >
                    <div class="h-full min-h-0 overflow-y-auto p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <span class="inline-flex self-start border border-stone-200 bg-stone-50 px-3.5 py-1 text-[10px] font-semibold uppercase tracking-[0.22em] text-neutral-500 dark:border-white/10 dark:bg-zinc-800 dark:text-zinc-400">{{ __('Group') }}</span>
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
                    </div>
                </aside>
            </div>
        </div>
    </div>
</div>
