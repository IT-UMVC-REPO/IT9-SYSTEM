@php($user = auth()->user())

<div
    data-test="profile-picture-editor"
    x-data="sukiProfilePictureEditor({
        currentAvatar: @js($user->profile_image_url),
        initials: @js($user->initials()),
    })"
    x-on:profile-picture-updated.window="handleUpdated($event)"
>
    <div class="flex flex-col gap-5 rounded-[1.5rem] border border-stone-200 bg-white/70 p-5 shadow-sm dark:border-white/10 dark:bg-zinc-900/70 sm:flex-row sm:items-center">
        <div class="relative h-24 w-24 shrink-0 overflow-hidden rounded-full shadow-sm ring-2 ring-stone-200 dark:ring-white/10" aria-hidden="true">
            <template x-if="currentAvatar">
                <img :src="currentAvatar" alt="{{ $user->name }}" class="h-full w-full object-cover">
            </template>
            <template x-if="!currentAvatar">
                <span class="brand-logo-badge flex h-full w-full items-center justify-center text-2xl font-semibold" x-text="initials"></span>
            </template>
        </div>

        <div class="min-w-0 flex-1">
            <p class="text-base font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Profile photo') }}</p>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-neutral-500 dark:text-zinc-400">{{ __('Crop a square photo for your SukiMarket avatar. JPG, PNG, WebP, or GIF can be selected, then saved as a cropped image.') }}</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" x-on:click="$refs.fileInput.click()" class="brand-button-secondary px-4 py-2 text-xs active:scale-[0.96]">
                    <i class="fa-solid fa-camera text-xs"></i>
                    {{ __('Change Photo') }}
                </button>
                <template x-if="croppedImage">
                    <button type="button" x-on:click="reEdit" class="brand-button-secondary px-4 py-2 text-xs active:scale-[0.96]">
                        {{ __('Re-edit') }}
                    </button>
                </template>
            </div>
        </div>

        <input x-ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" x-on:change="selectFile($event)" class="sr-only">
    </div>

    <div x-show="croppedImage" x-cloak class="mt-5 rounded-[1.5rem] border border-stone-200 bg-stone-50/80 p-5 dark:border-white/10 dark:bg-zinc-800/60">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <img :src="croppedImage" alt="{{ __('Cropped profile photo preview') }}" class="h-20 w-20 rounded-full object-cover shadow-sm ring-2 ring-emerald-400">
                <div>
                    <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Ready to save') }}</p>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-zinc-400">{{ __('Review the crop before updating your avatar.') }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" x-on:click="savePhoto" wire:loading.attr="disabled" wire:target="save" class="brand-button-primary px-4 py-2 text-xs active:scale-[0.96]">
                    <span wire:loading.remove wire:target="save">{{ __('Save Photo') }}</span>
                    <span wire:loading wire:target="save">{{ __('Saving...') }}</span>
                </button>
                <button type="button" x-on:click="reEdit" wire:loading.attr="disabled" wire:target="save" class="brand-button-secondary px-4 py-2 text-xs active:scale-[0.96]">
                    {{ __('Re-edit') }}
                </button>
            </div>
        </div>
    </div>

    @if (session('status'))
        <p class="mt-3 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('status') }}
        </p>
    @endif

    @error('croppedImageData')
        <p class="mt-3 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
    @enderror

    <template x-teleport="body">
        <div
            x-cloak
            x-show="modalOpen"
            x-transition.opacity
            class="fixed inset-0 z-[100] flex items-center justify-center bg-neutral-950/75 p-4 backdrop-blur-xl sm:p-6"
            role="dialog"
            aria-modal="true"
            aria-labelledby="profile-photo-editor-title"
        >
            <div x-on:click.outside="cancel" class="grid max-h-[92vh] w-full max-w-5xl overflow-hidden rounded-[2rem] border border-white/10 bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4 border-b border-stone-200 px-5 py-5 dark:border-white/10 sm:px-6">
                    <div>
                        <h2 id="profile-photo-editor-title" class="brand-serif text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Edit Profile Photo') }}</h2>
                        <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">{{ __('Crop and zoom your image before applying it.') }}</p>
                    </div>
                    <button type="button" x-on:click="cancel" class="brand-button-secondary flex h-10 w-10 items-center justify-center p-0" aria-label="{{ __('Cancel') }}">
                        <i class="fa-solid fa-xmark text-xs"></i>
                    </button>
                </div>

                <div class="grid max-h-[calc(92vh-5.5rem)] overflow-y-auto p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_14rem] lg:gap-6">
                    <div class="grid gap-4">
                        <div class="overflow-hidden rounded-[1.5rem] border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-800">
                            <div id="cropper-container" class="h-[300px] sm:h-[460px]">
                                <img id="cropper-image" x-ref="cropperImage" alt="{{ __('Selected profile photo') }}" class="block max-h-full max-w-full">
                            </div>
                        </div>

                        <div class="rounded-[1.25rem] border border-stone-200 bg-stone-50 p-4 dark:border-white/10 dark:bg-zinc-800/70">
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="button" x-on:click="zoomOut" class="brand-button-secondary h-10 w-10 p-0" aria-label="{{ __('Zoom Out') }}">
                                    <i class="fa-solid fa-magnifying-glass-minus"></i>
                                </button>
                                <input type="range" min="0" max="3" step="0.01" x-model="zoomValue" x-on:input="zoomTo($event.target.value)" class="min-w-48 flex-1 accent-emerald-600">
                                <button type="button" x-on:click="zoomIn" class="brand-button-secondary h-10 w-10 p-0" aria-label="{{ __('Zoom In') }}">
                                    <i class="fa-solid fa-magnifying-glass-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <aside class="mt-5 grid content-start gap-4 lg:mt-0">
                        <div class="rounded-[1.5rem] border border-stone-200 bg-stone-50 p-5 text-center dark:border-white/10 dark:bg-zinc-800/70">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Preview') }}</p>
                            <div class="mx-auto mt-4 h-28 w-28 overflow-hidden rounded-full border border-stone-200 bg-stone-100 shadow-sm dark:border-white/10 dark:bg-zinc-800">
                                <template x-if="previewImage">
                                    <img :src="previewImage" alt="{{ __('Profile photo preview') }}" class="h-full w-full object-cover">
                                </template>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <button type="button" x-on:click="applyCrop" class="brand-button-primary active:scale-[0.96]">{{ __('Apply') }}</button>
                            <button type="button" x-on:click="cancel" class="brand-button-secondary active:scale-[0.96]">{{ __('Cancel') }}</button>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </template>
</div>
