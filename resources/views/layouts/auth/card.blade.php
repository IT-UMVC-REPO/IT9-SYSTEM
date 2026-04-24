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
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
