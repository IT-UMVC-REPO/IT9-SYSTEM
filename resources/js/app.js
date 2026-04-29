window.global = window.global ?? window;

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
const videoCallIceServers = [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' },
    { urls: 'stun:stun2.l.google.com:19302' },
    { urls: 'stun:stun3.l.google.com:19302' },
];

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
        window.__conversationVideoCallInstance = this;

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
            && navigator.mediaDevices
            && navigator.mediaDevices.getUserMedia,
        );
    },

    videoCallDisabledReason() {
        if (!this.reverbEnabled) {
            return 'Real-time features require the Reverb server to be running.';
        }

        if (!window.Echo || !window.SimplePeer) {
            return 'Video calling is still loading. Please try again.';
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            return 'Video calling is not available in this browser.';
        }

        return 'Video calling is not available right now.';
    },

    isOverlayVisible() {
        return this.callStatus !== 'idle';
    },

    async startCall() {
        if (!this.supportsVideoCalling()) {
            this.cleanupCall('ended', this.videoCallDisabledReason());

            return;
        }

        try {
            const payload = await this.requestJson(this.routes.initiate, {
                receiver_id: this.otherUserId,
            }, {
                timeoutMs: 5000,
                unavailableMessage: 'Could not reach the call server. Make sure the app server is running.',
            });

            this.callId = payload.id;
            this.callStatus = 'calling';
            this.statusMessage = 'Preparing your camera...';

            await this.ensureLocalStream();
            this.statusMessage = `Calling ${this.otherUserName}...`;

            this.initPeer(true);
            await new Promise((resolve) => window.setTimeout(resolve, 50));
            this.flushPendingSignals();
        } catch (error) {
            if (this.callId !== null) {
                try {
                    await this.requestJson(this.callRoute('end'));
                } catch {
                    // Ignore cleanup failures while already surfacing the original error.
                }
            }

            this.cleanupCall('ended', this.callErrorMessage(error, 'Could not start the call. Please check your connection.'));
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
        } catch (error) {
            this.cleanupCall('ended', this.callErrorMessage(error, 'Could not answer the call. Please check your connection.'));
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
            config: {
                iceServers: videoCallIceServers,
            },
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
            } catch (error) {
                this.cleanupCall('ended', this.callErrorMessage(error, 'Unable to sync the call.'));
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

        let stream;

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: true,
                audio: true,
            });
        } catch (error) {
            const message = error instanceof DOMException && error.name === 'NotAllowedError'
                ? 'Camera or microphone access was denied. Please allow access in your browser settings and try again.'
                : `Could not access camera or microphone: ${error instanceof Error && error.message ? error.message : 'Unknown browser error.'}`;

            this.cleanupCall('ended', message);

            throw new Error(message, { cause: error });
        }

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

    async requestJson(url, body = null, options = {}) {
        const {
            timeoutMs = 5000,
            unavailableMessage = 'Call server unavailable. Please try again later.',
        } = options;

        let response;

        try {
            response = await fetch(url, {
                method: 'POST',
                headers: {
                    ...(body === null ? {} : { 'Content-Type': 'application/json' }),
                    'X-CSRF-TOKEN': this.csrfToken(),
                },
                body: body === null ? null : JSON.stringify(body),
                signal: this.requestSignal(timeoutMs),
            });
        } catch (error) {
            this.statusMessage = unavailableMessage;

            throw new Error(unavailableMessage, { cause: error });
        }

        const contentType = response.headers.get('content-type') ?? '';
        const payload = contentType.includes('application/json')
            ? await response.json()
            : null;

        if (!response.ok) {
            const message = payload?.message ?? unavailableMessage;

            this.statusMessage = message;

            throw new Error(message);
        }

        if (payload?.realtime_available === false) {
            const message = payload.message ?? unavailableMessage;

            this.statusMessage = message;

            throw new Error(message);
        }

        return payload;
    },

    requestSignal(timeoutMs) {
        if (!timeoutMs) {
            return undefined;
        }

        if (typeof AbortSignal !== 'undefined' && typeof AbortSignal.timeout === 'function') {
            return AbortSignal.timeout(timeoutMs);
        }

        const controller = new AbortController();

        window.setTimeout(() => controller.abort(), timeoutMs);

        return controller.signal;
    },

    callErrorMessage(error, fallbackMessage) {
        if (error instanceof DOMException && error.name === 'NotAllowedError') {
            return 'Camera and microphone access is required to use video calling.';
        }

        if (error instanceof DOMException && error.name === 'NotFoundError') {
            return 'No camera or microphone was found for this device.';
        }

        if (error instanceof Error && error.message) {
            return error.message;
        }

        return fallbackMessage;
    },

    setVideoSource(elementId, stream) {
        const element = document.getElementById(elementId);

        if (element instanceof HTMLVideoElement) {
            element.srcObject = stream;
        }
    },
});

window.conversationVideoCallControl = () => ({
    manager() {
        return (
            this.$el.closest('[data-conversation-video-call]')?.__conversationVideoCall
            ?? window.__conversationVideoCallInstance
            ?? null
        );  },

    get callStatus() {
        return this.manager()?.callStatus ?? 'idle';
    },

    supportsVideoCalling() {
        return this.manager()?.supportsVideoCalling() ?? false;
    },

    videoCallDisabledReason() {
        return this.manager()?.videoCallDisabledReason() ?? 'Video calling is still loading. Please try again.';
    },

    startCall() {
        return this.manager()?.startCall();
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

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
