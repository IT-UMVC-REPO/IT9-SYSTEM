@php
    $user = auth()->user();
    $roleLabel = match ($user->effectiveMarketplaceRole()) {
        \App\Enums\UserRole::Admin => 'Admin workspace',
        \App\Enums\UserRole::Vendor => 'Vendor workspace',
        \App\Enums\UserRole::Rider => 'Rider workspace',
        \App\Enums\UserRole::Customer => 'Customer workspace',
    };
@endphp

<div class="settings-intro">
    <div class="max-w-2xl">
        <span class="brand-kicker">
            <i class="fa-solid fa-sliders"></i>
            {{ __('Settings') }}
        </span>

        <h1 class="brand-serif mt-5 text-3xl font-bold tracking-tight text-stone-900 dark:text-zinc-50 sm:text-5xl">
            {{ __('Your market desk') }}
        </h1>

        <p class="mt-4 max-w-2xl text-base leading-8 text-stone-600 dark:text-zinc-400">
            {{ __('Manage your identity, sign-in protection, and visual preferences from one focused SukiMarket workspace.') }}
        </p>
    </div>

    <div class="settings-intro-card max-w-sm">
        <span class="settings-role-badge">{{ $roleLabel }}</span>
        <p class="mt-3 truncate text-sm font-medium text-stone-600 dark:text-zinc-300">{{ $user->email }}</p>
    </div>
</div>
