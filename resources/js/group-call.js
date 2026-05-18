import {
    isSessionDescriptionSignal,
    localDescriptionSignal,
    peerConnectionOptions,
    preferCodecs,
    stripSdp,
    videoCallIceServers,
    videoCallResetDelay,
} from './video-call';

const reusableGroupCallFor = (config) => {
    const activeCall = window.__activeGroupCall;

    if (!activeCall?.isReusableForConfig?.(config)) {
        return null;
    }

    return activeCall.adoptConfig(config);
};

export const groupConversationVideoCall = (config) => reusableGroupCallFor(config) ?? ({
    authUserId: config.authUserId,
    groupId: config.groupId,
    groupName: config.groupName,
    participantSummaries: config.participantSummaries ?? {},
    realtimeEnabled: config.realtimeEnabled,
    routes: config.routes,
    volume: 1.0,
    callStatus: 'idle',
    callId: null,
    localStream: null,
    iceServers: null,
    iceTransportPolicy: 'all',
    facingMode: 'user',
    hasMicrophone: true,
    hasCamera: true,
    hasMultipleCameras: false,
    hasCheckedCameraDevices: false,
    peerConnections: new Map(),
    remoteStreams: new Map(),
    remoteVideoActive: new Map(),
    remoteParticipants: [],
    participants: [],
    pendingSignals: new Map(),
    peerStates: new Map(),
    peerSignalQueues: new Map(),
    peerReconnectTimers: new Map(),
    candidateQueues: new Map(),
    candidateFlushTimers: new Map(),
    statusMessage: '',
    initialized: false,
    speakerParticipantId: null,
    participantFullscreenId: null,
    lastParticipantTap: { id: null, at: 0 },
    microphoneMuted: false,
    cameraDisabled: false,
    callStartedAt: null,
    callDurationTimer: null,
    elapsedSeconds: 0,
    callChromeVisible: true,
    callChromeTimer: null,
    previewPosition: { right: 16, bottom: 96 },
    groupJoinHandler: null,
    callChannelRef: null,
    callChannelName: null,
    screenSharing: false,
    screenTrack: null,
    cameraTrackBeforeShare: null,
    endingCall: false,

    adoptConfig(nextConfig) {
        this.authUserId = nextConfig.authUserId;
        this.groupId = nextConfig.groupId;
        this.groupName = nextConfig.groupName;
        this.participantSummaries = nextConfig.participantSummaries ?? {};
        this.realtimeEnabled = nextConfig.realtimeEnabled;
        this.routes = nextConfig.routes;

        return this;
    },

    isReusableForConfig(nextConfig) {
        return Number(this.groupId) === Number(nextConfig.groupId)
            && this.callId !== null
            && this.isCallInProgress();
    },

    init() {
        if (this.initialized) {
            this.exposeActiveGroupCall();
            this.syncLocalVideoSources();
            this.refreshRemoteParticipants();
            return;
        }

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
                this.subscribeCallChannel();
                this.callStatus = 'ringing';
                this.statusMessage = `${event.caller_name} started a group call.`;
                window.sukiRingtone?.start();
            })
            .listen('.GroupCallStatusChanged', (event) => {
                this.handleGroupStatusChanged(event);
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

    subscribeCallChannel() {
        if (!window.Echo || !this.callId) {
            return;
        }

        const channelName = `call.${this.callId}`;

        if (this.callChannelName === channelName && this.callChannelRef !== null) {
            return;
        }

        this.unsubscribeCallChannel();
        this.callChannelName = channelName;
        this.callChannelRef = window.Echo.private(channelName)
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
                this.handleGroupStatusChanged(event);
            });
    },

    unsubscribeCallChannel() {
        if (this.callChannelName && window.Echo) {
            window.Echo.leave(this.callChannelName);
        }

        this.callChannelRef = null;
        this.callChannelName = null;
    },

    handleGroupStatusChanged(event) {
        if (this.callId !== null && event.call_id !== this.callId) {
            return;
        }

        if (event.status === 'ended') {
            this.cleanupGroupCall(this.endingCall ? 'ended' : 'idle', this.endingCall ? 'Group call ended.' : '');
            return;
        }

        this.participants = this.normalizeParticipantIds(event.participants);
        this.removeDepartedPeers();

        if (this.localStream !== null && (this.callStatus === 'active' || this.callStatus === 'connecting')) {
            this.connectToParticipants();
        }

        this.refreshRemoteParticipants();
    },

    destroy() {
        this.disposeOnLeave();
    },

    isCallInProgress() {
        return ['ringing', 'connecting', 'active'].includes(this.callStatus)
            || this.localStream !== null
            || this.peerConnections.size > 0;
    },

    exposeActiveGroupCall() {
        if (!this.isCallInProgress() || this.callId === null) {
            return;
        }

        window.__activeGroupCall = this;
    },

    releaseActiveGroupCall() {
        if (window.__activeGroupCall === this) {
            window.__activeGroupCall = null;
        }
    },

    groupConversationUrl() {
        return this.routes?.conversation ?? window.location.href;
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

    supportsCameraSwitch() {
        return this.hasCamera && this.hasMultipleCameras && !this.screenSharing;
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

    gridStyle(participantCount, viewportWidth = null, viewportHeight = null) {
        const remoteCount = Number.isFinite(Number(participantCount)) ? Number(participantCount) : 0;
        const totalTiles = Math.max(1, remoteCount + 1);

        if (totalTiles <= 1) {
            return 'display: grid; grid-template-columns: 1fr; grid-template-rows: minmax(0, 1fr);';
        }

        const width = viewportWidth ?? window.innerWidth;
        const height = viewportHeight ?? window.innerHeight;
        const availableHeight = height - 136;

        let bestCols = 1;
        let bestScore = -Infinity;

        for (let cols = 1; cols <= totalTiles; cols += 1) {
            const rows = Math.ceil(totalTiles / cols);
            const emptySlots = (rows * cols) - totalTiles;
            const tileWidth = width / cols;
            const tileHeight = availableHeight / rows;
            const tileAspect = tileWidth / Math.max(tileHeight, 1);
            const aspectScore = -Math.abs(Math.log(tileAspect / 1.33)) * 3;
            const emptyPenalty = emptySlots * 4;
            const narrowTilePenalty = tileWidth < 120 ? (120 - tileWidth) * 0.5 : 0;
            const score = aspectScore - emptyPenalty - narrowTilePenalty;

            if (score > bestScore) {
                bestScore = score;
                bestCols = cols;
            }
        }

        return `display: grid; grid-template-columns: repeat(${bestCols}, 1fr); grid-auto-rows: 1fr;`;
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
        if (!this.hasMicrophone) {
            this.microphoneMuted = true;
            return;
        }

        this.microphoneMuted = !this.microphoneMuted;
        this.localStream?.getAudioTracks().forEach((track) => {
            track.enabled = !this.microphoneMuted;
        });
    },

    async toggleCamera() {
        if (this.screenSharing) {
            return;
        }

        if (!this.hasCamera) {
            this.cameraDisabled = true;
            return;
        }

        if (this.cameraDisabled && this.localStream?.getVideoTracks().length === 0) {
            await this.enableCameraTrack();
            return;
        }

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

    handleParticipantTap(participantId) {
        const normalizedParticipantId = Number(participantId);
        const now = Date.now();
        const tappedTwice = this.lastParticipantTap.id === normalizedParticipantId
            && now - this.lastParticipantTap.at <= 400;

        this.selectSpeaker(normalizedParticipantId);

        if (tappedTwice) {
            this.toggleParticipantFullscreen(normalizedParticipantId);
            this.lastParticipantTap = { id: null, at: 0 };
            return;
        }

        this.lastParticipantTap = { id: normalizedParticipantId, at: now };
    },

    toggleParticipantFullscreen(participantId) {
        const normalizedParticipantId = Number(participantId);
        this.participantFullscreenId = this.participantFullscreenId === normalizedParticipantId
            ? null
            : normalizedParticipantId;

        window.setTimeout(() => this.refreshParticipantVideoSources(), 0);
    },

    participantFullscreenClass(participantId) {
        if (this.participantFullscreenId === null) {
            return '';
        }

        return this.participantFullscreenId === Number(participantId)
            ? 'absolute inset-4 z-30 rounded-[1.75rem]'
            : 'hidden';
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
            this.statusMessage = 'Starting group call...';
            this.startCallTimer();
            this.showCallChrome();

            const payload = await this.requestJson(this.routes.initiate, {
                group_id: this.groupId,
            }, {
                allowRealtimeUnavailable: true,
                timeoutMs: 15000,
            });

            this.callId = payload.id;
            this.subscribeCallChannel();
            this.callStatus = 'active';
            this.participants = this.normalizeParticipantIds(payload.participants ?? [this.authUserId]);
            this.statusMessage = 'Preparing your camera...';
            this.exposeActiveGroupCall();

            await this.ensureLocalStream();
            this.syncLocalVideoSources();
            this.statusMessage = 'Loading call connection settings...';
            await this.loadIceConfiguration();
            this.statusMessage = 'Group call started.';
            this.connectToParticipants();
        } catch (error) {
            await this.endGroupCallAfterSetupFailure();
            this.cleanupGroupCall('ended', this.groupCallErrorMessage(error, 'Could not start the group call.'));
        }
    },

    async acceptCall() {
        if (!this.callId) {
            return;
        }

        try {
            window.sukiRingtone?.stop();
            this.subscribeCallChannel();
            this.callStatus = 'connecting';
            this.statusMessage = 'Joining group call...';
            this.startCallTimer();
            this.showCallChrome();

            const payload = await this.requestJson(this.callRoute('answer'), null, { timeoutMs: 15000 });

            this.callStatus = 'active';
            this.participants = this.normalizeParticipantIds(payload.participants ?? []);
            this.statusMessage = 'Preparing your camera...';
            this.exposeActiveGroupCall();

            await this.ensureLocalStream();
            this.syncLocalVideoSources();
            this.statusMessage = 'Loading call connection settings...';
            await this.loadIceConfiguration();
            this.statusMessage = 'Connected.';
            this.connectToParticipants();
        } catch (error) {
            await this.endGroupCallAfterSetupFailure();
            this.cleanupGroupCall('ended', this.groupCallErrorMessage(error, 'Could not join the group call.'));
        }
    },

    async endGroupCallAfterSetupFailure() {
        if (this.callId === null) {
            return;
        }

        try {
            await this.requestJson(this.callRoute('end'), null, {
                allowRealtimeUnavailable: true,
                timeoutMs: 5000,
            });
        } catch {
            // Ignore server cleanup failures after a local setup error.
        }
    },

    declineGroupCall() {
        window.sukiRingtone?.stop();
        this.cleanupGroupCall('idle');
    },

    async leaveCall() {
        if (this.endingCall || this.callStatus === 'idle' || this.callStatus === 'ended') {
            return;
        }

        this.endingCall = true;

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
            this.setVideoSource('group-call-local-video', this.localStream);
            this.syncLocalVideoSources();
            await this.updateCameraCapabilities();
            return this.localStream;
        }

        const mobileAudioFirst = this.isMobileDevice();
        const attempts = mobileAudioFirst
            ? [
                { video: false, audio: true },
                { video: this.getBestVideoConstraints(true), audio: true },
                { video: true, audio: true },
                { video: true, audio: false },
            ]
            : [
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

        this.applyLocalMediaAvailability();
        this.setVideoSource('group-call-local-video', this.localStream);
        this.syncLocalVideoSources();
        await this.updateCameraCapabilities();
        this.applyLocalMediaAvailability();

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
        const videoMaxKbps = isMobile ? 900 : 1800;
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

    async updateCameraCapabilities() {
        if (!navigator.mediaDevices?.enumerateDevices) {
            this.hasCheckedCameraDevices = true;
            this.hasMultipleCameras = false;
            this.applyLocalMediaAvailability();
            return;
        }

        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            const audioInputs = devices.filter((device) => device.kind === 'audioinput');
            const videoInputs = devices.filter((device) => device.kind === 'videoinput');

            this.hasMultipleCameras = videoInputs.length > 1;

            if (this.localStream === null) {
                this.hasMicrophone = audioInputs.length > 0;
                this.hasCamera = videoInputs.length > 0;
            } else {
                this.hasMicrophone = this.localStream.getAudioTracks().length > 0;
                this.hasCamera = this.screenSharing
                    ? videoInputs.length > 0 || this.hasCamera
                    : this.localStream.getVideoTracks().length > 0;
            }
        } catch {
            this.hasMultipleCameras = false;
            this.applyLocalMediaAvailability();
        }

        if (!this.hasMicrophone) {
            this.microphoneMuted = true;
        }

        if (!this.hasCamera && !this.screenSharing) {
            this.cameraDisabled = true;
        }

        this.hasCheckedCameraDevices = true;
    },

    applyLocalMediaAvailability() {
        if (this.localStream === null) {
            return;
        }

        const audioTracks = this.localStream.getAudioTracks();
        const videoTracks = this.localStream.getVideoTracks();

        this.hasMicrophone = audioTracks.length > 0;

        if (!this.hasMicrophone) {
            this.microphoneMuted = true;
        }

        audioTracks.forEach((track) => {
            track.enabled = !this.microphoneMuted;
        });

        if (!this.screenSharing) {
            this.hasCamera = videoTracks.length > 0;

            if (!this.hasCamera) {
                this.cameraDisabled = true;
            }
        }

        videoTracks.forEach((track) => {
            track.enabled = this.screenSharing || !this.cameraDisabled;
        });
    },

    isPipSupported() {
        return Boolean(document.pictureInPictureEnabled && HTMLVideoElement.prototype.requestPictureInPicture);
    },

    async enterPip() {
        if (!this.isPipSupported()) {
            return;
        }

        if (document.pictureInPictureElement) {
            await document.exitPictureInPicture();
            return;
        }

        const video = this.pipVideoElement();

        if (!video) {
            this.statusMessage = 'No active video is available for picture-in-picture.';
            return;
        }

        try {
            await video.requestPictureInPicture();
        } catch (error) {
            this.statusMessage = this.groupCallErrorMessage(error, 'Could not open picture-in-picture.');
        }
    },

    pipVideoElement() {
        const ids = [
            'group-call-speaker-video',
            'group-call-local-grid-video',
            'group-call-local-video',
            'group-call-local-thumbnail-video',
        ];

        return ids
            .map((elementId) => document.getElementById(elementId))
            .find((element) => (
                element instanceof HTMLVideoElement
                && element.srcObject instanceof MediaStream
                && element.srcObject.getVideoTracks().length > 0
            )) ?? null;
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
        this.syncLocalVideoSources();
        await this.updateCameraCapabilities();
    },

    async enableCameraTrack() {
        if (!this.localStream) {
            return;
        }

        let cameraStream;

        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: this.facingMode } },
                audio: false,
            });
        } catch (error) {
            this.statusMessage = this.groupCallErrorMessage(error, 'Could not turn on the camera.');
            return;
        }

        const [videoTrack] = cameraStream.getVideoTracks();

        if (!videoTrack) {
            cameraStream.getTracks().forEach((track) => track.stop());
            this.statusMessage = 'Could not turn on the camera.';
            return;
        }

        videoTrack.enabled = true;
        this.localStream.addTrack(videoTrack);

        for (const [peerId, peer] of this.peerConnections.entries()) {
            const sender = peer.getSenders().find((candidateSender) => candidateSender.track?.kind === 'video');

            if (sender) {
                await sender.replaceTrack(videoTrack);
                continue;
            }

            peer.addTrack(videoTrack, this.localStream);
            const state = this.peerStates.get(peerId);

            if (state?.shouldOffer) {
                await this.negotiateGroupPeer(peerId, peer, state);
            } else {
                await this.safeSendGroupSignal(peerId, { type: 'renegotiate' }, { fatal: false });
            }
        }

        this.cameraDisabled = false;
        this.syncLocalVideoSources();
        await this.updateCameraCapabilities();
    },

    async toggleScreenShare() {
        if (this.screenSharing) {
            await this.stopScreenShare();
            return;
        }

        await this.startScreenShare();
    },

    async startScreenShare() {
        if (!navigator.mediaDevices?.getDisplayMedia || !this.localStream) {
            this.statusMessage = 'Screen sharing is not available in this browser.';
            return;
        }

        let displayStream;

        try {
            displayStream = await navigator.mediaDevices.getDisplayMedia({
                video: true,
                audio: false,
            });
        } catch (error) {
            this.statusMessage = this.groupCallErrorMessage(error, 'Could not start screen sharing.');
            return;
        }

        const [screenTrack] = displayStream.getVideoTracks();

        if (!screenTrack) {
            displayStream.getTracks().forEach((track) => track.stop());
            this.statusMessage = 'Could not start screen sharing.';
            return;
        }

        this.cameraTrackBeforeShare = this.localStream.getVideoTracks()[0] ?? null;

        try {
            await this.replaceOutgoingVideoTrack(screenTrack);
        } catch (error) {
            screenTrack.stop();
            this.statusMessage = this.groupCallErrorMessage(error, 'Could not start screen sharing.');
            return;
        }

        this.screenTrack = screenTrack;
        this.screenSharing = true;
        this.localStream = new MediaStream([...this.localStream.getAudioTracks(), screenTrack]);
        this.syncLocalVideoSources();

        screenTrack.addEventListener('ended', () => {
            if (this.screenSharing) {
                void this.stopScreenShare();
            }
        }, { once: true });
    },

    async stopScreenShare() {
        const previousScreenTrack = this.screenTrack;
        const audioTracks = this.localStream?.getAudioTracks() ?? [];
        let nextVideoTrack = this.cameraTrackBeforeShare;

        if (!nextVideoTrack || nextVideoTrack.readyState !== 'live') {
            try {
                const cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: this.facingMode } },
                    audio: false,
                });
                [nextVideoTrack] = cameraStream.getVideoTracks();
            } catch {
                nextVideoTrack = null;
            }
        }

        if (nextVideoTrack) {
            nextVideoTrack.enabled = !this.cameraDisabled;
            await this.replaceOutgoingVideoTrack(nextVideoTrack);
            this.localStream = new MediaStream([...audioTracks, nextVideoTrack]);
        } else {
            await this.replaceOutgoingVideoTrack(null);
            this.localStream = new MediaStream(audioTracks);
        }

        previousScreenTrack?.stop();
        this.screenTrack = null;
        this.cameraTrackBeforeShare = null;
        this.screenSharing = false;
        this.syncLocalVideoSources();
    },

    async replaceOutgoingVideoTrack(track) {
        const videoSenders = Array.from(this.peerConnections.values())
            .flatMap((peer) => peer.getSenders())
            .filter((sender) => sender.track?.kind === 'video');

        for (const sender of videoSenders) {
            await sender.replaceTrack(track);
        }
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
        if (this.callStatus !== 'active') {
            return;
        }

        if (this.localStream === null) {
            window.setTimeout(() => this.connectToParticipants(), 500);
            return;
        }

        this.participants
            .filter((participantId) => participantId !== this.authUserId)
            .forEach((participantId) => {
                if (!this.peerConnections.has(participantId)) {
                    const initiator = this.shouldInitiatePeerConnection(participantId);
                    this.createPeerConnection(participantId, initiator);
                }
            });
    },

    createPeerConnection(peerId, initiator = false) {
        if (this.peerConnections.has(peerId) || this.localStream === null) {
            return this.peerConnections.get(peerId);
        }

        const peer = new RTCPeerConnection(peerConnectionOptions(this.iceServers, this.iceTransportPolicy));
        const state = this.createPeerState(peerId, peer, initiator);

        this.peerConnections.set(peerId, peer);
        this.localStream.getTracks().forEach((track) => peer.addTrack(track, this.localStream));

        peer.onicecandidate = (event) => {
            if (this.peerConnections.get(peerId) !== peer || !event.candidate || !this.callId) {
                return;
            }

            this.queueIceCandidate(peerId, event.candidate);
        };

        peer.ontrack = (event) => {
            if (this.peerConnections.get(peerId) !== peer) {
                return;
            }

            const incomingStream = (event.streams && event.streams.length > 0)
                ? event.streams[0]
                : (() => {
                    const existing = this.remoteStreams.get(peerId);

                    if (existing instanceof MediaStream) {
                        existing.addTrack(event.track);
                        return existing;
                    }

                    return new MediaStream([event.track]);
                })();

            this.remoteStreams.set(peerId, incomingStream);
            this.remoteStreams = new Map(this.remoteStreams);
            state.connected = true;
            this.updateRemoteVideoActive(peerId, incomingStream);
            this.watchRemoteVideoTrack(peerId, event.track, incomingStream);
            this.refreshRemoteParticipants();
            void this.setMaxBitrate(peer);
        };

        const handleConnectionStateChange = () => {
            if (this.peerConnections.get(peerId) !== peer) {
                return;
            }

            const iceState = peer.iceConnectionState;
            const connectionState = peer.connectionState;

            if (iceState === 'connected' || iceState === 'completed' || connectionState === 'connected') {
                state.connected = true;
                this.statusMessage = 'Connected.';
                return;
            }

            if (iceState === 'checking' || connectionState === 'connecting') {
                if (this.callStatus === 'active') {
                    this.statusMessage = `Connecting with ${this.participantName(peerId)}...`;
                }

                return;
            }

            if (iceState === 'disconnected' || connectionState === 'disconnected') {
                this.statusMessage = 'Connection interrupted. Trying to recover...';

                window.setTimeout(() => {
                    if (this.peerConnections.get(peerId) !== peer) return;
                    if (
                        peer.iceConnectionState !== 'disconnected'
                        && peer.iceConnectionState !== 'failed'
                        && peer.connectionState !== 'disconnected'
                        && peer.connectionState !== 'failed'
                    ) {
                        return;
                    }

                    this.schedulePeerReconnect(peerId, 0);
                }, 4500);

                return;
            }

            if (iceState === 'failed' || connectionState === 'failed') {
                this.schedulePeerReconnect(peerId, 0);
            }
        };

        peer.oniceconnectionstatechange = handleConnectionStateChange;
        peer.onconnectionstatechange = handleConnectionStateChange;

        if (initiator) {
            void this.negotiateGroupPeer(peerId, peer, state);
        }

        return peer;
    },

    createPeerState(peerId, peer, shouldOffer) {
        const state = {
            peer,
            shouldOffer,
            polite: Number(this.authUserId) > Number(peerId),
            makingOffer: false,
            ignoreOffer: false,
            isSettingRemoteAnswerPending: false,
            connected: false,
        };

        this.peerStates.set(peerId, state);

        return state;
    },

    async negotiateGroupPeer(peerId, peer, state, options = {}) {
        if (
            this.peerConnections.get(peerId) !== peer
            || !this.callId
            || !state.shouldOffer
            || state.makingOffer
        ) {
            return;
        }

        const { iceRestart = false } = options;

        try {
            state.makingOffer = true;

            const offer = await peer.createOffer({
                offerToReceiveAudio: true,
                offerToReceiveVideo: true,
                iceRestart,
            });
            const preferredOffer = offer.sdp
                ? { type: offer.type, sdp: this.preferCodecs(offer.sdp) }
                : offer;

            await peer.setLocalDescription(preferredOffer);

            if (this.peerConnections.get(peerId) !== peer || !this.callId) {
                return;
            }

            const localDescription = peer.localDescription ?? preferredOffer;
            const sent = await this.safeSendGroupSignal(peerId, localDescriptionSignal(localDescription), { fatal: false });

            if (!sent) {
                this.schedulePeerReconnect(peerId, 1500);
            }
        } catch {
            this.schedulePeerReconnect(peerId, 1500);
        } finally {
            state.makingOffer = false;
        }
    },

    schedulePeerReconnect(peerId, delayMs = 1200) {
        if (this.callStatus !== 'active' || this.callId === null || !this.participants.includes(peerId)) {
            return;
        }

        const previousTimer = this.peerReconnectTimers.get(peerId);

        if (previousTimer !== undefined) {
            window.clearTimeout(previousTimer);
        }

        this.statusMessage = `Reconnecting with ${this.participantName(peerId)}...`;

        const timer = window.setTimeout(() => {
            this.peerReconnectTimers.delete(peerId);

            if (this.callStatus !== 'active' || this.callId === null || !this.participants.includes(peerId)) {
                return;
            }

            const shouldOffer = this.shouldInitiatePeerConnection(peerId);
            this.closePeerConnection(peerId);

            if (shouldOffer) {
                this.createPeerConnection(peerId, true);
                return;
            }

            void this.safeSendGroupSignal(peerId, { type: 'renegotiate' }, { fatal: false });
        }, delayMs);

        this.peerReconnectTimers.set(peerId, timer);
    },

    closePeerConnection(peerId) {
        const peer = this.peerConnections.get(peerId);

        if (peer) {
            peer.onicecandidate = null;
            peer.ontrack = null;
            peer.oniceconnectionstatechange = null;
            peer.onconnectionstatechange = null;
            peer.close();
        }

        this.peerConnections.delete(peerId);
        this.peerStates.delete(peerId);
        this.candidateQueues.delete(peerId);

        if (this.candidateFlushTimers.has(peerId)) {
            window.clearTimeout(this.candidateFlushTimers.get(peerId));
            this.candidateFlushTimers.delete(peerId);
        }
    },

    queueIceCandidate(peerId, candidate) {
        const candidates = this.candidateQueues.get(peerId) ?? [];
        candidates.push(candidate.toJSON());
        this.candidateQueues.set(peerId, candidates);

        if (this.candidateFlushTimers.has(peerId)) {
            return;
        }

        const timer = window.setTimeout(() => {
            this.candidateFlushTimers.delete(peerId);
            void this.flushIceCandidates(peerId);
        }, 120);

        this.candidateFlushTimers.set(peerId, timer);
    },

    async flushIceCandidates(peerId) {
        const candidates = this.candidateQueues.get(peerId) ?? [];
        this.candidateQueues.delete(peerId);

        if (candidates.length === 0 || !this.callId) {
            return;
        }

        const sent = await this.safeSendGroupSignal(peerId, {
            type: 'candidates',
            candidates,
        }, { fatal: false });

        if (!sent) {
            this.schedulePeerReconnect(peerId, 1500);
        }
    },

    async safeHandleGroupSignal(senderId, signalData) {
        const nextQueue = (this.peerSignalQueues.get(senderId) ?? Promise.resolve())
            .catch(() => {})
            .then(() => this.handleGroupSignal(senderId, signalData))
            .catch(() => {
                if (this.callStatus !== 'idle') {
                    this.schedulePeerReconnect(senderId, 1200);
                }
            });

        this.peerSignalQueues.set(senderId, nextQueue);

        await nextQueue;
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

        if (signalData.type === 'candidates' && Array.isArray(signalData.candidates)) {
            for (const candidate of signalData.candidates) {
                await this.handleGroupSignal(senderId, { type: 'candidate', candidate });
            }

            return;
        }

        if (signalData.type === 'renegotiate') {
            if (this.shouldInitiatePeerConnection(senderId)) {
                this.schedulePeerReconnect(senderId, 0);
            }

            return;
        }

        const isDescription = isSessionDescriptionSignal(signalData);

        if (!this.peerConnections.has(senderId) && signalData.type === 'answer') {
            return;
        }

        if (!this.peerConnections.has(senderId) && !isDescription) {
            const signals = this.pendingSignals.get(senderId) ?? [];
            signals.push(signalData);
            this.pendingSignals.set(senderId, signals);
            return;
        }

        const peer = this.peerConnections.has(senderId)
            ? this.peerConnections.get(senderId)
            : this.createPeerConnection(senderId, signalData.type === 'offer' ? false : this.shouldInitiatePeerConnection(senderId));

        if (!peer) {
            return;
        }

        const state = this.peerStates.get(senderId) ?? this.createPeerState(
            senderId,
            peer,
            this.shouldInitiatePeerConnection(senderId),
        );

        if (isDescription) {
            if (signalData.type === 'answer' && peer.signalingState === 'stable') {
                return;
            }

            const readyForOffer = !state.makingOffer
                && (peer.signalingState === 'stable' || state.isSettingRemoteAnswerPending);
            const offerCollision = signalData.type === 'offer' && !readyForOffer;

            state.ignoreOffer = !state.polite && offerCollision;

            if (state.ignoreOffer) {
                return;
            }

            if (offerCollision) {
                await peer.setLocalDescription({ type: 'rollback' });
            }

            const cleanDescription = signalData.sdp
                ? { ...signalData, sdp: stripSdp(signalData.sdp) }
                : signalData;

            state.isSettingRemoteAnswerPending = signalData.type === 'answer';

            try {
                await peer.setRemoteDescription(new RTCSessionDescription(cleanDescription));
            } finally {
                state.isSettingRemoteAnswerPending = false;
            }

            if (this.peerConnections.get(senderId) !== peer) {
                return;
            }

            if (signalData.type === 'offer') {
                const answer = await peer.createAnswer();
                const preferredAnswer = answer.sdp
                    ? { type: answer.type, sdp: this.preferCodecs(answer.sdp) }
                    : answer;

                await peer.setLocalDescription(preferredAnswer);

                if (this.peerConnections.get(senderId) !== peer || !this.callId) {
                    return;
                }

                const localDescription = peer.localDescription ?? preferredAnswer;
                const sent = await this.safeSendGroupSignal(senderId, localDescriptionSignal(localDescription), { fatal: false });

                if (!sent) {
                    this.schedulePeerReconnect(senderId, 1500);
                    return;
                }
            }

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

            const candidate = signalData.type === 'candidate'
                ? signalData.candidate
                : signalData;

            try {
                await peer.addIceCandidate(new RTCIceCandidate(candidate));
            } catch (error) {
                if (!state.ignoreOffer) {
                    throw error;
                }
            }
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

    removeDepartedPeers() {
        const activeParticipantIds = new Set(this.participants);
        const knownPeerIds = new Set([
            ...this.peerConnections.keys(),
            ...this.remoteStreams.keys(),
        ]);

        knownPeerIds.forEach((peerId) => {
            if (peerId !== this.authUserId && !activeParticipantIds.has(peerId)) {
                this.removePeer(peerId);
            }
        });
    },

    refreshRemoteParticipants() {
        const activeParticipantIds = new Set([
            ...this.remoteStreams.keys(),
            ...this.peerConnections.keys(),
        ]);

        this.remoteParticipants = Array.from(activeParticipantIds).map((participantId) => ({
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

        if (
            this.participantFullscreenId !== null
            && !this.remoteParticipants.some((participant) => participant.id === this.participantFullscreenId)
        ) {
            this.participantFullscreenId = null;
        }

        if (this.callStatus === 'active' && this.remoteParticipants.length === 0) {
            this.statusMessage = 'Waiting for others to join...';
        }

        // Wait for Alpine to render x-for nodes, then attach srcObject.
        // Poll up to 60 times x 200 ms = 12 seconds. This is necessary because
        // Alpine processes reactive updates asynchronously and <video> elements
        // for new participants don't exist immediately after remoteParticipants changes.
        const attachSources = (retriesLeft) => {
            this.refreshParticipantVideoSources();

            const allMounted = this.remoteParticipants.every((participant) => {
                const el = document.getElementById(participant.tileElementId);
                return el instanceof HTMLVideoElement && el.srcObject instanceof MediaStream;
            });

            if (!allMounted && retriesLeft > 0) {
                window.setTimeout(() => attachSources(retriesLeft - 1), 200);
            }
        };

        // First tick: let Alpine queue the reactive update
        window.setTimeout(() => attachSources(60), 50);
    },

    refreshParticipantVideoSources() {
        this.remoteStreams = new Map(this.remoteStreams);
        this.remoteVideoActive = new Map(this.remoteVideoActive);

        this.remoteParticipants.forEach((participant) => {
            const stream = this.remoteStreams.get(participant.id) ?? null;

            [participant.tileElementId, participant.thumbnailElementId].forEach((elementId) => {
                const element = document.getElementById(elementId);

                if (element instanceof HTMLVideoElement && element.srcObject !== stream) {
                    element.srcObject = stream;

                    if (stream && element.paused) {
                        element.play().catch(() => {});
                    }
                }
            });
        });

        if (this.localStream) {
            [
                'group-call-local-video',
                'group-call-local-background-video',
                'group-call-local-grid-video',
                'group-call-local-thumbnail-video',
            ].forEach((elementId) => {
                const element = document.getElementById(elementId);

                if (element instanceof HTMLVideoElement && element.srcObject !== this.localStream) {
                    element.srcObject = this.localStream;
                }
            });
        }

        this.refreshSpeakerVideo();
    },

    refreshSpeakerVideo() {
        const speaker = this.speakerParticipant();
        const stream = speaker ? (this.remoteStreams.get(speaker.id) ?? null) : null;
        const element = document.getElementById('group-call-speaker-video');

        if (element instanceof HTMLVideoElement && element.srcObject !== stream) {
            element.srcObject = stream;

            if (stream && element.paused) {
                element.play().catch(() => {});
            }
        }
    },

    removePeer(peerId) {
        const reconnectTimer = this.peerReconnectTimers.get(peerId);

        if (reconnectTimer !== undefined) {
            window.clearTimeout(reconnectTimer);
            this.peerReconnectTimers.delete(peerId);
        }

        this.closePeerConnection(peerId);
        this.peerSignalQueues.delete(peerId);
        this.pendingSignals.delete(peerId);

        this.remoteStreams.delete(peerId);
        this.remoteStreams = new Map(this.remoteStreams);
        this.remoteVideoActive.delete(peerId);
        this.remoteVideoActive = new Map(this.remoteVideoActive);
        this.refreshRemoteParticipants();

        if (this.callStatus === 'active' && this.remoteParticipants.length === 0) {
            this.statusMessage = 'Waiting for others to join...';
        }

        // After a brief wait, do a final reconciliation in case a new stream
        // for the same peer arrived (reconnect scenario)
        window.setTimeout(() => {
            if (!this.peerConnections.has(peerId)) {
                this.refreshRemoteParticipants();
            }
        }, 500);
    },

    cleanupGroupCall(nextStatus = 'idle', message = '') {
        if (this.callStatus === 'ended' && nextStatus === 'ended') {
            return;
        }

        window.sukiRingtone?.stop();
        this.stopCallTimer();
        this.unsubscribeCallChannel();
        this.peerConnections.forEach((peer) => peer.close());
        this.peerConnections.clear();
        this.remoteStreams = new Map();
        this.remoteVideoActive.clear();
        this.remoteVideoActive = new Map();
        this.remoteParticipants = [];
        this.participants = [];
        this.pendingSignals.clear();
        this.peerStates.clear();
        this.peerSignalQueues.clear();
        this.peerReconnectTimers.forEach((timer) => window.clearTimeout(timer));
        this.peerReconnectTimers.clear();
        this.candidateFlushTimers.forEach((timer) => window.clearTimeout(timer));
        this.candidateFlushTimers.clear();
        this.candidateQueues.clear();
        this.speakerParticipantId = null;
        this.participantFullscreenId = null;
        this.lastParticipantTap = { id: null, at: 0 };
        this.iceServers = null;
        this.iceTransportPolicy = 'all';

        if (this.localStream !== null) {
            this.localStream.getTracks().forEach((track) => track.stop());
        }

        this.screenTrack?.stop();
        this.localStream = null;
        this.callId = null;
        this.callStatus = nextStatus;
        this.statusMessage = message;
        this.hasMicrophone = true;
        this.hasCamera = true;
        this.microphoneMuted = false;
        this.cameraDisabled = false;
        this.screenSharing = false;
        this.screenTrack = null;
        this.cameraTrackBeforeShare = null;
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
                    this.endingCall = false;
                }
            }, videoCallResetDelay);
        }

        if (nextStatus === 'idle') {
            this.statusMessage = '';
            this.endingCall = false;
        }

        this.releaseActiveGroupCall();
    },

    teardownRealtimeListeners() {
        this.unsubscribeCallChannel();

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
            allowRealtimeUnavailable = false,
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

        if (!response.ok || (!allowRealtimeUnavailable && payload?.realtime_available === false)) {
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

    syncLocalVideoSources() {
        window.setTimeout(() => {
            [
                'group-call-local-video',
                'group-call-local-background-video',
                'group-call-local-grid-video',
                'group-call-local-thumbnail-video',
            ].forEach((elementId) => this.setVideoSource(elementId, this.localStream));
        }, 0);
    },
});
