@php
    $footerGroups = [
        [
            'title' => __('Marketplace'),
            'links' => [
                ['label' => __('Storefront'), 'href' => route('shop.home')],
                ['label' => __('Vendor stalls'), 'href' => route('shop.vendors')],
                ['label' => __('Start setup'), 'href' => route('setup')],
            ],
        ],
        [
            'title' => __('Support'),
            'links' => [
                ['label' => __('Contact Us'), 'href' => route('contact.index')],
                ['label' => __('Privacy Policy'), 'href' => route('legal.privacy-policy')],
                ['label' => __('Terms & Conditions'), 'href' => route('legal.terms-and-conditions')],
            ],
        ],
    ];

    $accountLinks = auth()->check()
        ? [
            ['label' => __('Dashboard'), 'href' => route(auth()->user()->homeRoute())],
            ['label' => __('Settings'), 'href' => route('profile.edit')],
        ]
        : [
            ['label' => __('Log in'), 'href' => route('login')],
            ['label' => __('Register'), 'href' => route('register')],
        ];
@endphp

<footer data-test="site-footer" class="border-t border-white/60 bg-neutral-950 text-zinc-200 shadow-[0_-24px_80px_rgba(15,23,42,0.12)] dark:border-white/10">
    <div class="mx-auto grid max-w-[1500px] gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] lg:px-8 lg:py-14">
        <div class="grid gap-6">
            <a href="{{ route('home') }}" wire:navigate class="flex min-w-0 items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl border border-emerald-300/25 bg-emerald-400/15 text-emerald-200 shadow-sm" aria-hidden="true">
                    <span class="brand-logo-mark h-7 w-7" style="--suki-logo-mask: url('{{ asset('imgs/sukilogo.png') }}')"></span>
                </span>
                <span class="min-w-0">
                    <span class="brand-serif block truncate text-xl font-bold text-white">SukiMarket</span>
                    <span class="block truncate text-[11px] uppercase tracking-[0.16em] text-zinc-400">{{ __('Videre Est Scire') }}</span>
                </span>
            </a>

            <p class="max-w-2xl text-sm leading-7 text-zinc-400">
                {{ __('SukiMarket keeps local buying familiar: verified stalls, fresh listings, rider delivery, and marketplace support in one place.') }}
            </p>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-300">{{ __('Tagline') }}</p>
                    <p class="mt-2 text-sm font-semibold text-white">Videre Est Scire</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-amber-300">{{ __('Local') }}</p>
                    <p class="mt-2 text-sm font-semibold text-white">{{ __('Tagum City market flow') }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-rose-300">{{ __('Help') }}</p>
                    <a href="{{ route('contact.index') }}" wire:navigate class="mt-2 inline-flex text-sm font-semibold text-white transition hover:text-emerald-200">
                        {{ __('Contact Us') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="grid gap-8 sm:grid-cols-3">
            @foreach ($footerGroups as $group)
                <nav class="grid content-start gap-3" aria-label="{{ $group['title'] }}">
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-zinc-500">{{ $group['title'] }}</p>
                    @foreach ($group['links'] as $link)
                        <a href="{{ $link['href'] }}" wire:navigate class="text-sm font-medium text-zinc-300 transition hover:text-white">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>
            @endforeach

            <nav class="grid content-start gap-3" aria-label="{{ __('Account') }}">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-zinc-500">{{ __('Account') }}</p>
                @foreach ($accountLinks as $link)
                    <a href="{{ $link['href'] }}" wire:navigate class="text-sm font-medium text-zinc-300 transition hover:text-white">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-[1500px] flex-col gap-3 px-4 py-5 text-xs text-zinc-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <p>&copy; {{ now()->year }} SukiMarket. {{ __('All rights reserved.') }}</p>
            <p>{{ __('Fresh market routines, translated for the web.') }}</p>
        </div>
    </div>
</footer>
