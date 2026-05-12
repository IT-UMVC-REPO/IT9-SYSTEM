<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="brand-shell min-h-screen antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-8 sm:px-6 lg:px-8">
            <div class="brand-glow-orb pointer-events-none absolute -right-16 top-0 h-56 w-56 rounded-full blur-3xl"></div>
            <div class="pointer-events-none absolute -left-10 bottom-0 h-48 w-48 rounded-full bg-amber-300/10 blur-3xl"></div>

            <div class="relative z-10 flex w-full max-w-xl flex-col gap-6">
                <div class="flex justify-center">
                    <x-app-logo href="{{ route('home') }}" wire:navigate />
                </div>

                <div class="brand-panel w-full p-6 shadow-xl sm:p-8 lg:p-10">
                    <div class="flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                </div>
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
