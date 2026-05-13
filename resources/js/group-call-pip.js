const overlayId = 'suki-group-call-pip';

const activeTracks = (stream) => (
    stream instanceof MediaStream
    && stream.getTracks().some((track) => track.readyState === 'live')
);

const activeVideoTracks = (stream) => (
    stream instanceof MediaStream
    && stream.getVideoTracks().some((track) => track.readyState === 'live')
);

export const sukiGroupCallPip = {
    active: false,
    activeCall: null,
    overlay: null,
    grid: null,
    status: null,
    micButton: null,
    cameraButton: null,
    syncTimer: null,
    returnUrl: null,
    position: { right: 24, bottom: 24 },

    attachCall(call) {
        this.activeCall = call;
        window.__activeGroupCall = call;

        if (this.active) {
            this.sync(call);
        }
    },

    releaseCall(call) {
        if (this.activeCall !== call && window.__activeGroupCall !== call) {
            return;
        }

        this.hide(call);
        this.activeCall = null;

        if (window.__activeGroupCall === call) {
            window.__activeGroupCall = null;
        }
    },

    isActiveFor(call) {
        return this.active && this.activeCall === call;
    },

    enter(call, returnUrl = null) {
        this.attachCall(call);
        this.returnUrl = returnUrl ?? this.returnUrl ?? window.location.href;
        this.active = true;
        this.ensureOverlay();
        this.overlay.hidden = false;
        this.applyPosition();
        this.sync(call);
        this.startSyncTimer();
    },

    hide(call = null) {
        if (call !== null && this.activeCall !== call) {
            return;
        }

        this.active = false;
        this.stopSyncTimer();

        if (this.overlay) {
            this.overlay.hidden = true;
        }
    },

    exitToConversation() {
        const targetUrl = this.activeCall?.groupConversationUrl?.() ?? this.returnUrl ?? window.location.href;

        this.hide(this.activeCall);

        if (window.Livewire && typeof window.Livewire.navigate === 'function') {
            window.Livewire.navigate(targetUrl);
            return;
        }

        window.location.href = targetUrl;
    },

    sync(call = null) {
        if (call !== null && call !== this.activeCall) {
            this.attachCall(call);
        }

        if (!this.active || !this.activeCall) {
            return;
        }

        this.ensureOverlay();
        this.syncControls();
        this.syncVideos();
    },

    ensureOverlay() {
        if (this.overlay) {
            return;
        }

        this.overlay = document.getElementById(overlayId) ?? document.createElement('section');
        this.overlay.id = overlayId;
        this.overlay.hidden = true;
        this.overlay.setAttribute('aria-label', 'Group call mini window');
        this.overlay.style.cssText = [
            'position: fixed',
            'z-index: 90',
            'width: min(360px, calc(100vw - 24px))',
            'max-height: min(480px, calc(100vh - 24px))',
            'display: flex',
            'flex-direction: column',
            'gap: 10px',
            'padding: 10px',
            'border-radius: 18px',
            'background: rgba(10, 10, 10, 0.92)',
            'color: white',
            'box-shadow: 0 24px 70px rgba(0, 0, 0, 0.35)',
            'backdrop-filter: blur(18px)',
            'border: 1px solid rgba(255, 255, 255, 0.12)',
        ].join(';');

        this.overlay.innerHTML = `
            <div data-pip-drag-handle style="display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:grab;user-select:none;">
                <div style="min-width:0;">
                    <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.58);">Group call</p>
                    <p data-pip-status style="margin:2px 0 0;font-size:13px;font-weight:700;color:white;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">Active call</p>
                </div>
                <button type="button" data-pip-exit style="display:flex;height:34px;min-width:34px;align-items:center;justify-content:center;border-radius:999px;border:0;background:rgba(255,255,255,.12);color:white;cursor:pointer;" aria-label="Exit picture in picture">
                    <i class="fa-solid fa-window-restore" aria-hidden="true"></i>
                </button>
            </div>
            <div data-pip-grid style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;min-height:150px;overflow:hidden;"></div>
            <div style="display:flex;align-items:center;justify-content:center;gap:10px;">
                <button type="button" data-pip-mic style="display:flex;height:42px;width:42px;align-items:center;justify-content:center;border-radius:999px;border:0;background:rgba(255,255,255,.16);color:white;cursor:pointer;" aria-label="Toggle microphone">
                    <i class="fa-solid fa-microphone" aria-hidden="true"></i>
                </button>
                <button type="button" data-pip-camera style="display:flex;height:42px;width:42px;align-items:center;justify-content:center;border-radius:999px;border:0;background:rgba(255,255,255,.16);color:white;cursor:pointer;" aria-label="Toggle camera">
                    <i class="fa-solid fa-video" aria-hidden="true"></i>
                </button>
            </div>
        `;

        document.body.appendChild(this.overlay);

        this.grid = this.overlay.querySelector('[data-pip-grid]');
        this.status = this.overlay.querySelector('[data-pip-status]');
        this.micButton = this.overlay.querySelector('[data-pip-mic]');
        this.cameraButton = this.overlay.querySelector('[data-pip-camera]');

        this.overlay.querySelector('[data-pip-exit]')?.addEventListener('click', () => this.exitToConversation());
        this.micButton?.addEventListener('click', () => {
            this.activeCall?.toggleMicrophone?.();
            this.syncControls();
        });
        this.cameraButton?.addEventListener('click', () => {
            this.activeCall?.toggleCamera?.();
            this.syncControls();
        });

        this.overlay.querySelector('[data-pip-drag-handle]')?.addEventListener('pointerdown', (event) => this.startDrag(event));
        window.addEventListener('resize', () => this.applyPosition());
    },

    syncControls() {
        if (!this.activeCall) {
            return;
        }

        if (this.status) {
            this.status.textContent = this.activeCall.statusMessage || this.activeCall.groupName || 'Active call';
        }
        this.updateControlButton(this.micButton, this.activeCall.microphoneMuted, 'fa-microphone', 'fa-microphone-slash');
        this.updateControlButton(this.cameraButton, this.activeCall.cameraDisabled, 'fa-video', 'fa-video-slash');
    },

    updateControlButton(button, isDisabled, enabledIcon, disabledIcon) {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        button.style.background = isDisabled ? 'rgba(239, 68, 68, .28)' : 'rgba(255, 255, 255, .16)';
        button.style.color = isDisabled ? '#fca5a5' : '#ffffff';
        button.setAttribute('aria-pressed', isDisabled ? 'true' : 'false');

        const icon = button.querySelector('i');

        if (icon) {
            icon.className = `fa-solid ${isDisabled ? disabledIcon : enabledIcon}`;
        }
    },

    syncVideos() {
        if (!this.grid || !this.activeCall) {
            return;
        }

        const entries = Array.from(this.activeCall.remoteStreams?.entries?.() ?? [])
            .filter(([, stream]) => activeTracks(stream));
        const activeIds = new Set(entries.map(([participantId]) => String(participantId)));

        this.grid.querySelectorAll('[data-pip-tile]').forEach((tile) => {
            if (!activeIds.has(tile.getAttribute('data-pip-tile'))) {
                tile.remove();
            }
        });

        if (entries.length === 0) {
            this.grid.innerHTML = `
                <div data-pip-empty style="grid-column:1/-1;display:flex;min-height:150px;align-items:center;justify-content:center;border-radius:14px;background:rgba(255,255,255,.08);padding:16px;text-align:center;font-size:13px;font-weight:700;color:rgba(255,255,255,.72);">
                    Waiting for others to join...
                </div>
            `;
            return;
        }

        this.grid.querySelectorAll('[data-pip-empty]').forEach((emptyState) => emptyState.remove());

        entries.forEach(([participantId, stream]) => {
            const key = String(participantId);
            let tile = this.grid.querySelector(`[data-pip-tile="${key}"]`);

            if (!tile) {
                tile = document.createElement('div');
                tile.setAttribute('data-pip-tile', key);
                tile.style.cssText = 'position:relative;min-height:118px;overflow:hidden;border-radius:14px;background:#171717;';
                tile.innerHTML = `
                    <video autoplay playsinline style="height:100%;width:100%;object-fit:cover;background:#111827;"></video>
                    <div data-pip-camera-off style="position:absolute;inset:0;display:none;align-items:center;justify-content:center;background:#171717;color:rgba(255,255,255,.72);font-size:12px;font-weight:700;">Camera off</div>
                    <span data-pip-name style="position:absolute;left:8px;right:8px;bottom:7px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;border-radius:999px;background:rgba(0,0,0,.48);padding:4px 8px;font-size:11px;font-weight:700;color:white;"></span>
                `;
                this.grid.appendChild(tile);
            }

            const video = tile.querySelector('video');

            if (video instanceof HTMLVideoElement && video.srcObject !== stream) {
                video.srcObject = stream;
                video.play().catch(() => {});
            }

            const cameraOff = tile.querySelector('[data-pip-camera-off]');

            if (cameraOff instanceof HTMLElement) {
                cameraOff.style.display = activeVideoTracks(stream) ? 'none' : 'flex';
            }

            const name = tile.querySelector('[data-pip-name]');

            if (name) {
                name.textContent = this.activeCall.participantName?.(Number(participantId)) ?? 'Participant';
            }
        });
    },

    startSyncTimer() {
        this.stopSyncTimer();
        this.syncTimer = window.setInterval(() => this.sync(), 1000);
    },

    stopSyncTimer() {
        if (this.syncTimer !== null) {
            window.clearInterval(this.syncTimer);
            this.syncTimer = null;
        }
    },

    startDrag(event) {
        if (!(event.currentTarget instanceof HTMLElement)) {
            return;
        }

        const startX = event.clientX;
        const startY = event.clientY;
        const startRight = this.position.right;
        const startBottom = this.position.bottom;

        const move = (moveEvent) => {
            this.position = {
                right: startRight - (moveEvent.clientX - startX),
                bottom: startBottom - (moveEvent.clientY - startY),
            };
            this.applyPosition();
        };

        const stop = () => {
            document.removeEventListener('pointermove', move);
            document.removeEventListener('pointerup', stop);
        };

        document.addEventListener('pointermove', move);
        document.addEventListener('pointerup', stop, { once: true });
    },

    applyPosition() {
        if (!this.overlay) {
            return;
        }

        const rect = this.overlay.getBoundingClientRect();
        const maxRight = Math.max(12, window.innerWidth - rect.width - 12);
        const maxBottom = Math.max(12, window.innerHeight - rect.height - 12);

        this.position = {
            right: Math.min(maxRight, Math.max(12, this.position.right)),
            bottom: Math.min(maxBottom, Math.max(12, this.position.bottom)),
        };

        this.overlay.style.right = `${this.position.right}px`;
        this.overlay.style.bottom = `${this.position.bottom}px`;
    },
};
