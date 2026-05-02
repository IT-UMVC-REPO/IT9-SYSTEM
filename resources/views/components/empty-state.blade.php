@props(['icon' => 'fa-regular fa-folder-open', 'heading', 'body', 'actionLabel' => null, 'actionRoute' => null])

<div {{ $attributes->merge(['class' => 'brand-panel px-6 py-14 text-center']) }}>
    <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-[2rem] brand-soft-surface">
        <svg viewBox="0 0 120 120" aria-hidden="true" class="h-16 w-16">
            <circle cx="60" cy="60" r="48" fill="currentColor" opacity="0.12" />
            <path d="M34 70c6-18 18-27 35-27 9 0 16 3 21 8-4 18-16 27-35 27-8 0-15-3-21-8Z" fill="currentColor" opacity="0.2" />
            <path d="M40 73c7-14 18-21 33-21" stroke="currentColor" stroke-width="6" stroke-linecap="round" fill="none" />
            <circle cx="82" cy="44" r="7" fill="currentColor" opacity="0.35" />
        </svg>
    </div>

    <span class="mt-5 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white text-neutral-500 shadow-sm dark:bg-zinc-800 dark:text-zinc-300">
        <i class="{{ $icon }}"></i>
    </span>

    <h2 class="brand-serif mt-5 text-2xl font-bold text-neutral-900 dark:text-zinc-100 sm:text-3xl">{{ $heading }}</h2>
    <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">{{ $body }}</p>

    @if (filled($actionLabel) && filled($actionRoute))
        <a href="{{ $actionRoute }}" wire:navigate class="brand-button-primary mt-6">
            {{ $actionLabel }}
        </a>
    @endif
</div>
