@php
    $user = auth()->user();
    $roleLabel = match ($user->effectiveMarketplaceRole()) {
        \App\Enums\UserRole::Admin => 'Admin workspace',
        \App\Enums\UserRole::Vendor => 'Vendor workspace',
        \App\Enums\UserRole::Customer => 'Customer workspace',
    };
@endphp

<div class="settings-intro">
    <div class="max-w-2xl">
        <span class="brand-kicker">
            <i class="fa-solid fa-sliders"></i>
            {{ __('Settings') }}
        </span>

        <h1 class="brand-serif mt-5 text-4xl font-bold tracking-tight text-stone-900 dark:text-zinc-50 sm:text-5xl">
            Your market desk, tuned to you.
        </h1>

        <p class="mt-4 max-w-2xl text-base leading-8 text-stone-600 dark:text-zinc-400">
            Manage your identity, sign-in protection, and visual preferences from one focused SukiMarket workspace that stays warm and easy to scan.
        </p>
    </div>

    <div class="settings-intro-card max-w-lg">
        <div class="flex flex-wrap items-center gap-2">
            <span class="settings-role-badge">{{ $roleLabel }}</span>
            <span class="inline-flex items-center gap-2 rounded-full border border-stone-200 bg-white px-3 py-1 text-xs font-medium text-stone-500 dark:border-white/10 dark:bg-zinc-900/80 dark:text-zinc-300">
                <i class="fa-solid fa-envelope text-[11px] text-emerald-600"></i>
                {{ $user->email }}
            </span>
        </div>

        <div class="mt-1 grid gap-3 sm:grid-cols-3">
            <div class="rounded-[1.25rem] border border-stone-200 bg-white px-4 py-3 text-sm font-medium text-stone-600 dark:border-white/10 dark:bg-zinc-900/80 dark:text-zinc-300">
                <i class="fa-solid fa-id-card mr-2 text-emerald-600"></i>
                Profile
            </div>
            <div class="rounded-[1.25rem] border border-stone-200 bg-white px-4 py-3 text-sm font-medium text-stone-600 dark:border-white/10 dark:bg-zinc-900/80 dark:text-zinc-300">
                <i class="fa-solid fa-shield-halved mr-2 text-emerald-600"></i>
                Security
            </div>
            <div class="rounded-[1.25rem] border border-stone-200 bg-white px-4 py-3 text-sm font-medium text-stone-600 dark:border-white/10 dark:bg-zinc-900/80 dark:text-zinc-300">
                <i class="fa-solid fa-circle-half-stroke mr-2 text-emerald-600"></i>
                Appearance
            </div>
        </div>
    </div>
</div>
