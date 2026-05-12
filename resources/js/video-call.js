export const videoCallResetDelay = 1800;
export const videoCallConnectingWarningDelay = 8000;
export const videoCallConnectingTimeout = 30000;
export const localVideoElementId = 'conversation-call-local-video';
export const localVideoBackgroundElementId = 'conversation-call-local-background-video';
export const remoteVideoElementId = 'conversation-call-remote-video';
export const videoCallIceServers = [
    { urls: ['stun:stun.l.google.com:19302'] },
    { urls: ['stun:stun1.l.google.com:19302'] },
    { urls: ['stun:stun2.l.google.com:19302'] },
    { urls: ['stun:stun3.l.google.com:19302'] },
];

export function peerConnectionOptions(iceServers, iceTransportPolicy = 'all') {
    return {
        iceServers: iceServers ?? videoCallIceServers,
        iceTransportPolicy,
        bundlePolicy: 'max-bundle',
        rtcpMuxPolicy: 'require',
        iceCandidatePoolSize: 4,
    };
}

export function waitForIceGathering(peer, timeoutMs = 1500) {
    return new Promise((resolve) => {
        if (peer.iceGatheringState === 'complete') {
            resolve();
            return;
        }

        const finish = () => {
            window.clearTimeout(timeout);
            peer.removeEventListener('icegatheringstatechange', handleStateChange);
            resolve();
        };
        const handleStateChange = () => {
            if (peer.iceGatheringState === 'complete') {
                finish();
            }
        };
        const timeout = window.setTimeout(finish, timeoutMs);

        peer.addEventListener('icegatheringstatechange', handleStateChange);
    });
}

/**
 * Strip large/unnecessary SDP lines to keep signal payloads small enough
 * for Pusher's free-tier 10KB message limit.
 *
 * IMPORTANT: When removing an a=rtpmap / a=fmtp line we must also remove its
 * payload type number from the m= line, otherwise the browser rejects the SDP.
 */
export function stripSdp(sdp) {
    if (!sdp) return sdp;

    // Normalize line endings: SDP spec requires CRLF, browsers may emit LF only.
    const lines = sdp.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');

    const removedPts = new Set();

    const filtered = lines.filter((line) => {
        // Drop all a=ssrc lines (they bloat the payload)
        if (line.startsWith('a=ssrc')) return false;

        // Drop a=rtpmap / a=fmtp / a=rtcp-fb lines for removed codec IDs
        const attrPt = line.match(/^a=(?:rtpmap|fmtp|rtcp-fb):(\d+)/);
        if (attrPt && removedPts.has(attrPt[1])) return false;

        return true;
    }).map((line) => {
        // Patch m= lines: remove the dead payload type numbers from the PT list.
        // e.g. "m=video 9 UDP/TLS/RTP/SAVPF 96 97 102 103" → drop 102, 103
        if (line.startsWith('m=') && removedPts.size > 0) {
            // m= format: "m=<media> <port> <proto> <pt1> <pt2> ..."
            return line.replace(/^(m=\S+ \S+ \S+)((?:\s+\d+)+)/, (_, header, pts) => {
                const kept = pts.trim().split(/\s+/).filter((pt) => !removedPts.has(pt));
                return header + ' ' + kept.join(' ');
            });
        }

        return line;
    });

    // Rejoin with \r\n per SDP spec and ensure a trailing \r\n
    return filtered.join('\r\n') + '\r\n';
}

export function preferCodecs(sdp, kind = 'video') {
    if (!sdp || kind !== 'video') return sdp;

    const lines = sdp.split('\r\n');
    const mLineIndex = lines.findIndex((line) => line.startsWith('m=video'));

    if (mLineIndex === -1) return sdp;

    const ptMap = {};
    lines.forEach((line) => {
        const match = line.match(/^a=rtpmap:(\d+) (VP8|VP9|H264)/i);

        if (match) {
            ptMap[match[2].toUpperCase()] = match[1];
        }
    });

    if (!ptMap.VP8) return sdp;

    const parts = lines[mLineIndex].split(' ');
    const header = parts.slice(0, 3);
    const pts = parts.slice(3);
    const vp8Pt = ptMap.VP8;

    lines[mLineIndex] = [...header, vp8Pt, ...pts.filter((pt) => pt !== vp8Pt)].join(' ');

    return lines.join('\r\n');
}

export const conversationVideoCall = (config) => ({
    authUserId: config.authUserId,
    conversationKey: config.conversationKey,
    otherUserId: config.otherUserId,
    otherUserName: config.otherUserName,
    otherUserInitials: config.otherUserInitials ?? '?',
    realtimeEnabled: config.realtimeEnabled,
    routes: config.routes,
    callStatus: 'idle',
    callId: null,
    peer: null,
    localStream: null,
    pendingSignals: [],
    iceServers: null,
    iceTransportPolicy: 'all',
    facingMode: 'user',
    hasMultipleCameras: false,
    hasCheckedCameraDevices: false,
    connectingWarningTimer: null,
    connectingTimeoutTimer: null,
    showTurnWarning: false,
    statusMessage: '',
    initialized: false,
    microphoneMuted: false,
    cameraDisabled: false,
    remoteVideoActive: false,
    callStartedAt: null,
    callDurationTimer: null,
    elapsedSeconds: 0,
    callChromeVisible: true,
    callChromeTimer: null,
    previewPosition: { right: 16, bottom: 96 },

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
                this.startCallTimer();
                this.showCallChrome();
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

    supportsCameraSwitch() {
        return this.hasMultipleCameras;
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

    showCallChrome() {
        this.callChromeVisible = true;

        if (this.callChromeTimer !== null) {
            window.clearTimeout(this.callChromeTimer);
        }

        this.callChromeTimer = window.setTimeout(() => {
            if (this.callStatus !== 'idle') {
                this.callChromeVisible = false;
            }
        }, 4000);
    },

    startCallTimer() {
        if (this.callDurationTimer !== null) {
            return;
        }

        this.callStartedAt ??= Date.now();
        this.elapsedSeconds = Math.floor((Date.now() - this.callStartedAt) / 1000);

        this.callDurationTimer = window.setInterval(() => {
            this.elapsedSeconds = Math.floor((Date.now() - this.callStartedAt) / 1000);
        }, 1000);
    },

    stopCallTimer() {
        if (this.callDurationTimer !== null) {
            window.clearInterval(this.callDurationTimer);
        }

        if (this.callChromeTimer !== null) {
            window.clearTimeout(this.callChromeTimer);
        }

        this.callDurationTimer = null;
        this.callChromeTimer = null;
        this.callStartedAt = null;
        this.elapsedSeconds = 0;
        this.callChromeVisible = true;
        this.previewPosition = { right: 16, bottom: 96 };
    },

    formattedCallDuration() {
        const minutes = String(Math.floor(this.elapsedSeconds / 60)).padStart(2, '0');
        const seconds = String(this.elapsedSeconds % 60).padStart(2, '0');

        return `${minutes}:${seconds}`;
    },

    callStatusLabel() {
        if (this.statusMessage && ['active', 'connecting'].includes(this.callStatus)) {
            return this.statusMessage;
        }

        if (this.callStatus === 'active') {
            return 'Connected';
        }

        if (this.callStatus === 'connecting') {
            return 'Connecting';
        }

        if (this.callStatus === 'incoming') {
            return 'Incoming call';
        }

        return 'Calling';
    },

    callPreviewStyle() {
        return `right: ${this.previewPosition.right}px; bottom: ${this.previewPosition.bottom}px;`;
    },

    startPreviewDrag(event) {
        const point = event.touches?.[0] ?? event;
        const startX = point.clientX;
        const startY = point.clientY;
        const startRight = this.previewPosition.right;
        const startBottom = this.previewPosition.bottom;

        const movePreview = (moveEvent) => {
            if (moveEvent.cancelable) {
                moveEvent.preventDefault();
            }

            const movePoint = moveEvent.touches?.[0] ?? moveEvent;
            const maxRight = Math.max(8, window.innerWidth - 128);
            const maxBottom = Math.max(72, window.innerHeight - 152);

            this.previewPosition = {
                right: Math.min(maxRight, Math.max(8, startRight - (movePoint.clientX - startX))),
                bottom: Math.min(maxBottom, Math.max(72, startBottom - (movePoint.clientY - startY))),
            };
        };

        const stopDrag = () => {
            window.removeEventListener('mousemove', movePreview);
            window.removeEventListener('mouseup', stopDrag);
            window.removeEventListener('touchmove', movePreview);
            window.removeEventListener('touchend', stopDrag);
        };

        window.addEventListener('mousemove', movePreview);
        window.addEventListener('mouseup', stopDrag, { once: true });
        window.addEventListener('touchmove', movePreview, { passive: false });
        window.addEventListener('touchend', stopDrag, { once: true });
    },

    toggleMicrophone() {
        this.microphoneMuted = !this.microphoneMuted;
        this.localStream?.getAudioTracks().forEach((track) => {
            track.enabled = !this.microphoneMuted;
        });
    },

    toggleCamera() {
        this.cameraDisabled = !this.cameraDisabled;
        this.localStream?.getVideoTracks().forEach((track) => {
            track.enabled = !this.cameraDisabled;
        });
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
                allowRealtimeUnavailable: true,
                timeoutMs: 15000,
                unavailableMessage: 'Could not reach the call server. Make sure the app server is running.',
            });

            this.callId = payload.id;
            this.callStatus = 'calling';
            this.statusMessage = 'Preparing your camera...';
            this.startCallTimer();
            this.showCallChrome();

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
            this.startCallTimer();
            this.showCallChrome();

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
        void this.exitPip();

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
            }).catch(() => {});
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

        const peer = new RTCPeerConnection(peerConnectionOptions(this.iceServers, this.iceTransportPolicy));

        this.peer = peer;
        this.startConnectionTimers();

        this.localStream.getTracks().forEach((track) => {
            peer.addTrack(track, this.localStream);
        });

        peer.ontrack = (event) => {
            if (this.peer !== peer) {
                return;
            }

            const incomingStream = (event.streams && event.streams.length > 0)
                ? event.streams[0]
                : (() => {
                    const existing = document.getElementById(remoteVideoElementId)?.srcObject;

                    if (existing instanceof MediaStream) {
                        existing.addTrack(event.track);
                        return existing;
                    }

                    return new MediaStream([event.track]);
                })();

            this.setVideoSource(remoteVideoElementId, incomingStream);

            if (event.track.kind === 'video') {
                this.remoteVideoActive = event.track.readyState === 'live' && !event.track.muted;
                event.track.addEventListener('mute', () => {
                    this.remoteVideoActive = false;
                });
                event.track.addEventListener('unmute', () => {
                    this.remoteVideoActive = true;
                });
                event.track.addEventListener('ended', () => {
                    this.remoteVideoActive = false;
                });
            }

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

            if (peer.iceConnectionState === 'disconnected') {
                this.statusMessage = 'Connection interrupted. Trying to recover...';

                if (peer.restartIce) {
                    peer.restartIce();
                }

                window.setTimeout(() => {
                    if (this.peer === peer && peer.iceConnectionState === 'disconnected') {
                        this.showTurnWarning = !this.usesTurnServers();
                        this.statusMessage = this.connectionFailureMessage();

                        if (peer.restartIce) {
                            peer.restartIce();
                        }
                    }
                }, 8000);
                return;
            }

            if (peer.iceConnectionState === 'failed') {
                this.showTurnWarning = !this.usesTurnServers();
                this.statusMessage = this.connectionFailureMessage();

                if (peer.restartIce) {
                    peer.restartIce();
                }
            }
        };

        if (initiator) {
            void (async () => {
                try {
                    const offer = await peer.createOffer({ offerToReceiveAudio: true, offerToReceiveVideo: true });
                    const preferredOffer = offer.sdp
                        ? { type: offer.type, sdp: this.preferCodecs(offer.sdp) }
                        : offer;

                    await peer.setLocalDescription(preferredOffer);
                    await waitForIceGathering(peer);

                    if (this.peer !== peer || !this.callId) {
                        return;
                    }

                    const localDescription = peer.localDescription ?? preferredOffer;
                    const strippedSdp = stripSdp(localDescription.sdp);

                    await this.requestJson(this.callRoute('signal'), {
                        signal_data: { type: localDescription.type, sdp: strippedSdp },
                    }, { timeoutMs: 15000 });
                } catch (error) {
                    if (this.peer === peer) {
                        void this.endCall(this.callErrorMessage(error, 'Could not create call offer.'));
                    }
                }
            })();
        }
    },

    markPeerConnected() {
        this.clearConnectionTimers();
        this.callStatus = 'active';
        this.statusMessage = 'Connected.';

        if (this.peer !== null) {
            void this.setMaxBitrate(this.peer);
        }
    },

    startConnectionTimers() {
        this.clearConnectionTimers();

        this.connectingWarningTimer = window.setTimeout(() => {
            if (this.callStatus !== 'connecting') {
                return;
            }

            this.showTurnWarning = !this.usesTurnServers();
            this.statusMessage = this.usesTurnServers()
                ? 'Still connecting media. Please keep this window open...'
                : 'Still connecting media. Calls across different networks need TURN credentials in .env.';
        }, videoCallConnectingWarningDelay);

        this.connectingTimeoutTimer = window.setTimeout(() => {
            if (this.callStatus !== 'connecting') {
                return;
            }

            this.showTurnWarning = !this.usesTurnServers();
            this.statusMessage = this.connectionFailureMessage();

            if (this.peer?.restartIce) {
                this.peer.restartIce();
            }
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
        this.showTurnWarning = false;
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
            void (async () => {
                try {
                    // Strip the remote SDP before setting it so inbound descriptions
                    // follow the same SSRC cleanup.
                    const cleanOffer = signalData.sdp ? { ...signalData, sdp: stripSdp(signalData.sdp) } : signalData;
                    await peer.setRemoteDescription(new RTCSessionDescription(cleanOffer));

                    if (this.peer !== peer) {
                        return;
                    }

                    const answer = await peer.createAnswer();
                    const preferredAnswer = answer.sdp
                        ? { type: answer.type, sdp: this.preferCodecs(answer.sdp) }
                        : answer;

                    await peer.setLocalDescription(preferredAnswer);
                    await waitForIceGathering(peer);

                    if (this.peer !== peer || !this.callId) {
                        return;
                    }

                    const localDescription = peer.localDescription ?? preferredAnswer;
                    const strippedSdp = stripSdp(localDescription.sdp);

                    await this.requestJson(this.callRoute('signal'), {
                        signal_data: { type: localDescription.type, sdp: strippedSdp },
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
            void (async () => {
                try {
                    // Strip the remote SDP before setting it so inbound descriptions
                    // follow the same SSRC cleanup.
                    const cleanAnswer = signalData.sdp ? { ...signalData, sdp: stripSdp(signalData.sdp) } : signalData;
                    await peer.setRemoteDescription(new RTCSessionDescription(cleanAnswer));

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
            await this.updateCameraCapabilities();
            return this.localStream;
        }

        const attempts = [
            { video: this.getBestVideoConstraints(), audio: true },
            { video: this.getBestVideoConstraints(true), audio: true },
            { video: true, audio: true },
            { video: true, audio: false },
            { video: false, audio: true },
        ];

        let lastError = null;

        for (const constraints of attempts) {
            try {
                this.localStream = await navigator.mediaDevices.getUserMedia(constraints);
                break;
            } catch (error) {
                lastError = error;

                if (error instanceof DOMException && error.name === 'NotAllowedError') {
                    throw error;
                }
            }
        }

        if (this.localStream === null) {
            throw lastError ?? new Error('Could not access camera or microphone.');
        }

        this.localStream.getAudioTracks().forEach((track) => {
            track.enabled = !this.microphoneMuted;
        });
        this.localStream.getVideoTracks().forEach((track) => {
            track.enabled = !this.cameraDisabled;
        });
        this.setVideoSource(localVideoElementId, this.localStream);
        this.setVideoSource(localVideoBackgroundElementId, this.localStream);
        await this.updateCameraCapabilities();

        return this.localStream;
    },

    getBestVideoConstraints(useFallback = false) {
        const isMobile = this.isMobileDevice?.() ?? /Android|iPhone|iPad/i.test(navigator.userAgent);

        return {
            width: { ideal: isMobile ? 480 : (useFallback ? 640 : 1280) },
            height: { ideal: isMobile ? 360 : (useFallback ? 480 : 720) },
            frameRate: { ideal: isMobile ? 15 : 30, max: isMobile ? 20 : 60 },
            facingMode: { ideal: this.facingMode },
        };
    },

    async setMaxBitrate(peer) {
        const isMobile = this.isMobileDevice?.() ?? /Android|iPhone|iPad/i.test(navigator.userAgent);
        const videoMaxKbps = isMobile ? 300 : 1200;
        const audioMaxKbps = 64;
        const senders = peer.getSenders();

        for (const sender of senders) {
            const params = sender.getParameters();

            if (!params.encodings || params.encodings.length === 0) {
                params.encodings = [{}];
            }

            if (sender.track?.kind === 'video') {
                params.encodings[0].maxBitrate = videoMaxKbps * 1000;
                params.encodings[0].maxFramerate = isMobile ? 15 : 30;

                if (isMobile) {
                    params.encodings[0].scaleResolutionDownBy = 1.5;
                }
            } else if (sender.track?.kind === 'audio') {
                params.encodings[0].maxBitrate = audioMaxKbps * 1000;
            }

            try {
                await sender.setParameters(params);
            } catch {
                // Some browsers do not support sender parameter updates.
            }
        }
    },

    preferCodecs(sdp, kind = 'video') {
        return preferCodecs(sdp, kind);
    },

    isPipSupported() {
        return Boolean(
            document.pictureInPictureEnabled
            && !document.querySelector(`#${remoteVideoElementId}`)?.disablePictureInPicture,
        );
    },

    async enterPip() {
        const remoteVideo = document.getElementById(remoteVideoElementId);

        if (!remoteVideo) {
            return;
        }

        const stream = remoteVideo.srcObject;
        if (!(stream instanceof MediaStream) || stream.getVideoTracks().length === 0) {
            this.statusMessage = 'No remote video to show in picture-in-picture.';
            window.setTimeout(() => {
                if (this.statusMessage.startsWith('No remote')) {
                    this.statusMessage = '';
                }
            }, 3000);
            return;
        }

        if (!document.pictureInPictureEnabled) {
            return;
        }

        try {
            if (document.pictureInPictureElement) {
                await document.exitPictureInPicture();
            }

            await remoteVideo.requestPictureInPicture();
        } catch {
            this.statusMessage = 'Picture-in-picture is not available in this browser.';
            window.setTimeout(() => {
                this.statusMessage = '';
            }, 3000);
        }
    },

    async exitPip() {
        if (!document.pictureInPictureElement) {
            return;
        }

        try {
            await document.exitPictureInPicture();
        } catch {
            // Ignore PiP cleanup failures.
        }
    },

    isMobileDevice() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
            || (navigator.maxTouchPoints > 1 && Math.min(window.innerWidth, window.innerHeight) < 900);
    },

    async updateCameraCapabilities() {
        if (!navigator.mediaDevices?.enumerateDevices) {
            this.hasCheckedCameraDevices = true;
            this.hasMultipleCameras = false;
            return;
        }

        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            this.hasMultipleCameras = devices.filter((device) => device.kind === 'videoinput').length > 1;
        } catch {
            this.hasMultipleCameras = false;
        }

        this.hasCheckedCameraDevices = true;
    },

    async switchCamera() {
        if (!this.localStream || !this.supportsCameraSwitch()) {
            return;
        }

        const previousFacingMode = this.facingMode;
        const previousVideoTracks = this.localStream.getVideoTracks();
        this.facingMode = this.facingMode === 'user' ? 'environment' : 'user';

        let cameraStream;

        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: this.facingMode } },
                audio: false,
            });
        } catch (error) {
            this.facingMode = previousFacingMode;
            this.statusMessage = this.callErrorMessage(error, 'Could not switch cameras. Your current camera is still active.');
            return;
        }

        const [newVideoTrack] = cameraStream.getVideoTracks();

        if (!newVideoTrack) {
            cameraStream.getTracks().forEach((track) => track.stop());
            this.facingMode = previousFacingMode;
            this.statusMessage = 'Could not switch cameras. Your current camera is still active.';
            return;
        }

        const sender = this.peer?.getSenders().find((candidateSender) => candidateSender.track?.kind === 'video');

        if (sender) {
            try {
                await sender.replaceTrack(newVideoTrack);
            } catch (error) {
                newVideoTrack.stop();
                this.facingMode = previousFacingMode;
                this.statusMessage = this.callErrorMessage(error, 'Could not switch cameras. Your current camera is still active.');
                return;
            }
        }

        const audioTracks = this.localStream.getAudioTracks();
        previousVideoTracks.forEach((track) => track.stop());
        this.localStream = new MediaStream([...audioTracks, newVideoTrack]);
        newVideoTrack.enabled = !this.cameraDisabled;
        this.setVideoSource(localVideoElementId, this.localStream);
        this.setVideoSource(localVideoBackgroundElementId, this.localStream);
        await this.updateCameraCapabilities();
    },

    cleanupCall(nextStatus, message = '') {
        void this.exitPip();
        this.clearConnectionTimers();
        this.stopCallTimer();

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
        this.microphoneMuted = false;
        this.cameraDisabled = false;
        this.remoteVideoActive = false;
        this.setVideoSource(localVideoElementId, null);
        this.setVideoSource(localVideoBackgroundElementId, null);
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
            allowRealtimeUnavailable = false,
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

        if (!allowRealtimeUnavailable && payload?.realtime_available === false) {
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

        if (error instanceof DOMException && error.name === 'NotReadableError') {
            return 'Your camera or microphone is already in use by another app.';
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
