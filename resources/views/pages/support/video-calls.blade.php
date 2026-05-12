<x-layouts::app.header :title="__('Video call support')">
    <main class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
        <section class="brand-panel p-6 sm:p-8">
            <span class="brand-kicker">{{ __('Video calls') }}</span>
            <h1 class="brand-serif mt-4 text-3xl font-bold text-neutral-900 dark:text-zinc-100">
                {{ __('TURN credentials may be required') }}
            </h1>
            <p class="mt-4 text-sm leading-7 text-neutral-600 dark:text-zinc-300">
                {{ __('Mobile-to-mobile calls or calls across different networks often need a TURN server so audio and video can relay when direct peer connections are blocked by NAT, carrier networks, or tunnels.') }}
            </p>
            <p class="mt-4 text-sm leading-7 text-neutral-600 dark:text-zinc-300">
                {{ __('If calls stay on connecting, configure WEBRTC_TURN_URLS with WEBRTC_TURN_USERNAME and WEBRTC_TURN_CREDENTIAL, or WEBRTC_TURN_SHARED_SECRET, then clear Laravel config cache before trying again.') }}
            </p>
        </section>
    </main>
</x-layouts::app.header>
