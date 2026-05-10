@props([
    'name',
    'heading',
    'body',
    'confirmLabel',
    'confirmAction' => null,
    'variant' => 'danger',
    'maxWidth' => 'max-w-sm',
])

@php
    $isDanger = $variant === 'danger';
    $iconClasses = $isDanger
        ? 'bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300'
        : 'brand-soft-surface';
    $buttonClasses = $isDanger
        ? 'inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700'
        : 'brand-button-primary active:scale-[0.96]';
@endphp

<flux:modal name="{{ $name }}" class="{{ $maxWidth }} p-6 sm:p-7" {{ $attributes }}>
    <div class="group">
        <div class="flex h-14 w-14 items-center justify-center rounded-full transition-transform duration-300 group-hover:scale-110 {{ $iconClasses }}">
            <i class="fa-solid {{ $isDanger ? 'fa-triangle-exclamation' : 'fa-circle-info' }}"></i>
        </div>

        <h2 class="brand-serif mt-5 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ $heading }}</h2>
        <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">{{ $body }}</p>

        <div class="mt-6 flex justify-end gap-3">
            <flux:button variant="ghost" x-on:click="$flux.modal('{{ $name }}').close()">
                {{ __('Cancel') }}
            </flux:button>

            <button
                type="button"
                @if ($confirmAction) wire:click="{{ $confirmAction }}" @endif
                x-on:click="$flux.modal('{{ $name }}').close()"
                class="{{ $buttonClasses }} transition-all duration-150 active:scale-[0.97]"
            >
                {{ $confirmLabel }}
            </button>
        </div>
    </div>
</flux:modal>
