<?php

use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Appearance settings')] class extends Component {
    public string $brand_color = '';

    public function mount(): void
    {
        $this->brand_color = auth()->user()->brand_color ?? '#059669';
    }

    public function setPreset(string $hex): void
    {
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $hex) !== 1) {
            return;
        }

        $this->brand_color = $hex;
    }

    public function saveBrandColor(): void
    {
        $this->validate([
            'brand_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        auth()->user()->update(['brand_color' => $this->brand_color]);

        Flux::toast(variant: 'success', text: __('Brand color saved.'), duration: 1200);

        $this->dispatch('brand-color-persisted');
    }

    public function resetBrandColor(): void
    {
        auth()->user()->update(['brand_color' => null]);

        $this->brand_color = '#059669';

        Flux::toast(text: __('Brand color reset to default.'), duration: 1200);

        $this->dispatch('brand-color-persisted');
    }
}; ?>

<section class="w-full settings-page">
    @php
        $brandPresets = [
            ['label' => 'Emerald', 'hex' => '#059669'],
            ['label' => 'Violet', 'hex' => '#7c3aed'],
            ['label' => 'Rose', 'hex' => '#e11d48'],
            ['label' => 'Amber', 'hex' => '#d97706'],
            ['label' => 'Cobalt', 'hex' => '#1d4ed8'],
            ['label' => 'Coral', 'hex' => '#ea580c'],
            ['label' => 'Fuchsia', 'hex' => '#a21caf'],
            ['label' => 'Slate', 'hex' => '#475569'],
        ];
    @endphp

    <div class="settings-shell mx-auto max-w-[1500px] space-y-8">
        @include('partials.settings-heading')

        <flux:heading class="sr-only">{{ __('Appearance settings') }}</flux:heading>

        <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Choose how SukiMarket should look while you browse and manage your account')">
            <section class="settings-section-card">
                <div class="flex items-start gap-4">
                    <span class="brand-soft-surface flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl">
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

            <section
                class="settings-section-card"
                x-data="{
                    updatePreview(hex) {
                        document.dispatchEvent(new CustomEvent('brand-color-preview', { detail: hex }));
                    }
                }"
            >
                <div class="flex items-start gap-4">
                    <span class="brand-soft-surface flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl">
                        <i class="fa-solid fa-droplet text-lg"></i>
                    </span>

                    <div>
                        <h3 class="brand-serif text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Brand color') }}</h3>
                        <p class="mt-2 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                            {{ __('Pick your accent color. The entire SukiMarket interface — buttons, badges, active states, and navigation — updates to match.') }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 space-y-5">
                    <div class="flex flex-wrap gap-3">
                        @foreach ($brandPresets as $preset)
                            <button
                                type="button"
                                wire:click="setPreset('{{ $preset['hex'] }}')"
                                x-on:click="updatePreview('{{ $preset['hex'] }}')"
                                aria-label="{{ __('Choose :color', ['color' => $preset['label']]) }}"
                                aria-pressed="{{ $brand_color === $preset['hex'] ? 'true' : 'false' }}"
                                @class([
                                    'h-9 w-9 rounded-full border border-white/70 shadow-sm transition focus-visible:outline-hidden',
                                    'ring-2 ring-offset-2 ring-offset-stone-50 dark:ring-offset-zinc-900' => $brand_color === $preset['hex'],
                                ])
                                style="background-color: {{ $preset['hex'] }}; {{ $brand_color === $preset['hex'] ? 'box-shadow: 0 0 0 2px rgba(255,255,255,0.75);' : '' }}"
                            >
                                <span class="sr-only">{{ $preset['label'] }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div class="grid gap-2">
                            <label for="brand-color" class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">
                                {{ __('Custom color') }}
                            </label>

                            <input
                                id="brand-color"
                                type="color"
                                wire:model.live="brand_color"
                                x-on:input="updatePreview($event.target.value)"
                                class="appearance-color-input"
                            >
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <flux:button variant="primary" wire:click="saveBrandColor">
                                {{ __('Save') }}
                            </flux:button>

                            <flux:button variant="filled" wire:click="resetBrandColor" x-on:click="updatePreview('#059669')">
                                {{ __('Reset to default') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            </section>
        </x-pages::settings.layout>
    </div>
</section>

<script>
    (() => {
        if (window.sukiBrandPalettePreviewRegistered) {
            return;
        }

        window.sukiBrandPalettePreviewRegistered = true;

        const clampLightness = (value) => Math.max(0.05, Math.min(0.98, value));
        const clampChroma = (value) => Math.max(0.0, Math.min(0.37, value));
        const linearize = (value) => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
        const cubeRoot = (value) => value >= 0 ? value ** (1 / 3) : -((-value) ** (1 / 3));
        const formatOklch = (lightness, chroma, hue) => `oklch(${clampLightness(lightness).toFixed(4)} ${clampChroma(chroma).toFixed(4)} ${hue.toFixed(2)})`;

        const applyBrandPalette = (hex) => {
            if (! /^#[0-9a-fA-F]{6}$/.test(hex)) {
                return;
            }

            const r = parseInt(hex.slice(1, 3), 16) / 255;
            const g = parseInt(hex.slice(3, 5), 16) / 255;
            const b = parseInt(hex.slice(5, 7), 16) / 255;

            const rl = linearize(r);
            const gl = linearize(g);
            const bl = linearize(b);

            const X = 0.4122214708 * rl + 0.5363325363 * gl + 0.0514459929 * bl;
            const Y = 0.2119034982 * rl + 0.6806995451 * gl + 0.1073969566 * bl;
            const Z = 0.0883024619 * rl + 0.2817188376 * gl + 0.6299787005 * bl;

            const l_ = cubeRoot(X);
            const m_ = cubeRoot(Y);
            const s_ = cubeRoot(Z);

            const L = 0.2104542553 * l_ + 0.7936177850 * m_ - 0.0040720468 * s_;
            const a = 1.9779984951 * l_ - 2.4285922050 * m_ + 0.4505937099 * s_;
            const bv = 0.0259040371 * l_ + 0.7827717662 * m_ - 0.8086757660 * s_;

            const C = Math.sqrt(a ** 2 + bv ** 2);
            const H = (Math.atan2(bv, a) * 180 / Math.PI + 360) % 360;

            const stops = {
                50: formatOklch(0.97, C * 0.25, H),
                100: formatOklch(0.93, C * 0.35, H),
                200: formatOklch(0.87, C * 0.45, H),
                300: formatOklch(0.79, C * 0.60, H),
                400: formatOklch(0.70, C * 0.75, H),
                500: formatOklch(L, C, H),
                600: formatOklch(L * 0.82, C * 1.05, H),
                700: formatOklch(L * 0.68, C * 1.08, H),
                800: formatOklch(L * 0.52, C * 0.95, H),
                900: formatOklch(L * 0.36, C * 0.80, H),
                950: formatOklch(L * 0.22, C * 0.60, H),
            };

            Object.entries(stops).forEach(([stop, value]) => {
                document.documentElement.style.setProperty(`--brand-${stop}`, value);
            });

            document.documentElement.style.setProperty('--color-accent', 'var(--brand-600)');
            document.documentElement.style.setProperty('--color-accent-content', 'var(--brand-700)');
            document.documentElement.style.setProperty('--color-accent-foreground', '#ffffff');
        };

        document.addEventListener('brand-color-preview', (event) => {
            applyBrandPalette(event.detail);
        });
    })();
</script>
