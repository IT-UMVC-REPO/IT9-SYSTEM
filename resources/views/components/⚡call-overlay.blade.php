<?php

use Livewire\Component;

new class extends Component
{
    public function placeholder(): string
    {
        return '';
    }
};
?>

<div
    x-data
    x-cloak
    x-show="$store.pipManager?.shouldShowOverlay()"
    x-transition.opacity
    x-on:mousemove.window="$store.pipManager?.moveDrag($event, $el)"
    x-on:mouseup.window="$store.pipManager?.endDrag($el)"
    x-on:touchmove.window="$store.pipManager?.moveDrag($event, $el)"
    x-on:touchend.window="$store.pipManager?.endDrag($el)"
    x-on:click="$store.pipManager?.minimized && ! $store.pipManager?.dragging ? $store.pipManager.expand() : null"
    x-bind:style="$store.pipManager?.minimized ? $store.pipManager.overlayStyle() : ''"
    x-bind:class="[
        $store.pipManager?.minimized
            ? 'h-[120px] min-h-[90px] w-[160px] max-w-[calc(100vw-24px)] cursor-grab rounded-2xl border border-white/15 bg-neutral-950/80 p-2 opacity-80 shadow-2xl shadow-black/40 backdrop-blur-xl active:cursor-grabbing sm:h-[180px] sm:w-[260px]'
            : 'inset-0 h-auto w-auto rounded-none bg-neutral-950 p-3 opacity-100 sm:p-5',
        $store.pipManager?.reducedMotion ? '!transition-none' : 'transition-all duration-200 ease-out',
    ]"
    class="fixed z-[1000] overflow-hidden text-white"
    style="display: none;"
    role="dialog"
    aria-label="{{ __('Active call') }}"
>
    <section class="flex h-full min-h-0 flex-col gap-2">
        <header
            x-on:mousedown.prevent="$store.pipManager?.startDrag($event, $el.closest('[role=dialog]'))"
            x-on:touchstart.prevent="$store.pipManager?.startDrag($event, $el.closest('[role=dialog]'))"
            class="flex shrink-0 items-center justify-between gap-2"
        >
            <div class="min-w-0">
                <p class="truncate text-xs font-semibold uppercase tracking-[0.16em] text-white/55" x-text="$store.pipManager?.callKind === 'group' ? @js(__('Group call')) : @js(__('Video call'))"></p>
                <p class="truncate text-sm font-semibold text-white" x-text="$store.pipManager?.title"></p>
            </div>

            <div class="flex shrink-0 items-center gap-1">
                <button
                    type="button"
                    x-show="! $store.pipManager?.minimized"
                    x-on:click.stop="$store.pipManager?.minimize()"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                    aria-label="{{ __('Minimize call') }}"
                    title="{{ __('Minimize call') }}"
                >
                    <flux:icon.minus variant="micro" />
                </button>
                <button
                    type="button"
                    x-show="$store.pipManager?.minimized"
                    x-on:click.stop="$store.pipManager?.expand()"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                    aria-label="{{ __('Expand call') }}"
                    title="{{ __('Expand call') }}"
                >
                    <flux:icon.arrows-pointing-out variant="micro" />
                </button>
            </div>
        </header>

        <div
            class="grid min-h-0 flex-1 gap-2"
            x-bind:class="$store.pipManager?.minimized ? 'grid-cols-1' : 'grid-cols-1 sm:grid-cols-2'"
        >
            <template x-for="entry in $store.pipManager?.overlayEntries() ?? []" :key="entry.id">
                <div class="relative min-h-0 overflow-hidden rounded-xl bg-neutral-900">
                    <video
                        autoplay
                        playsinline
                        x-effect="$store.pipManager?.bindVideo($el, entry.stream, entry.local === true)"
                        x-bind:class="entry.local ? 'scale-x-[-1]' : ''"
                        class="h-full w-full object-cover"
                    ></video>
                    <div
                        x-show="! entry.hasVideo"
                        class="absolute inset-0 flex items-center justify-center bg-neutral-900 px-3 text-center text-xs font-semibold text-white/70"
                    >
                        {{ __('Camera off') }}
                    </div>
                    <span
                        x-show="! $store.pipManager?.minimized"
                        class="absolute bottom-2 left-2 right-2 truncate rounded-full bg-black/55 px-2 py-1 text-xs font-semibold text-white"
                        x-text="entry.label"
                    ></span>
                </div>
            </template>

            <div
                x-show="($store.pipManager?.overlayEntries() ?? []).length === 0"
                class="flex min-h-0 items-center justify-center rounded-xl bg-white/10 px-4 text-center text-sm font-semibold text-white/70"
            >
                {{ __('Waiting for others to join...') }}
            </div>

            <div
                x-show="! $store.pipManager?.minimized && $store.pipManager?.overflowCount() > 0"
                class="flex min-h-0 items-center justify-center rounded-xl bg-white/10 text-2xl font-bold text-white/80"
                x-text="'+' + $store.pipManager?.overflowCount()"
            ></div>
        </div>

        <footer
            x-show="! $store.pipManager?.minimized"
            class="flex shrink-0 items-center justify-center gap-2"
        >
            <button
                type="button"
                x-on:click.stop="$store.pipManager?.toggleMicrophone()"
                x-bind:class="$store.pipManager?.activeCall?.microphoneMuted ? 'bg-red-500/20 text-red-300' : 'bg-white/10 text-white hover:bg-white/20'"
                class="inline-flex h-11 w-11 items-center justify-center rounded-full transition"
                aria-label="{{ __('Toggle microphone') }}"
            >
                <flux:icon.microphone variant="mini" />
            </button>
            <button
                type="button"
                x-on:click.stop="$store.pipManager?.toggleCamera()"
                x-bind:class="$store.pipManager?.activeCall?.cameraDisabled ? 'bg-red-500/20 text-red-300' : 'bg-white/10 text-white hover:bg-white/20'"
                class="inline-flex h-11 w-11 items-center justify-center rounded-full transition"
                aria-label="{{ __('Toggle camera') }}"
            >
                <template x-if="! $store.pipManager?.activeCall?.cameraDisabled"><flux:icon.video-camera variant="mini" /></template>
                <template x-if="$store.pipManager?.activeCall?.cameraDisabled"><flux:icon.video-camera-slash variant="mini" /></template>
            </button>
            <button
                type="button"
                x-on:click.stop="$store.pipManager?.toggleScreenShare()"
                x-bind:class="$store.pipManager?.activeCall?.screenSharing ? 'bg-[var(--brand-600)] text-white' : 'bg-white/10 text-white hover:bg-white/20'"
                class="inline-flex h-11 w-11 items-center justify-center rounded-full transition"
                aria-label="{{ __('Share screen') }}"
                title="{{ __('Share screen') }}"
            >
                <flux:icon.computer-desktop variant="mini" />
            </button>
            <button
                type="button"
                x-on:click.stop="$store.pipManager?.endCall()"
                class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-red-500 text-white transition hover:bg-red-600"
                aria-label="{{ __('End call') }}"
            >
                <flux:icon.phone-x-mark variant="solid" />
            </button>
        </footer>
    </section>
</div>
