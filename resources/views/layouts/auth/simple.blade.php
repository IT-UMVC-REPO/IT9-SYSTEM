<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="brand-shell min-h-screen antialiased">
    <div class="mx-auto flex min-h-screen max-w-7xl items-stretch p-4 sm:p-6 lg:p-8">
        <div class="grid flex-1 gap-6 lg:grid-cols-[minmax(0,1.08fr)_minmax(28rem,0.92fr)]">
            <section
                class="relative hidden overflow-hidden rounded-[2rem] bg-neutral-950 p-8 text-white shadow-xl lg:flex lg:flex-col lg:justify-between">
                <div class="absolute -right-16 top-0 h-56 w-56 rounded-full bg-emerald-500/10 blur-3xl"></div>
                <div class="absolute -left-10 bottom-0 h-48 w-48 rounded-full bg-amber-300/10 blur-3xl"></div>

                <div class="bg-white p-4 rounded-lg inline-flex items-center gap-2 self-start">
                    <a href="{{ route('home') }}" class="relative z-10 flex items-center gap-3" wire:navigate>
                        <x-app-logo class="h-9 w-auto" />
                    </a>
                </div>


                <div class="relative z-10">

                    <h1 class="brand-serif mb-5 text-5xl font-bold leading-tight text-white">
                        Your suki,<br>wherever you are.
                    </h1>
                    <p class="max-w-sm text-base leading-8 text-neutral-300">
                        Browse fresh produce, seafood, and everyday market staples from verified local vendors — all in
                        one familiar, easy-to-navigate storefront.
                    </p>
                </div>

                <div class="relative z-10 border-t border-white/10 pt-6">
                    <div class="max-w-md rounded-3xl border border-white/10 bg-white/5 p-5 backdrop-blur-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-emerald-300">
                            What awaits inside
                        </p>
                        <p class="mt-3 text-sm leading-7 text-neutral-200">
                            Sign in to browse real storefronts, revisit trusted vendors, and keep your market routine in
                            one place.
                        </p>
                        <p class="mt-4 text-sm leading-7 text-neutral-400">
                            From daily essentials to fresh finds, SukiMarket is built to feel familiar from the very
                            first order.
                        </p>
                    </div>
                </div>
            </section>

            <section class="flex items-center justify-center">
                <div class="brand-panel w-full max-w-xl p-6 shadow-xl sm:p-8 lg:p-10">
                    <a href="{{ route('home') }}" class="brand-link mb-6" wire:navigate>
                        <i class="fa-solid fa-arrow-left text-xs"></i>
                        Back to home
                    </a>

                    <div class="flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                </div>
            </section>
        </div>
    </div>
    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
