@props(['user', 'size' => 'md'])

@php
    $sizeClasses = match ($size) {
        'sm' => 'h-[34px] w-[34px] text-sm',
        'md' => 'h-10 w-10 text-sm',
        'lg' => 'h-11 w-11 text-sm',
        'xl' => 'h-16 w-16 text-xl',
        '2xl' => 'h-20 w-20 text-2xl',
        default => 'h-10 w-10 text-sm',
    };
@endphp

@if ($user?->profile_image)
    <img
        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($user->profile_image) }}"
        alt="{{ $user->name }}"
        {{ $attributes->class([$sizeClasses, 'rounded-full object-cover shadow-sm ring-2 ring-stone-200 dark:ring-white/10']) }}
    >
@else
    <span {{ $attributes->class([$sizeClasses, 'brand-logo-badge flex items-center justify-center rounded-full font-semibold shadow-sm']) }}>
        {{ $user?->initials() ?? '?' }}
    </span>
@endif
