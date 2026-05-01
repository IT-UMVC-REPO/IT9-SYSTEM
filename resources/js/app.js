window.global = window.global ?? window;

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (pusherKey) {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        forceTLS: true,
    });
} else if (reverbKey) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}

const videoCallResetDelay = 1800;
const videoCallConnectingWarningDelay = 10000;
const videoCallConnectingTimeout = 30000;
const localVideoElementId = 'conversation-call-local-video';
const remoteVideoElementId = 'conversation-call-remote-video';
const videoCallIceServers = [
    { urls: ['stun:stun.l.google.com:19302'] },
    { urls: ['stun:stun1.l.google.com:19302'] },
    { urls: ['stun:stun2.l.google.com:19302'] },
    { urls: ['stun:stun3.l.google.com:19302'] },
];

/**
 * Strip large/unnecessary SDP lines to keep signal payloads small enough
 * for Pusher's free-tier 10KB message limit.
 */
function stripSdp(sdp) {
    return sdp.split('\n')
        .filter(line => !line.startsWith('a=ssrc'))
        .join('\n');
}

window.conversationVideoCall = (config) => ({
    authUserId: config.authUserId,
    conversationKey: config.conversationKey,
    otherUserId: config.otherUserId,
    otherUserName: config.otherUserName,
    realtimeEnabled: config.realtimeEnabled,
    routes: config.routes,
    callStatus: 'idle',
    callId: null,
    peer: null,
    localStream: null,
    pendingSignals: [],
    iceServers: null,
    iceTransportPolicy: 'all',
    connectingWarningTimer: null,
    connectingTimeoutTimer: null,
    statusMessage: '',
    initialized: false,

    init() {
        if (this.initialized || !this.realtimeEnabled || !window.Echo) {
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
                    this.callStatus = 'connecting';
                    this.statusMessage = `${this.otherUserName} accepted. Connecting media...`;
                    this.startConnectionTimers();
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
            this.realtimeEnabled
            && window.Echo
            && navigator.mediaDevices
            && navigator.mediaDevices.getUserMedia,
        );
    },

    videoCallDisabledReason() {
        if (!this.realtimeEnabled) {
            return 'Real-time calling is not configured for this app.';
        }

        if (!window.Echo) {
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
                timeoutMs: 15000,
                unavailableMessage: 'Could not reach the call server. Make sure the app server is running.',
            });

            this.callId = payload.id;
            this.callStatus = 'calling';
            this.statusMessage = 'Preparing your camera...';

            await this.ensureLocalStream();
            this.statusMessage = 'Loading call connection settings...';
            await this.loadIceConfiguration();
            this.statusMessage = `Calling ${this.otherUserName}...`;

            this.initPeer(true);
            this.flushPendingSignals();
        } catch (error) {
            if (this.callId !== null) {
                try {
                    await this.requestJson(this.callRoute('end'));
                } catch {
                    // Ignore cleanup failures.
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
            this.statusMessage = 'Loading call connection settings...';
            await this.loadIceConfiguration();

            this.callStatus = 'connecting';
            this.statusMessage = `Accepted. Connecting media with ${this.otherUserName}...`;

            this.initPeer(false);
            this.flushPendingSignals();

            await this.requestJson(this.callRoute('answer'), null, { timeoutMs: 15000 });
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
                await this.requestJson(this.callRoute('end'), null, { timeoutMs: 15000 });
            } catch {
                // Ignore server cleanup failures.
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

    async loadIceConfiguration() {
        if (this.iceServers !== null) {
            return;
        }

        const payload = await this.requestJson(this.routes.iceServers, null, {
            method: 'GET',
            timeoutMs: 15000,
            unavailableMessage: 'Could not load call connection settings.',
        });

        this.iceServers = Array.isArray(payload?.ice_servers) && payload.ice_servers.length > 0
            ? payload.ice_servers
            : videoCallIceServers;
        this.iceTransportPolicy = payload?.ice_transport_policy === 'relay' ? 'relay' : 'all';
    },

    initPeer(initiator) {
        if (this.peer !== null || this.localStream === null) {
            return;
        }

        const peer = new RTCPeerConnection({
            iceServers: this.iceServers ?? videoCallIceServers,
            iceTransportPolicy: this.iceTransportPolicy,
        });

        this.peer = peer;
        this.startConnectionTimers();

        this.localStream.getTracks().forEach((track) => {
            peer.addTrack(track, this.localStream);
        });

        peer.onicecandidate = async ({ candidate }) => {
            if (!candidate || !this.callId || this.peer !== peer) {
                return;
            }

            console.log('ICE candidate size:', new Blob([JSON.stringify({ candidate: candidate.toJSON() })]).size, 'bytes');

            try {
                await this.requestJson(this.callRoute('signal'), {
                    signal_data: {
                        type: 'candidate',
                        candidate: candidate.toJSON(),
                    },
                }, { timeoutMs: 15000 });
            } catch (error) {
                if (this.peer === peer) {
                    this.cleanupCall('ended', this.callErrorMessage(error, 'Unable to sync the call.'));
                }
            }
        };

        peer.ontrack = (event) => {
            if (this.peer !== peer || !event.streams[0]) {
                return;
            }

            this.setVideoSource(remoteVideoElementId, event.streams[0]);
            this.markPeerConnected();
        };

        peer.oniceconnectionstatechange = () => {
            if (this.peer !== peer) {
                return;
            }

            if (peer.iceConnectionState === 'checking' && this.callStatus === 'connecting') {
                this.statusMessage = `Connecting media with ${this.otherUserName}...`;
            }

            if (peer.iceConnectionState === 'connected' || peer.iceConnectionState === 'completed') {
                this.markPeerConnected();
            }

            if (peer.iceConnectionState === 'failed' || peer.iceConnectionState === 'disconnected') {
                void this.endCall(this.connectionFailureMessage());
            }
        };

        if (initiator) {
            peer.createOffer({ offerToReceiveAudio: true, offerToReceiveVideo: true })
                .then((offer) => peer.setLocalDescription(offer).then(() => offer))
                .then((offer) => {
                    if (this.peer !== peer || !this.callId) {
                        return null;
                    }

                    const strippedSdp = stripSdp(offer.sdp);
                    console.log('Outgoing offer SDP size:', new Blob([JSON.stringify({ type: offer.type, sdp: strippedSdp })]).size, 'bytes');

                    return this.requestJson(this.callRoute('signal'), {
                        signal_data: { type: offer.type, sdp: strippedSdp },
                    }, { timeoutMs: 15000 });
                })
                .catch((error) => {
                    if (this.peer === peer) {
                        void this.endCall(this.callErrorMessage(error, 'Could not create call offer.'));
                    }
                });
        }
    },

    markPeerConnected() {
        this.clearConnectionTimers();
        this.callStatus = 'active';
        this.statusMessage = 'Connected.';
    },

    startConnectionTimers() {
        this.clearConnectionTimers();

        this.connectingWarningTimer = window.setTimeout(() => {
            if (this.callStatus !== 'connecting') {
                return;
            }

            this.statusMessage = this.usesTurnServers()
                ? 'Still connecting media. Please keep this window open...'
                : 'Still connecting media. Calls across different networks need TURN credentials in .env.';
        }, videoCallConnectingWarningDelay);

        this.connectingTimeoutTimer = window.setTimeout(() => {
            if (this.callStatus !== 'connecting') {
                return;
            }

            void this.endCall(this.connectionFailureMessage());
        }, videoCallConnectingTimeout);
    },

    clearConnectionTimers() {
        if (this.connectingWarningTimer !== null) {
            window.clearTimeout(this.connectingWarningTimer);
        }

        if (this.connectingTimeoutTimer !== null) {
            window.clearTimeout(this.connectingTimeoutTimer);
        }

        this.connectingWarningTimer = null;
        this.connectingTimeoutTimer = null;
    },

    usesTurnServers() {
        return (this.iceServers ?? []).some((server) => {
            const urls = Array.isArray(server.urls) ? server.urls : [server.urls];

            return urls.some((url) => typeof url === 'string' && url.startsWith('turn'));
        });
    },

    connectionFailureMessage() {
        if (!this.usesTurnServers()) {
            return 'Could not establish the media connection. Add TURN credentials in .env for calls across different networks.';
        }

        return 'Could not establish the media connection. Please check your network and try again.';
    },

    handleIncomingSignal(signalData) {
        if (!signalData || typeof signalData !== 'object') {
            return;
        }

        if (this.peer === null) {
            this.pendingSignals.push(signalData);
            return;
        }

        const peer = this.peer;

        if (signalData.type === 'offer') {
            console.log('Incoming offer SDP size:', new Blob([JSON.stringify(signalData)]).size, 'bytes');

            void (async () => {
                try {
                    await peer.setRemoteDescription(new RTCSessionDescription(signalData));

                    if (this.peer !== peer) {
                        return;
                    }

                    const answer = await peer.createAnswer();
                    await peer.setLocalDescription(answer);

                    if (this.peer !== peer || !this.callId) {
                        return;
                    }

                    const strippedSdp = stripSdp(answer.sdp);
                    console.log('Outgoing answer SDP size:', new Blob([JSON.stringify({ type: answer.type, sdp: strippedSdp })]).size, 'bytes');

                    await this.requestJson(this.callRoute('signal'), {
                        signal_data: { type: answer.type, sdp: strippedSdp },
                    }, { timeoutMs: 15000 });

                    this.flushPendingSignals();
                } catch (error) {
                    if (this.peer === peer) {
                        void this.endCall(this.callErrorMessage(error, 'Could not accept call offer.'));
                    }
                }
            })();

            return;
        }

        if (signalData.type === 'answer') {
            console.log('Incoming answer SDP size:', new Blob([JSON.stringify(signalData)]).size, 'bytes');

            void (async () => {
                try {
                    await peer.setRemoteDescription(new RTCSessionDescription(signalData));

                    if (this.peer === peer) {
                        this.flushPendingSignals();
                    }
                } catch (error) {
                    if (this.peer === peer) {
                        void this.endCall(this.callErrorMessage(error, 'Could not process call answer.'));
                    }
                }
            })();

            return;
        }

        if (signalData.candidate) {
            if (!peer.remoteDescription) {
                this.pendingSignals.push(signalData);
                return;
            }

            const candidate = signalData.type === 'candidate'
                ? signalData.candidate
                : signalData;

            peer.addIceCandidate(new RTCIceCandidate(candidate)).catch(() => { });
        }
    },

    flushPendingSignals() {
        while (this.peer !== null && this.pendingSignals.length > 0) {
            let signalData = this.pendingSignals[0];
            let isSessionDescription = signalData?.type === 'offer' || signalData?.type === 'answer';

            if (!this.peer.remoteDescription && !isSessionDescription) {
                const sessionDescriptionIndex = this.pendingSignals.findIndex((pendingSignal) => (
                    pendingSignal?.type === 'offer' || pendingSignal?.type === 'answer'
                ));

                if (sessionDescriptionIndex === -1) {
                    return;
                }

                [signalData] = this.pendingSignals.splice(sessionDescriptionIndex, 1);
                isSessionDescription = true;
            } else {
                this.pendingSignals.shift();
            }

            if (signalData !== undefined) {
                this.handleIncomingSignal(signalData);
            }

            if (isSessionDescription) {
                return;
            }
        }
    },

    async ensureLocalStream() {
        if (this.localStream !== null) {
            return this.localStream;
        }

        let stream;

        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        } catch (error) {
            if (error instanceof DOMException && error.name === 'NotAllowedError') {
                throw error;
            }

            stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
        }

        this.localStream = stream;
        this.setVideoSource(localVideoElementId, stream);

        return stream;
    },

    cleanupCall(nextStatus, message = '') {
        this.clearConnectionTimers();

        const peer = this.peer;
        this.peer = null;

        if (peer !== null) {
            peer.close();
        }

        if (this.localStream !== null) {
            this.localStream.getTracks().forEach((track) => track.stop());
        }

        this.localStream = null;
        this.pendingSignals = [];
        this.iceServers = null;
        this.iceTransportPolicy = 'all';
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
            method = 'POST',
            timeoutMs = 5000,
            unavailableMessage = 'Call server unavailable. Please try again later.',
        } = options;

        let response;

        try {
            response = await fetch(url, {
                method,
                headers: {
                    ...(body === null ? {} : { 'Content-Type': 'application/json' }),
                    ...(method === 'GET' ? {} : { 'X-CSRF-TOKEN': this.csrfToken() }),
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
            return 'Camera or microphone access was denied. Please allow access in your browser settings and try again.';
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
        );
    },

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

import './echo';