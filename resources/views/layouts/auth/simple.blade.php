<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="brand-shell min-h-screen antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <div class="mx-auto flex min-h-screen max-w-7xl items-stretch p-4 sm:p-6 lg:p-8">
        <div class="grid flex-1 gap-6 lg:grid-cols-[minmax(0,1.08fr)_minmax(28rem,0.92fr)]">
            <section
                x-data
                x-init
                x-transition:enter="transition ease-out duration-500 delay-100"
                x-transition:enter-start="opacity-0 -translate-x-4"
                x-transition:enter-end="opacity-100 translate-x-0"
                class="relative hidden overflow-hidden rounded-[2rem] bg-[linear-gradient(160deg,var(--brand-700)_0%,var(--brand-900)_100%)] p-8 text-white shadow-xl lg:flex lg:flex-col lg:justify-between">
                <div class="absolute -right-16 top-0 h-56 w-56 rounded-full bg-[oklch(from_var(--brand-400)_l_c_h_/_0.22)] blur-3xl"></div>
                <div class="absolute -left-10 bottom-0 h-48 w-48 rounded-full bg-[oklch(from_var(--brand-200)_l_c_h_/_0.22)] blur-3xl"></div>

                <div class="inline-flex items-center gap-2 self-start rounded-lg border border-stone-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900/80">
                    <x-app-logo href="{{ route('home') }}" wire:navigate class="relative z-10 h-9 w-auto" />
                </div>

                <div class="relative z-10">
                    <h1 class="brand-serif mb-5 text-5xl font-bold leading-tight text-white">
                        Your palengke,<br>at your doorstep.
                    </h1>
                    <p class="text-base leading-8 text-neutral-300 dark:text-zinc-300">
                        Browse fresh produce, seafood, and everyday market staples from verified local vendors - all in
                        one familiar, easy-to-navigate storefront.
                    </p>
                </div>

                <div class="relative z-10 border-t border-white/10 pt-6">
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-5 backdrop-blur-sm">
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

            <section
                x-data
                x-init
                x-transition:enter="transition ease-out duration-400"
                x-transition:enter-start="opacity-0 translate-x-6"
                x-transition:enter-end="opacity-100 translate-x-0"
                class="flex items-center justify-center"
            >
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
        <flux:toast.group position="bottom left">
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
    <script>
        (() => {
            const ENTER_CLASS = 'suki-page-enter';

            function applyEnter(el) {
                el.classList.remove(ENTER_CLASS);
                void el.offsetWidth;
                el.classList.add(ENTER_CLASS);
                el.addEventListener('animationend', () => el.classList.remove(ENTER_CLASS), { once: true });
            }

            document.addEventListener('livewire:navigated', () => {
                const main = document.querySelector('main');

                if (main) {
                    applyEnter(main);
                }

                window.sukiRevealAll?.();
            });

            let bar = null;
            let barHideTimer = null;
            let barSafetyTimer = null;

            document.addEventListener('livewire:navigating', () => {
                clearTimeout(barHideTimer);
                clearTimeout(barSafetyTimer);

                if (!bar) {
                    bar = document.createElement('div');
                    bar.id = 'suki-nprogress-bar';
                    document.body.appendChild(bar);
                }

                bar.style.opacity = '1';
                bar.style.transition = '';
                bar.style.display = 'block';

                barSafetyTimer = setTimeout(() => {
                    if (bar) {
                        bar.style.display = 'none';
                    }
                }, 6000);
            });

            document.addEventListener('livewire:navigated', () => {
                clearTimeout(barSafetyTimer);

                if (bar) {
                    bar.style.opacity = '0';
                    bar.style.transition = 'opacity 300ms ease';
                    barHideTimer = setTimeout(() => {
                        if (bar) {
                            bar.style.display = 'none';
                            bar.style.opacity = '';
                            bar.style.transition = '';
                        }
                    }, 320);
                }
            });
        })();
    </script>
</body>

</html>
