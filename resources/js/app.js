import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import SimplePeer from 'simple-peer';

window.Pusher = Pusher;
window.SimplePeer = SimplePeer;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

const videoCallResetDelay = 1800;
const localVideoElementId = 'conversation-call-local-video';
const remoteVideoElementId = 'conversation-call-remote-video';

window.conversationVideoCall = (config) => ({
    authUserId: config.authUserId,
    conversationKey: config.conversationKey,
    otherUserId: config.otherUserId,
    otherUserName: config.otherUserName,
    reverbEnabled: config.reverbEnabled,
    routes: config.routes,
    callStatus: 'idle',
    callId: null,
    peer: null,
    localStream: null,
    pendingSignals: [],
    statusMessage: '',
    initialized: false,

    init() {
        if (this.initialized || !this.reverbEnabled || !window.Echo) {
            return;
        }

        this.initialized = true;

        window.Echo.private(`messaging.${this.conversationKey}`)
            .listen('.VideoCallInitiated', (event) => {
                if (event.caller_id === this.authUserId || this.callStatus !== 'idle') {
                    return;
                }

                this.callId = event.call_id;
                this.callStatus = 'incoming';
                this.statusMessage = `${event.caller_name} is calling...`;
            })
            .listen('.VideoCallSignal', (event) => {
                if (event.sender_id === this.authUserId) {
                    return;
                }

                if (this.callId !== null && event.call_id !== this.callId) {
                    return;
                }

                this.callId ??= event.call_id;
                this.handleIncomingSignal(event.signal_data);
            })
            .listen('.VideoCallStatusChanged', (event) => {
                if (this.callId !== null && event.call_id !== this.callId) {
                    return;
                }

                if (event.status === 'active' && this.callStatus === 'calling') {
                    this.statusMessage = `${this.otherUserName} joined the call.`;

                    return;
                }

                if (event.status === 'declined') {
                    this.cleanupCall('ended', `${this.otherUserName} declined the call.`);

                    return;
                }

                if (event.status === 'ended') {
                    this.cleanupCall('ended', 'Call ended.');
                }
            });
    },

    supportsVideoCalling() {
        return Boolean(
            this.reverbEnabled
            && window.Echo
            && window.SimplePeer
            && navigator.mediaDevices?.getUserMedia,
        );
    },

    isOverlayVisible() {
        return this.callStatus !== 'idle';
    },

    async startCall() {
        if (!this.supportsVideoCalling()) {
            this.cleanupCall('ended', 'Video calling is not available in this browser.');

            return;
        }

        try {
            await this.ensureLocalStream();

            const payload = await this.requestJson(this.routes.initiate, {
                receiver_id: this.otherUserId,
            });

            this.callId = payload.id;
            this.callStatus = 'calling';
            this.statusMessage = `Calling ${this.otherUserName}...`;

            this.initPeer(true);
        } catch {
            this.cleanupCall('ended', 'Unable to start the call.');
        }
    },

    async acceptCall() {
        if (!this.callId) {
            return;
        }

        try {
            await this.ensureLocalStream();

            this.callStatus = 'active';
            this.statusMessage = `Connecting to ${this.otherUserName}...`;

            this.initPeer(false);
            this.flushPendingSignals();

            await this.requestJson(this.callRoute('answer'));
        } catch {
            this.cleanupCall('ended', 'Unable to answer the call.');
        }
    },

    async declineCall() {
        if (!this.callId) {
            return;
        }

        try {
            await this.requestJson(this.callRoute('decline'));
        } catch {
            // Ignore server cleanup failures when declining.
        }

        this.cleanupCall('idle');
    },

    async endCall(message = 'Call ended.') {
        const activeCallId = this.callId;

        if (activeCallId !== null) {
            try {
                await this.requestJson(this.callRoute('end'));
            } catch {
                // Ignore server cleanup failures and still release local media.
            }
        }

        this.cleanupCall('ended', message);
    },

    disposeOnLeave() {
        if (this.localStream === null && this.peer === null) {
            return;
        }

        if (this.callId !== null) {
            void fetch(this.callRoute('end'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken(),
                },
                keepalive: true,
            });
        }

        this.cleanupCall('idle');
    },

    initPeer(initiator) {
        if (this.peer !== null || this.localStream === null) {
            return;
        }

        const peer = new window.SimplePeer({
            initiator,
            stream: this.localStream,
            trickle: true,
        });

        this.peer = peer;

        peer.on('signal', async (signalData) => {
            if (!this.callId) {
                return;
            }

            try {
                await this.requestJson(this.callRoute('signal'), {
                    signal_data: signalData,
                });
            } catch {
                this.cleanupCall('ended', 'Unable to sync the call.');
            }
        });

        peer.on('connect', () => {
            this.callStatus = 'active';
            this.statusMessage = 'Connected.';
        });

        peer.on('stream', (remoteStream) => {
            this.setVideoSource(remoteVideoElementId, remoteStream);
            this.callStatus = 'active';
            this.statusMessage = 'Connected.';
        });

        peer.on('close', () => {
            if (this.peer !== peer) {
                return;
            }

            void this.endCall();
        });

        peer.on('error', () => {
            if (this.peer !== peer) {
                return;
            }

            void this.endCall('Connection lost.');
        });
    },

    handleIncomingSignal(signalData) {
        if (this.peer === null) {
            this.pendingSignals.push(signalData);

            return;
        }

        this.peer.signal(signalData);
    },

    flushPendingSignals() {
        while (this.peer !== null && this.pendingSignals.length > 0) {
            const signalData = this.pendingSignals.shift();

            if (signalData !== undefined) {
                this.peer.signal(signalData);
            }
        }
    },

    async ensureLocalStream() {
        if (this.localStream !== null) {
            return this.localStream;
        }

        const stream = await navigator.mediaDevices.getUserMedia({
            video: true,
            audio: true,
        });

        this.localStream = stream;
        this.setVideoSource(localVideoElementId, stream);

        return stream;
    },

    cleanupCall(nextStatus, message = '') {
        const peer = this.peer;

        this.peer = null;

        if (peer !== null) {
            peer.removeAllListeners();
            peer.destroy();
        }

        if (this.localStream !== null) {
            this.localStream.getTracks().forEach((track) => track.stop());
        }

        this.localStream = null;
        this.pendingSignals = [];
        this.setVideoSource(localVideoElementId, null);
        this.setVideoSource(remoteVideoElementId, null);

        this.callId = null;
        this.callStatus = nextStatus;
        this.statusMessage = message;

        if (nextStatus === 'ended') {
            window.setTimeout(() => {
                if (this.callStatus === 'ended') {
                    this.callStatus = 'idle';
                    this.statusMessage = '';
                }
            }, videoCallResetDelay);
        }

        if (nextStatus === 'idle') {
            this.statusMessage = '';
        }
    },

    callRoute(action) {
        return (this.routes[action] ?? '').replace('__CALL_ID__', String(this.callId ?? ''));
    },

    csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    },

    async requestJson(url, body = null) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                ...(body === null ? {} : { 'Content-Type': 'application/json' }),
                'X-CSRF-TOKEN': this.csrfToken(),
            },
            body: body === null ? null : JSON.stringify(body),
        });

        if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
        }

        const contentType = response.headers.get('content-type') ?? '';

        if (!contentType.includes('application/json')) {
            return null;
        }

        return await response.json();
    },

    setVideoSource(elementId, stream) {
        const element = document.getElementById(elementId);

        if (element instanceof HTMLVideoElement) {
            element.srcObject = stream;
        }
    },
});

document.addEventListener('brand-color-preview', (event) => {
    if (typeof event.detail !== 'string') {
        return;
    }

    document.documentElement.style.setProperty('--brand-preview', event.detail);
});

document.addEventListener('brand-color-persisted', () => {
    window.setTimeout(() => window.location.reload(), 1200);
});
