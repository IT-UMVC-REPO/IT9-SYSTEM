const documentPipSize = { width: 380, height: 280 };
const overlaySize = { width: 320, height: 220 };
const overlayPadding = 12;

const isMediaStream = (stream) => typeof MediaStream !== 'undefined' && stream instanceof MediaStream;

const hasLiveTracks = (stream) => (
    isMediaStream(stream)
    && stream.getTracks().some((track) => track.readyState === 'live')
);

const hasLiveVideo = (stream) => (
    isMediaStream(stream)
    && stream.getVideoTracks().some((track) => track.readyState === 'live' && track.enabled)
);

const streamEntriesFrom = (streams) => {
    if (streams instanceof Map) {
        return Array.from(streams.entries());
    }

    if (streams && typeof streams === 'object') {
        return Object.entries(streams);
    }

    return [];
};

const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

const initialOverlayPosition = () => ({
    x: Math.max(overlayPadding, window.innerWidth - overlaySize.width - 24),
    y: Math.max(overlayPadding, window.innerHeight - overlaySize.height - 24),
});

export const createPipManager = () => ({
    active: false,
    pipWindow: null,
    mode: 'overlay',
    minimized: true,
    position: initialOverlayPosition(),
    participants: [],
    localStream: null,
    remoteStreams: {},
    remoteEntries: [],
    activeCall: null,
    callKind: 'direct',
    title: 'Call',
    pagehideBehavior: 'overlay',
    dragging: false,
    dragOffset: { x: 0, y: 0 },
    reducedMotion: false,
    initialized: false,

    init() {
        if (this.initialized) {
            return;
        }

        this.initialized = true;
        this.reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;

        window.matchMedia?.('(prefers-reduced-motion: reduce)').addEventListener?.('change', (event) => {
            this.reducedMotion = event.matches;
        });

        document.addEventListener('livewire:navigating', () => {
            if (this.active && this.mode !== 'document-pip') {
                this.switchToOverlay();
            }
        });

        window.addEventListener('resize', () => this.clampPosition());
    },

    supportsVideoCalling() {
        return Boolean(
            navigator.mediaDevices?.getUserMedia
            && window.RTCPeerConnection
        );
    },

    supportsDocumentPip() {
        return Boolean(
            window.documentPictureInPicture
            && !this.isTouchDevice()
            && this.supportsVideoCalling()
        );
    },

    isTouchDevice() {
        return navigator.maxTouchPoints > 1
            || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    },

    async enter(call, options = {}) {
        this.attachCall(call, options);

        if (!this.supportsVideoCalling()) {
            call.statusMessage = 'Your browser doesn\'t support video calls.';
            return;
        }

        if (this.supportsDocumentPip() && options.forceOverlay !== true) {
            try {
                await this.openDocumentPip();
                return;
            } catch {
                this.switchToOverlay();
                return;
            }
        }

        this.switchToOverlay();
    },

    enterOverlay(call = this.activeCall, options = {}) {
        if (call) {
            this.attachCall(call, { ...options, forceOverlay: true });
        }

        this.switchToOverlay();
    },

    attachCall(call, options = {}) {
        if (!call) {
            return;
        }

        this.activeCall = call;
        this.callKind = options.kind ?? (call.groupId ? 'group' : 'direct');
        this.title = options.title ?? call.groupName ?? call.otherUserName ?? 'Call';
        this.pagehideBehavior = options.pagehideBehavior ?? this.pagehideBehavior;
        this.sync(call);
    },

    sync(call = this.activeCall) {
        if (call && call !== this.activeCall) {
            this.activeCall = call;
        }

        if (!this.activeCall) {
            return;
        }

        this.localStream = this.activeCall.localStream ?? null;
        this.remoteEntries = this.collectRemoteEntries();
        this.remoteStreams = Object.fromEntries(this.remoteEntries.map((entry) => [entry.id, entry.stream]));
        this.participants = this.collectParticipants();

        if (this.mode === 'document-pip') {
            this.renderDocumentPip();
        }
    },

    collectRemoteEntries() {
        if (!this.activeCall) {
            return [];
        }

        if (this.activeCall.remoteStreams instanceof Map) {
            return streamEntriesFrom(this.activeCall.remoteStreams)
                .filter(([, stream]) => hasLiveTracks(stream))
                .map(([id, stream]) => ({
                    id: String(id),
                    label: this.participantLabel(id),
                    stream,
                    hasVideo: hasLiveVideo(stream),
                }));
        }

        const directStream = this.activeCall.persistentRemoteStream
            ?? document.getElementById('conversation-call-remote-video')?.srcObject
            ?? null;

        if (!hasLiveTracks(directStream)) {
            return [];
        }

        return [{
            id: String(this.activeCall.otherUserId ?? 'remote'),
            label: this.activeCall.otherUserName ?? 'Participant',
            stream: directStream,
            hasVideo: hasLiveVideo(directStream),
        }];
    },

    collectParticipants() {
        const localParticipant = {
            id: 'local',
            label: 'You',
            stream: this.localStream,
            local: true,
            hasVideo: hasLiveVideo(this.localStream),
        };

        return [
            ...(isMediaStream(this.localStream) ? [localParticipant] : []),
            ...this.remoteEntries,
        ];
    },

    participantLabel(id) {
        if (!this.activeCall) {
            return 'Participant';
        }

        if (typeof this.activeCall.participantName === 'function') {
            return this.activeCall.participantName(Number(id));
        }

        if (String(id) === String(this.activeCall.otherUserId)) {
            return this.activeCall.otherUserName ?? 'Participant';
        }

        return 'Participant';
    },

    async openDocumentPip() {
        if (!this.supportsDocumentPip()) {
            throw new Error('Document Picture-in-Picture is not supported.');
        }

        if (this.pipWindow && !this.pipWindow.closed) {
            this.mode = 'document-pip';
            this.active = true;
            this.minimized = true;
            this.renderDocumentPip();
            return;
        }

        this.pipWindow = await window.documentPictureInPicture.requestWindow(documentPipSize);
        this.mode = 'document-pip';
        this.active = true;
        this.minimized = true;
        this.injectDocumentPip();
        this.renderDocumentPip();

        this.pipWindow.addEventListener('pagehide', () => {
            const closedCall = this.activeCall;
            this.pipWindow = null;

            if (!this.active || !closedCall) {
                return;
            }

            if (this.pagehideBehavior === 'hangup') {
                void this.endCall();
                return;
            }

            this.enterOverlay(closedCall, { forceOverlay: true });
        }, { once: true });
    },

    injectDocumentPip() {
        const doc = this.pipWindow?.document;

        if (!doc) {
            return;
        }

        doc.head.innerHTML = '';
        document.querySelectorAll('link[rel="stylesheet"]').forEach((link) => {
            doc.head.appendChild(link.cloneNode(true));
        });

        const style = doc.createElement('style');
        style.textContent = `
            :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; overflow: hidden; background: #0a0a0a; color: white; }
            .pip-shell { display: grid; grid-template-rows: auto 1fr auto; min-height: 100vh; gap: 8px; padding: 10px; background: linear-gradient(180deg, #171717, #050505); }
            .pip-header { display: flex; align-items: center; justify-content: space-between; gap: 8px; min-width: 0; }
            .pip-title { min-width: 0; }
            .pip-title strong { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; }
            .pip-title span { display: block; margin-top: 2px; color: rgba(255,255,255,.56); font-size: 11px; }
            .pip-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; min-height: 0; }
            .pip-tile { position: relative; min-height: 86px; overflow: hidden; border-radius: 12px; background: #171717; }
            .pip-tile video { width: 100%; height: 100%; object-fit: cover; background: #111827; }
            .pip-empty, .pip-camera-off { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; padding: 10px; text-align: center; color: rgba(255,255,255,.72); font-size: 12px; font-weight: 700; }
            .pip-label { position: absolute; left: 7px; right: 7px; bottom: 7px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; border-radius: 999px; background: rgba(0,0,0,.52); padding: 4px 7px; font-size: 10px; font-weight: 700; }
            .pip-overflow { display: flex; align-items: center; justify-content: center; border-radius: 12px; background: rgba(255,255,255,.08); color: rgba(255,255,255,.76); font-size: 18px; font-weight: 800; }
            .pip-controls { display: flex; align-items: center; justify-content: center; gap: 8px; }
            button { display: inline-flex; height: 38px; min-width: 38px; align-items: center; justify-content: center; border: 0; border-radius: 999px; background: rgba(255,255,255,.14); color: white; cursor: pointer; font-size: 11px; font-weight: 800; }
            button[aria-pressed="true"] { background: rgba(239,68,68,.28); color: #fca5a5; }
            button[data-end] { background: #ef4444; color: white; }
        `;
        doc.head.appendChild(style);
        doc.body.innerHTML = `
            <main class="pip-shell">
                <header class="pip-header">
                    <div class="pip-title">
                        <strong data-pip-title></strong>
                        <span data-pip-status></span>
                    </div>
                    <button type="button" data-action="overlay" title="Return to tab">Tab</button>
                </header>
                <section class="pip-grid" data-pip-grid></section>
                <footer class="pip-controls">
                    <button type="button" data-action="microphone" title="Mute microphone">Mic</button>
                    <button type="button" data-action="camera" title="Turn camera off">Cam</button>
                    <button type="button" data-action="screen" title="Share screen">Share</button>
                    <button type="button" data-end data-action="end" title="End call">End</button>
                </footer>
            </main>
        `;

        doc.querySelectorAll('[data-action]').forEach((button) => {
            button.addEventListener('click', () => {
                this.handleAction(button.getAttribute('data-action'));
            });
        });
    },

    renderDocumentPip() {
        const doc = this.pipWindow?.document;

        if (!doc || this.pipWindow.closed) {
            return;
        }

        const title = doc.querySelector('[data-pip-title]');
        const status = doc.querySelector('[data-pip-status]');
        const grid = doc.querySelector('[data-pip-grid]');

        if (title) {
            title.textContent = this.title;
        }

        if (status) {
            status.textContent = this.statusLabel();
        }

        this.syncDocumentControls(doc);

        if (!grid) {
            return;
        }

        const entries = this.visibleEntries();
        const overflow = Math.max(0, this.remoteEntries.length - entries.length);
        const activeIds = new Set(entries.map((entry) => entry.id));

        grid.querySelectorAll('[data-pip-tile]').forEach((tile) => {
            if (!activeIds.has(tile.getAttribute('data-pip-tile'))) {
                tile.remove();
            }
        });

        grid.querySelectorAll('[data-pip-empty], [data-pip-overflow]').forEach((element) => element.remove());

        if (entries.length === 0) {
            const empty = doc.createElement('div');
            empty.className = 'pip-tile';
            empty.setAttribute('data-pip-empty', 'true');
            empty.style.gridColumn = '1 / -1';
            empty.innerHTML = '<div class="pip-empty">Waiting for others to join...</div>';
            grid.appendChild(empty);
            return;
        }

        entries.forEach((entry) => {
            let tile = grid.querySelector(`[data-pip-tile="${entry.id}"]`);

            if (!tile) {
                tile = doc.createElement('div');
                tile.className = 'pip-tile';
                tile.setAttribute('data-pip-tile', entry.id);
                tile.innerHTML = `
                    <video autoplay playsinline></video>
                    <div class="pip-camera-off" data-pip-camera-off hidden>Camera off</div>
                    <span class="pip-label" data-pip-label></span>
                `;
                grid.appendChild(tile);
            }

            this.bindVideo(tile.querySelector('video'), entry.stream);

            const cameraOff = tile.querySelector('[data-pip-camera-off]');
            if (cameraOff) {
                cameraOff.hidden = entry.hasVideo;
            }

            const label = tile.querySelector('[data-pip-label]');
            if (label) {
                label.textContent = entry.label;
            }
        });

        if (overflow > 0) {
            const overflowBadge = doc.createElement('div');
            overflowBadge.className = 'pip-overflow';
            overflowBadge.setAttribute('data-pip-overflow', 'true');
            overflowBadge.textContent = `+${overflow}`;
            grid.appendChild(overflowBadge);
        }
    },

    syncDocumentControls(doc) {
        const microphone = doc.querySelector('[data-action="microphone"]');
        const camera = doc.querySelector('[data-action="camera"]');
        const screen = doc.querySelector('[data-action="screen"]');

        microphone?.setAttribute('aria-pressed', this.activeCall?.microphoneMuted ? 'true' : 'false');
        camera?.setAttribute('aria-pressed', this.activeCall?.cameraDisabled ? 'true' : 'false');
        screen?.setAttribute('aria-pressed', this.activeCall?.screenSharing ? 'true' : 'false');
    },

    visibleEntries() {
        return this.remoteEntries.slice(0, 4);
    },

    overflowCount() {
        return Math.max(0, this.remoteEntries.length - this.visibleEntries().length);
    },

    overlayEntries() {
        if (this.remoteEntries.length > 0) {
            return this.visibleEntries();
        }

        if (isMediaStream(this.localStream)) {
            return [{
                id: 'local',
                label: 'You',
                stream: this.localStream,
                hasVideo: hasLiveVideo(this.localStream),
                local: true,
            }];
        }

        return [];
    },

    statusLabel() {
        if (!this.activeCall) {
            return 'Active call';
        }

        if (typeof this.activeCall.callStatusLabel === 'function') {
            return this.activeCall.callStatusLabel();
        }

        return this.activeCall.statusMessage || 'Active call';
    },

    switchToOverlay() {
        this.active = true;
        this.mode = 'overlay';
        this.minimized = true;
        this.clampPosition();
    },

    expand() {
        this.minimized = false;
    },

    minimize() {
        this.minimized = true;
        this.snapToNearestCorner();
    },

    hide(call = null) {
        if (call !== null && call !== this.activeCall) {
            return;
        }

        this.active = false;
        this.minimized = true;

        if (this.pipWindow && !this.pipWindow.closed) {
            this.pipWindow.close();
        }

        this.pipWindow = null;
    },

    async closePipWindow() {
        if (this.pipWindow && !this.pipWindow.closed) {
            this.pipWindow.close();
        }

        this.pipWindow = null;
    },

    handleAction(action) {
        if (action === 'microphone') {
            this.toggleMicrophone();
        } else if (action === 'camera') {
            this.toggleCamera();
        } else if (action === 'screen') {
            void this.toggleScreenShare();
        } else if (action === 'end') {
            void this.endCall();
        } else if (action === 'overlay') {
            this.switchToOverlay();
            void this.closePipWindow();
        }
    },

    toggleMicrophone() {
        this.activeCall?.toggleMicrophone?.();
        this.sync();
    },

    toggleCamera() {
        this.activeCall?.toggleCamera?.();
        this.sync();
    },

    async toggleScreenShare() {
        await this.activeCall?.toggleScreenShare?.();
        this.sync();
    },

    async endCall() {
        if (!this.activeCall) {
            this.hide();
            return;
        }

        if (typeof this.activeCall.endCall === 'function') {
            await this.activeCall.endCall('Call ended.');
        } else if (typeof this.activeCall.leaveCall === 'function') {
            await this.activeCall.leaveCall();
        }

        this.hide();
    },

    bindVideo(video, stream, muted = false) {
        if (!(video instanceof HTMLVideoElement)) {
            return;
        }

        if (video.srcObject !== stream) {
            video.srcObject = stream;
        }

        video.muted = muted;
        video.playsInline = true;
        video.play().catch(() => {});
    },

    overlayStyle() {
        if (!this.minimized) {
            return '';
        }

        return `left: ${this.position.x}px; top: ${this.position.y}px;`;
    },

    startDrag(event, element) {
        if (!this.minimized) {
            return;
        }

        const point = event.touches?.[0] ?? event;
        const rect = element.getBoundingClientRect();

        this.dragging = true;
        this.dragOffset = {
            x: point.clientX - rect.left,
            y: point.clientY - rect.top,
        };
    },

    moveDrag(event, element) {
        if (!this.dragging || !this.minimized) {
            return;
        }

        if (event.cancelable) {
            event.preventDefault();
        }

        const point = event.touches?.[0] ?? event;
        const rect = element.getBoundingClientRect();

        this.position = {
            x: clamp(point.clientX - this.dragOffset.x, overlayPadding, window.innerWidth - rect.width - overlayPadding),
            y: clamp(point.clientY - this.dragOffset.y, overlayPadding, window.innerHeight - rect.height - overlayPadding),
        };
    },

    endDrag(element) {
        if (!this.dragging) {
            return;
        }

        this.dragging = false;
        this.snapToNearestCorner(element);
    },

    snapToNearestCorner(element = null) {
        const rect = element?.getBoundingClientRect?.() ?? {
            width: overlaySize.width,
            height: overlaySize.height,
        };
        const corners = [
            { x: overlayPadding, y: overlayPadding },
            { x: window.innerWidth - rect.width - overlayPadding, y: overlayPadding },
            { x: overlayPadding, y: window.innerHeight - rect.height - overlayPadding },
            { x: window.innerWidth - rect.width - overlayPadding, y: window.innerHeight - rect.height - overlayPadding },
        ].map((corner) => ({
            x: clamp(corner.x, overlayPadding, window.innerWidth - rect.width - overlayPadding),
            y: clamp(corner.y, overlayPadding, window.innerHeight - rect.height - overlayPadding),
        }));

        const nearestCorner = corners
            .map((corner) => ({
                ...corner,
                distance: Math.hypot(corner.x - this.position.x, corner.y - this.position.y),
            }))
            .sort((first, second) => first.distance - second.distance)[0];

        this.position = {
            x: nearestCorner.x,
            y: nearestCorner.y,
        };
    },

    clampPosition() {
        this.position = {
            x: clamp(this.position.x, overlayPadding, Math.max(overlayPadding, window.innerWidth - overlaySize.width - overlayPadding)),
            y: clamp(this.position.y, overlayPadding, Math.max(overlayPadding, window.innerHeight - overlaySize.height - overlayPadding)),
        };
    },
});

export function registerPipManager() {
    window.sukiPipManager ??= createPipManager();

    const registerStore = () => {
        if (!window.Alpine?.store) {
            return;
        }

        window.Alpine.store('pipManager', window.sukiPipManager);
        window.sukiPipManager.init();
    };

    if (window.Alpine?.store) {
        registerStore();
    }

    document.addEventListener('alpine:init', registerStore, { once: true });
}

registerPipManager();
