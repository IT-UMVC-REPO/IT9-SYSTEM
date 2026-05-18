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

<footer data-test="site-footer" class="border-t border-white/10 bg-neutral-950 text-zinc-200">
    <div class="mx-auto grid max-w-[1500px] gap-7 px-4 py-7 sm:px-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start lg:px-8 lg:py-7">
        <div class="grid max-w-3xl gap-4">
            <a href="{{ route('home') }}" wire:navigate class="flex min-w-0 items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl border border-emerald-300/25 bg-emerald-400/15 text-emerald-200 shadow-sm" aria-hidden="true">
                    <span class="brand-logo-mark h-6 w-6" style="--suki-logo-mask: url('{{ asset('imgs/sukilogo.png') }}')"></span>
                </span>
                <span class="min-w-0">
                    <span class="brand-serif block truncate text-lg font-bold leading-tight text-white">SukiMarket</span>
                    <span class="block truncate text-[10px] uppercase tracking-[0.16em] text-zinc-400">{{ __('Videre Est Scire') }}</span>
                </span>
            </a>

            <p class="max-w-2xl text-sm leading-6 text-zinc-400">
                {{ __('SukiMarket keeps local buying familiar: verified stalls, fresh listings, rider delivery, and marketplace support in one place.') }}
            </p>

        </div>

        <div class="grid gap-6 sm:grid-cols-[repeat(3,minmax(8rem,1fr))] lg:min-w-[42rem]">
            @foreach ($footerGroups as $group)
                <nav class="grid content-start gap-2" aria-label="{{ $group['title'] }}">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-zinc-500">{{ $group['title'] }}</p>
                    @foreach ($group['links'] as $link)
                        <a href="{{ $link['href'] }}" wire:navigate class="text-sm font-medium leading-5 text-zinc-300 transition hover:text-white">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>
            @endforeach

            <nav class="grid content-start gap-2" aria-label="{{ __('Account') }}">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-zinc-500">{{ __('Account') }}</p>
                @foreach ($accountLinks as $link)
                    <a href="{{ $link['href'] }}" wire:navigate class="text-sm font-medium leading-5 text-zinc-300 transition hover:text-white">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-[1500px] flex-col gap-2 px-4 py-3 text-xs text-zinc-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <p>&copy; {{ now()->year }} SukiMarket. {{ __('All rights reserved.') }}</p>
            <p>{{ __('Fresh market routines, translated for the web.') }}</p>
        </div>
    </div>
</footer>
