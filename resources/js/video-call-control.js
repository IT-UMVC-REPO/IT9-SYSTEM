export const conversationVideoCallControl = () => ({
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
