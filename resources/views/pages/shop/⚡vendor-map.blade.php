<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Vendor Map')] class extends Component {};
?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <span class="brand-kicker">{{ __('Discover stalls') }}</span>
            <h1 class="brand-serif mt-4 text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Vendor Map') }}</h1>
            <p class="mt-4 max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
                {{ __('Find approved LocalPalengke vendors near you across Tagum City. Click any marker to visit their stall.') }}
            </p>
        </div>
    </section>

    <div
        x-data="sukiVendorMap()"
        x-init="initMap('suki-vendor-map')"
        class="brand-panel overflow-hidden"
        style="min-height: 600px;"
    >
        <div id="suki-vendor-map" class="h-[600px] w-full rounded-3xl z-0"></div>
    </div>
</div>
