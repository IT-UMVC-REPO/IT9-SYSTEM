<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Appearance settings')] class extends Component {
}; ?>

<section class="w-full settings-page">
    <div class="settings-shell mx-auto max-w-[1500px] space-y-8">
        @include('partials.settings-heading')

        <flux:heading class="sr-only">{{ __('Appearance settings') }}</flux:heading>

        <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Choose how SukiMarket should look while you browse and manage your account')">
            <section class="settings-section-card">
                <div class="flex items-start gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                        <i class="fa-solid fa-circle-half-stroke text-lg"></i>
                    </span>

                    <div>
                        <h3 class="brand-serif text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Theme preference') }}</h3>
                        <p class="mt-2 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                            {{ __('Switch between light, dark, and system themes so your account feels comfortable whether you are shopping in daylight or after market hours.') }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 rounded-[1.5rem] border border-stone-200 bg-white/80 p-4 shadow-sm dark:border-white/10 dark:bg-white/5 sm:p-6">
                    <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" class="w-full">
                        <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                        <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                        <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
                    </flux:radio.group>
                </div>

                <p class="text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                    {{ __('System follows your device preference automatically, while Light and Dark keep the same look every time you return to SukiMarket.') }}
                </p>
            </section>
        </x-pages::settings.layout>
    </div>
</section>
