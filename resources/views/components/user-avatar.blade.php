@props(['user', 'size' => 'md', 'online' => false])

@php
    $isArrayUser = is_array($user);
    $userName = $isArrayUser ? ($user['name'] ?? null) : $user?->name;
    $profileImage = $isArrayUser ? ($user['profile_image'] ?? null) : $user?->profile_image;
    $initials = $isArrayUser ? ($user['initials'] ?? null) : $user?->initials();

    if (! filled($initials)) {
        $initials = collect(explode(' ', (string) $userName))
            ->filter()
            ->map(fn (string $part): string => mb_substr($part, 0, 1))
            ->take(2)
            ->implode('');
    }

    $sizeClasses = match ($size) {
        'xs' => 'h-7 w-7 text-xs',
        'sm' => 'h-[34px] w-[34px] text-sm',
        'md' => 'h-10 w-10 text-sm',
        'lg' => 'h-11 w-11 text-sm',
        'profile' => 'h-12 w-12 text-base',
        'xl' => 'h-16 w-16 text-xl',
        '2xl' => 'h-20 w-20 text-2xl',
        default => 'h-10 w-10 text-sm',
    };
@endphp

<span {{ $attributes->class(['relative inline-flex shrink-0 rounded-full transition-transform duration-200']) }}>
    @if ($profileImage)
        <img
            src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($profileImage) }}"
            alt="{{ $userName ?? __('User') }}"
            onerror="this.onerror=null; this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden'); this.nextElementSibling.classList.add('flex');"
            class="{{ $sizeClasses }} rounded-full object-cover shadow-sm ring-2 ring-stone-200 transition-opacity duration-300 dark:ring-white/10"
            loading="lazy"
        >
        <span class="{{ $sizeClasses }} brand-logo-badge hidden items-center justify-center rounded-full font-semibold shadow-sm">
            {{ filled($initials) ? $initials : '?' }}
        </span>
    @else
        <span class="{{ $sizeClasses }} brand-logo-badge flex items-center justify-center rounded-full font-semibold shadow-sm">
            {{ filled($initials) ? $initials : '?' }}
        </span>
    @endif

    @if ($online)
        <span class="absolute bottom-0 right-0 block h-3 w-3 rounded-full border-2 border-white bg-emerald-500 ring-1 ring-emerald-400 dark:border-zinc-900"></span>
    @endif
</span>
