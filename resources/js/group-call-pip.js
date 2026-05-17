export const sukiGroupCallPip = {
    activeCall: null,

    attachCall(call) {
        this.activeCall = call;
        window.__activeGroupCall = call;

        if (window.sukiPipManager?.active) {
            window.sukiPipManager.attachCall(call, {
                kind: 'group',
                title: call.groupName ?? 'Group call',
            });
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
        return Boolean(window.sukiPipManager?.active && window.sukiPipManager?.activeCall === call);
    },

    enter(call, returnUrl = null) {
        this.attachCall(call);
        call.returnUrl = returnUrl ?? call.returnUrl ?? window.location.href;

        return window.sukiPipManager?.enter(call, {
            kind: 'group',
            title: call.groupName ?? 'Group call',
            returnUrl: call.returnUrl,
            exitUrl: call.routes?.inbox ?? null,
        });
    },

    sync(call = this.activeCall) {
        if (!call) {
            return;
        }

        window.sukiPipManager?.sync(call);
    },

    hide(call = null) {
        window.sukiPipManager?.hide(call);
    },

    exitToConversation() {
        const targetUrl = this.activeCall?.groupConversationUrl?.() ?? this.activeCall?.returnUrl ?? window.location.href;

        window.sukiPipManager?.returnToCall?.();

        if (!window.sukiPipManager?.navigateToUrl) {
            if (window.Livewire && typeof window.Livewire.navigate === 'function') {
                window.Livewire.navigate(targetUrl);
                return;
            }

            window.location.href = targetUrl;
        }
    },
};
