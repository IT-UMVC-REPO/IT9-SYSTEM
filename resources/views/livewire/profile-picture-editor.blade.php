@php($user = auth()->user())

<div
    data-test="profile-picture-editor"
    x-data="sukiProfilePictureEditor({
        currentAvatar: @js($user->profile_image_url),
        initials: @js($user->initials()),
    })"
    x-on:profile-picture-updated.window="handleUpdated($event)"
>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <button type="button" x-on:click="$refs.fileInput.click()" class="group relative h-24 w-24 shrink-0 overflow-hidden rounded-full shadow-sm ring-2 ring-stone-200 transition hover:ring-emerald-400 dark:ring-white/10" aria-label="{{ __('Change Photo') }}">
            <template x-if="currentAvatar">
                <img :src="currentAvatar" alt="{{ $user->name }}" class="h-full w-full object-cover">
            </template>
            <template x-if="!currentAvatar">
                <span class="brand-logo-badge flex h-full w-full items-center justify-center text-2xl font-semibold" x-text="initials"></span>
            </template>
            <span class="absolute inset-x-0 bottom-0 bg-neutral-950/70 px-2 py-1 text-xs font-semibold text-white opacity-95 transition group-hover:bg-neutral-950/85">
                {{ __('Change Photo') }}
            </span>
        </button>

        <div class="min-w-0">
            <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Profile photo') }}</p>
            <p class="mt-1 text-xs leading-6 text-neutral-500 dark:text-zinc-400">{{ __('Crop a square photo for your Sukimarket avatar. JPG, PNG, WebP, or GIF can be selected, then saved as a cropped image.') }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
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

    <div
        x-cloak
        x-show="modalOpen"
        x-transition.opacity
        class="fixed inset-0 z-[100] flex items-center justify-center bg-neutral-950/70 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-labelledby="profile-photo-editor-title"
    >
        <div x-on:click.outside="cancel" class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-[1.75rem] border border-white/10 bg-white p-5 shadow-2xl dark:bg-zinc-900 sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="profile-photo-editor-title" class="brand-serif text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Edit Profile Photo') }}</h2>
                    <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">{{ __('Crop, zoom, rotate, or flip your image before applying it.') }}</p>
                </div>
                <button type="button" x-on:click="cancel" class="brand-button-secondary flex h-10 w-10 items-center justify-center p-0" aria-label="{{ __('Cancel') }}">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_8rem]">
                <div class="overflow-hidden rounded-2xl border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-800">
                    <div id="cropper-container" class="h-[280px] sm:h-[400px]">
                        <img id="cropper-image" x-ref="cropperImage" alt="{{ __('Selected profile photo') }}" class="block max-h-full max-w-full">
                    </div>
                </div>

                <div class="flex flex-row items-center gap-4 lg:flex-col">
                    <div>
                        <p class="mb-2 text-center text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Preview') }}</p>
                        <div class="h-20 w-20 overflow-hidden rounded-full border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-800">
                            <template x-if="previewImage">
                                <img :src="previewImage" alt="{{ __('Profile photo preview') }}" class="h-full w-full object-cover">
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" x-on:click="zoomOut" class="brand-button-secondary px-4 py-2 text-xs"><i class="fa-solid fa-magnifying-glass-minus"></i>{{ __('Zoom Out') }}</button>
                    <input type="range" min="0" max="3" step="0.01" x-model="zoomValue" x-on:input="zoomTo($event.target.value)" class="min-w-48 flex-1 accent-emerald-600">
                    <button type="button" x-on:click="zoomIn" class="brand-button-secondary px-4 py-2 text-xs"><i class="fa-solid fa-magnifying-glass-plus"></i>{{ __('Zoom In') }}</button>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" x-on:click="rotate(-90)" class="brand-button-secondary px-4 py-2 text-xs"><i class="fa-solid fa-rotate-left"></i>{{ __('Rotate Left') }}</button>
                    <button type="button" x-on:click="rotate(90)" class="brand-button-secondary px-4 py-2 text-xs"><i class="fa-solid fa-rotate-right"></i>{{ __('Rotate Right') }}</button>
                    <button type="button" x-on:click="flipHorizontal" class="brand-button-secondary px-4 py-2 text-xs"><i class="fa-solid fa-left-right"></i>{{ __('Flip Horizontal') }}</button>
                    <button type="button" x-on:click="flipVertical" class="brand-button-secondary px-4 py-2 text-xs"><i class="fa-solid fa-up-down"></i>{{ __('Flip Vertical') }}</button>
                    <button type="button" x-on:click="resetEditor" class="brand-button-secondary px-4 py-2 text-xs">{{ __('Reset') }}</button>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-stone-200 pt-5 dark:border-white/10">
                <button type="button" x-on:click="cancel" class="brand-button-secondary active:scale-[0.96]">{{ __('Cancel') }}</button>
                <button type="button" x-on:click="applyCrop" class="brand-button-primary active:scale-[0.96]">{{ __('Apply') }}</button>
            </div>
        </div>
    </div>
</div>
