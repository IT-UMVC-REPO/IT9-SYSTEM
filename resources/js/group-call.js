import { preferCodecs, stripSdp, videoCallIceServers, videoCallResetDelay } from './video-call';

export const groupConversationVideoCall = (config) => ({
    authUserId: config.authUserId,
    groupId: config.groupId,
    groupName: config.groupName,
    participantSummaries: config.participantSummaries ?? {},
    realtimeEnabled: config.realtimeEnabled,
    routes: config.routes,
    callStatus: 'idle',
    callId: null,
    localStream: null,
    iceServers: null,
    iceTransportPolicy: 'all',
    facingMode: 'user',
    hasMultipleCameras: false,
    hasCheckedCameraDevices: false,
    peerConnections: new Map(),
    remoteStreams: new Map(),
    remoteVideoActive: new Map(),
    remoteParticipants: [],
    participants: [],
    pendingSignals: new Map(),
    statusMessage: '',
    initialized: false,
    speakerParticipantId: null,
    microphoneMuted: false,
    cameraDisabled: false,
    callStartedAt: null,
    callDurationTimer: null,
    elapsedSeconds: 0,
    callChromeVisible: true,
    callChromeTimer: null,
    previewPosition: { right: 16, bottom: 96 },
    groupJoinHandler: null,

    init() {
        if (this.initialized || !this.realtimeEnabled || !window.Echo) {
            return;
        }

        this.initialized = true;

        window.Echo.private(`group.${this.groupId}`)
            .listen('.GroupCallInitiated', (event) => {
                if (event.caller_id === this.authUserId || this.callStatus !== 'idle') {
                    return;
                }

                this.callId = event.call_id;
                this.callStatus = 'ringing';
                this.statusMessage = `${event.caller_name} started a group call.`;
                window.sukiRingtone?.start();
            })
            .listen('.GroupCallSignal', (event) => {
                if (event.sender_id === this.authUserId || event.call_id !== this.callId) {
                    return;
                }

                if (event.recipient_id !== null && event.recipient_id !== this.authUserId) {
                    return;
                }

                void this.safeHandleGroupSignal(event.sender_id, event.signal_data);
            })
            .listen('.GroupCallStatusChanged', (event) => {
                if (this.callId !== null && event.call_id !== this.callId) {
                    return;
                }

                if (event.status === 'ended') {
                    this.cleanupGroupCall('idle');
                    return;
                }

                this.participants = this.normalizeParticipantIds(event.participants);

                if (this.localStream !== null && this.callStatus === 'active') {
                    this.connectToParticipants();
                }
            });

        this.groupJoinHandler = (event) => {
            if (this.callStatus !== 'idle') {
                return;
            }

            const callId = Number(event.detail?.callId ?? null);

            if (!callId) {
                return;
            }

            this.callId = callId;
            this.callStatus = 'ringing';
            void this.acceptCall();
        };

        document.addEventListener('group-call-join', this.groupJoinHandler);
    },

    destroy() {
        this.disposeOnLeave();
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

    isOverlayVisible() {
        return this.callStatus === 'active' || this.callStatus === 'connecting' || this.callStatus === 'ended';
    },

    normalizeParticipantIds(participants) {
        if (!Array.isArray(participants)) {
            return [];
        }

        return participants
            .map((participantId) => Number(participantId))
            .filter((participantId) => Number.isInteger(participantId));
    },

    shouldInitiatePeerConnection(participantId) {
        // The lower numeric user id creates the offer so each mesh edge has a single initiator.
        return Number(this.authUserId) < Number(participantId);
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

    participantName(participantId) {
        return this.participantSummaries?.[participantId]?.name ?? 'Participant';
    },

    participantInitials(participantId) {
        return this.participantSummaries?.[participantId]?.initials ?? '?';
    },

    activeParticipantCount() {
        return Math.max(1, new Set([this.authUserId, ...this.participants, ...this.remoteParticipants.map((participant) => participant.id)]).size);
    },

    selectSpeaker(participantId) {
        this.speakerParticipantId = participantId;
        window.setTimeout(() => this.refreshParticipantVideoSources(), 0);
    },

    speakerParticipant() {
        return this.remoteParticipants.find((participant) => participant.id === this.speakerParticipantId)
            ?? this.remoteParticipants[0]
            ?? null;
    },

    thumbnailParticipants() {
        const speaker = this.speakerParticipant();

        if (speaker === null) {
            return [];
        }

        return this.remoteParticipants.filter((participant) => participant.id !== speaker.id);
    },

    async startCall() {
        if (!this.supportsVideoCalling()) {
            this.statusMessage = 'Video calling is not available right now.';
            return;
        }

        try {
            this.callStatus = 'connecting';
            this.statusMessage = 'Preparing your camera...';
            this.startCallTimer();
            this.showCallChrome();
            await this.ensureLocalStream();
            this.statusMessage = 'Loading call connection settings...';
            await this.loadIceConfiguration();

            const payload = await this.requestJson(this.routes.initiate, {
                group_id: this.groupId,
            }, { timeoutMs: 15000 });

            this.callId = payload.id;
            this.callStatus = 'active';
            this.participants = this.normalizeParticipantIds(payload.participants ?? [this.authUserId]);
            this.statusMessage = 'Group call started.';
        } catch (error) {
            this.cleanupGroupCall('ended', this.groupCallErrorMessage(error, 'Could not start the group call.'));
        }
    },

    async acceptCall() {
        if (!this.callId) {
            return;
        }

        try {
            window.sukiRingtone?.stop();
            this.callStatus = 'connecting';
            this.statusMessage = 'Joining group call...';
            this.startCallTimer();
            this.showCallChrome();
            await this.ensureLocalStream();
            await this.loadIceConfiguration();

            const payload = await this.requestJson(this.callRoute('answer'), null, { timeoutMs: 15000 });

            this.callStatus = 'active';
            this.participants = this.normalizeParticipantIds(payload.participants ?? []);
            this.statusMessage = 'Connected.';

            if (this.localStream !== null) {
                this.connectToParticipants();
            }
        } catch (error) {
            this.cleanupGroupCall('ended', this.groupCallErrorMessage(error, 'Could not join the group call.'));
        }
    },

    declineGroupCall() {
        window.sukiRingtone?.stop();
        this.cleanupGroupCall('idle');
    },

    async leaveCall() {
        if (this.callId !== null) {
            try {
                await this.requestJson(this.callRoute('end'), null, { timeoutMs: 15000 });
            } catch {
                // Ignore cleanup failures while leaving.
            }
        }

        this.cleanupGroupCall('ended', 'Group call ended.');
    },

    disposeOnLeave() {
        void this.exitPip();

        if (this.localStream === null && this.peerConnections.size === 0) {
            this.teardownRealtimeListeners();
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

        this.cleanupGroupCall('idle');
        this.teardownRealtimeListeners();
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
        this.setVideoSource('group-call-local-video', this.localStream);
        this.setVideoSource('group-call-local-background-video', this.localStream);
        this.setVideoSource('group-call-local-grid-video', this.localStream);
        this.setVideoSource('group-call-local-thumbnail-video', this.localStream);
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

    isMobileDevice() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
            || (navigator.maxTouchPoints > 1 && Math.min(window.innerWidth, window.innerHeight) < 900);
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
        return Boolean(document.pictureInPictureEnabled);
    },

    async enterPip() {
        const speakerVideo = document.getElementById('group-call-speaker-video');
        const firstRemoteTile = document.querySelector('[id^="group-tile-video-"]');
        const pipVideo = speakerVideo?.srcObject ? speakerVideo : firstRemoteTile;

        if (!pipVideo || !this.isPipSupported()) {
            return;
        }

        try {
            if (document.pictureInPictureElement) {
                await document.exitPictureInPicture();
            }

            await pipVideo.requestPictureInPicture();
        } catch {
            // PiP is unavailable or the browser denied the request.
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
            this.statusMessage = this.groupCallErrorMessage(error, 'Could not switch cameras. Your current camera is still active.');
            return;
        }

        const [newVideoTrack] = cameraStream.getVideoTracks();

        if (!newVideoTrack) {
            cameraStream.getTracks().forEach((track) => track.stop());
            this.facingMode = previousFacingMode;
            this.statusMessage = 'Could not switch cameras. Your current camera is still active.';
            return;
        }

        const videoSenders = Array.from(this.peerConnections.values())
            .flatMap((peer) => peer.getSenders())
            .filter((sender) => sender.track?.kind === 'video');

        try {
            for (const sender of videoSenders) {
                await sender.replaceTrack(newVideoTrack);
            }
        } catch (error) {
            newVideoTrack.stop();
            this.facingMode = previousFacingMode;
            this.statusMessage = this.groupCallErrorMessage(error, 'Could not switch cameras. Your current camera is still active.');
            return;
        }

        const audioTracks = this.localStream.getAudioTracks();
        previousVideoTracks.forEach((track) => track.stop());
        this.localStream = new MediaStream([...audioTracks, newVideoTrack]);
        newVideoTrack.enabled = !this.cameraDisabled;
        this.setVideoSource('group-call-local-video', this.localStream);
        this.setVideoSource('group-call-local-background-video', this.localStream);
        this.setVideoSource('group-call-local-grid-video', this.localStream);
        this.setVideoSource('group-call-local-thumbnail-video', this.localStream);
        await this.updateCameraCapabilities();
    },

    async loadIceConfiguration() {
        if (this.iceServers !== null) {
            return;
        }

        const payload = await this.requestJson(this.routes.iceServers, null, {
            method: 'GET',
            timeoutMs: 15000,
            unavailableMessage: 'Could not load group call connection settings.',
        });

        this.iceServers = Array.isArray(payload?.ice_servers) && payload.ice_servers.length > 0
            ? payload.ice_servers
            : videoCallIceServers;
        this.iceTransportPolicy = payload?.ice_transport_policy === 'relay' ? 'relay' : 'all';
    },

    connectToParticipants() {
        if (this.localStream === null || this.callStatus !== 'active') {
            return;
        }

        this.participants
            .filter((participantId) => participantId !== this.authUserId)
            .forEach((participantId) => {
                if (!this.peerConnections.has(participantId)) {
                    this.createPeerConnection(participantId, this.shouldInitiatePeerConnection(participantId));
                }
            });
    },

    createPeerConnection(peerId, initiator = false) {
        if (this.peerConnections.has(peerId) || this.localStream === null) {
            return this.peerConnections.get(peerId);
        }

        const peer = new RTCPeerConnection({
            iceServers: this.iceServers ?? videoCallIceServers,
            iceTransportPolicy: this.iceTransportPolicy,
        });

        this.peerConnections.set(peerId, peer);
        this.localStream.getTracks().forEach((track) => peer.addTrack(track, this.localStream));

        peer.onicecandidate = ({ candidate }) => {
            if (!candidate || !this.callId) {
                return;
            }

            void this.safeSendGroupSignal(peerId, {
                type: 'candidate',
                candidate: candidate.toJSON(),
            }, { fatal: false });
        };

        peer.ontrack = (event) => {
            if (!event.streams[0]) {
                return;
            }

            this.remoteStreams.set(peerId, event.streams[0]);
            this.updateRemoteVideoActive(peerId, event.streams[0]);
            this.watchRemoteVideoTrack(peerId, event.track, event.streams[0]);
            this.refreshRemoteParticipants();
            void this.setMaxBitrate(peer);
        };

        peer.oniceconnectionstatechange = () => {
            if (peer.iceConnectionState === 'failed') {
                this.removePeer(peerId);
                return;
            }

            if (peer.iceConnectionState === 'disconnected' && this.callStatus !== 'idle') {
                this.statusMessage = 'A participant connection was interrupted. Trying to recover...';
                peer.restartIce?.();

                window.setTimeout(() => {
                    if (this.peerConnections.get(peerId) === peer && peer.iceConnectionState === 'disconnected') {
                        this.removePeer(peerId);
                    }
                }, 5000);
            }
        };

        if (initiator) {
            peer.createOffer({ offerToReceiveAudio: true, offerToReceiveVideo: true })
                .then((offer) => {
                    const preferredOffer = offer.sdp
                        ? { type: offer.type, sdp: this.preferCodecs(offer.sdp) }
                        : offer;

                    return peer.setLocalDescription(preferredOffer).then(() => preferredOffer);
                })
                .then(async (offer) => {
                    const sent = await this.safeSendGroupSignal(peerId, {
                        type: offer.type,
                        sdp: stripSdp(offer.sdp),
                    });

                    if (!sent) {
                        this.removePeer(peerId);
                    }
                })
                .catch(() => this.removePeer(peerId));
        }

        return peer;
    },

    async safeHandleGroupSignal(senderId, signalData) {
        try {
            await this.handleGroupSignal(senderId, signalData);
        } catch (error) {
            if (this.callStatus !== 'idle') {
                this.cleanupGroupCall('ended', this.groupCallErrorMessage(error, 'Could not sync the group call.'));
            }
        }
    },

    async safeSendGroupSignal(recipientId, signalData, options = {}) {
        const { fatal = true } = options;

        try {
            await this.sendGroupSignal(recipientId, signalData);

            return true;
        } catch (error) {
            const message = this.groupCallErrorMessage(error, 'Could not sync the group call.');

            if (fatal && this.callStatus !== 'idle') {
                this.cleanupGroupCall('ended', message);
            } else if (this.callStatus !== 'idle') {
                this.statusMessage = message;
            }

            return false;
        }
    },

    async handleGroupSignal(senderId, signalData) {
        if (!signalData || typeof signalData !== 'object') {
            return;
        }

        if (!this.participants.includes(senderId)) {
            this.participants = [...this.participants, senderId];
        }

        if (this.localStream === null) {
            const signals = this.pendingSignals.get(senderId) ?? [];
            signals.push(signalData);
            this.pendingSignals.set(senderId, signals);
            return;
        }

        const peer = this.createPeerConnection(senderId, false);

        if (!peer) {
            return;
        }

        if (signalData.type === 'offer') {
            await peer.setRemoteDescription(new RTCSessionDescription({
                ...signalData,
                sdp: stripSdp(signalData.sdp),
            }));

            const answer = await peer.createAnswer();
            const preferredAnswer = answer.sdp
                ? { type: answer.type, sdp: this.preferCodecs(answer.sdp) }
                : answer;

            await peer.setLocalDescription(preferredAnswer);
            const sent = await this.safeSendGroupSignal(senderId, {
                type: preferredAnswer.type,
                sdp: stripSdp(preferredAnswer.sdp),
            });

            if (sent) {
                this.flushGroupSignals(senderId);
            }

            return;
        }

        if (signalData.type === 'answer') {
            await peer.setRemoteDescription(new RTCSessionDescription({
                ...signalData,
                sdp: stripSdp(signalData.sdp),
            }));
            this.flushGroupSignals(senderId);
            return;
        }

        if (signalData.candidate) {
            if (!peer.remoteDescription) {
                const signals = this.pendingSignals.get(senderId) ?? [];
                signals.push(signalData);
                this.pendingSignals.set(senderId, signals);
                return;
            }

            await peer.addIceCandidate(new RTCIceCandidate(signalData.candidate));
        }
    },

    flushGroupSignals(senderId) {
        const signals = this.pendingSignals.get(senderId) ?? [];
        this.pendingSignals.delete(senderId);
        signals.forEach((signalData) => {
            void this.safeHandleGroupSignal(senderId, signalData);
        });
    },

    async sendGroupSignal(recipientId, signalData) {
        await this.requestJson(this.callRoute('signal'), {
            recipient_id: recipientId,
            signal_data: signalData,
        }, {
            timeoutMs: 10000,
            unavailableMessage: 'Could not sync the group call. Please check your realtime connection.',
        });
    },

    streamHasActiveVideo(stream) {
        return stream.getVideoTracks().some((track) => (
            track.readyState === 'live'
            && track.enabled !== false
            && !track.muted
        ));
    },

    updateRemoteVideoActive(participantId, stream) {
        this.remoteVideoActive.set(participantId, this.streamHasActiveVideo(stream));
        this.remoteVideoActive = new Map(this.remoteVideoActive);
    },

    setRemoteVideoActive(participantId, active) {
        this.remoteVideoActive.set(participantId, active);
        this.remoteVideoActive = new Map(this.remoteVideoActive);
    },

    watchRemoteVideoTrack(participantId, track, stream) {
        if (track.kind !== 'video') {
            return;
        }

        this.setRemoteVideoActive(participantId, track.readyState === 'live' && !track.muted);
        track.addEventListener('mute', () => this.setRemoteVideoActive(participantId, false));
        track.addEventListener('unmute', () => this.updateRemoteVideoActive(participantId, stream));
        track.addEventListener('ended', () => this.setRemoteVideoActive(participantId, false));
    },

    refreshRemoteParticipants() {
        this.remoteParticipants = Array.from(this.remoteStreams.keys()).map((participantId) => ({
            id: participantId,
            tileElementId: `group-tile-video-${participantId}`,
            thumbnailElementId: `group-thumbnail-video-${participantId}`,
            name: this.participantName(participantId),
            initials: this.participantInitials(participantId),
        }));

        if (
            this.speakerParticipantId === null
            || !this.remoteParticipants.some((participant) => participant.id === this.speakerParticipantId)
        ) {
            this.speakerParticipantId = this.remoteParticipants[0]?.id ?? null;
        }

        window.setTimeout(() => this.refreshParticipantVideoSources(), 0);
    },

    refreshParticipantVideoSources() {
        this.remoteParticipants.forEach((participant) => {
            this.setVideoSource(participant.tileElementId, this.remoteStreams.get(participant.id) ?? null);
            this.setVideoSource(participant.thumbnailElementId, this.remoteStreams.get(participant.id) ?? null);
        });

        if (this.localStream) {
            this.setVideoSource('group-call-local-grid-video', this.localStream);
            this.setVideoSource('group-call-local-thumbnail-video', this.localStream);
        }

        this.refreshSpeakerVideo();
    },

    refreshSpeakerVideo() {
        const speaker = this.speakerParticipant();
        this.setVideoSource('group-call-speaker-video', speaker === null ? null : this.remoteStreams.get(speaker.id) ?? null);
    },

    removePeer(peerId) {
        const peer = this.peerConnections.get(peerId);

        if (peer) {
            peer.close();
        }

        this.peerConnections.delete(peerId);
        this.remoteStreams.delete(peerId);
        this.remoteVideoActive.delete(peerId);
        this.remoteVideoActive = new Map(this.remoteVideoActive);
        this.refreshRemoteParticipants();
    },

    cleanupGroupCall(nextStatus = 'idle', message = '') {
        void this.exitPip();
        window.sukiRingtone?.stop();
        this.stopCallTimer();
        this.peerConnections.forEach((peer) => peer.close());
        this.peerConnections.clear();
        this.remoteStreams.clear();
        this.remoteVideoActive.clear();
        this.remoteVideoActive = new Map();
        this.remoteParticipants = [];
        this.participants = [];
        this.pendingSignals.clear();
        this.speakerParticipantId = null;
        this.iceServers = null;
        this.iceTransportPolicy = 'all';

        if (this.localStream !== null) {
            this.localStream.getTracks().forEach((track) => track.stop());
        }

        this.localStream = null;
        this.callId = null;
        this.callStatus = nextStatus;
        this.statusMessage = message;
        this.microphoneMuted = false;
        this.cameraDisabled = false;
        this.setVideoSource('group-call-local-video', null);
        this.setVideoSource('group-call-local-background-video', null);
        this.setVideoSource('group-call-local-grid-video', null);
        this.setVideoSource('group-call-local-thumbnail-video', null);
        this.setVideoSource('group-call-speaker-video', null);

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

    teardownRealtimeListeners() {
        if (this.groupJoinHandler !== null) {
            document.removeEventListener('group-call-join', this.groupJoinHandler);
            this.groupJoinHandler = null;
        }

        if (this.initialized && window.Echo) {
            window.Echo.leave(`group.${this.groupId}`);
        }

        this.initialized = false;
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
            unavailableMessage = 'Group call server unavailable. Please try again later.',
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

        const payload = (response.headers.get('content-type') ?? '').includes('application/json')
            ? await response.json()
            : null;

        if (!response.ok || payload?.realtime_available === false) {
            const message = payload?.message ?? unavailableMessage;
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

    groupCallErrorMessage(error, fallbackMessage) {
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
