@props([
    'customer' => null,
    'vendorProfile' => null,
    'height' => '250px',
    'customerLabel' => 'Delivery Point',
    'vendorLabel' => 'Vendor Stall',
    'vendorMarker' => 'vendorOrange',
    'showDistance' => false,
    'heading' => 'Delivery map',
    'mapId' => null,
])

@php
    use Illuminate\Support\Str;

    $mapId ??= 'order-location-map-'.Str::random(8);
    $points = [];

    if ($customer instanceof \App\Models\User && $customer->hasLocation()) {
        $points[] = [
            'lat' => (float) $customer->lat,
            'lng' => (float) $customer->lng,
            'label' => __($customerLabel),
            'address' => $customer->address ?: __('Delivery address'),
            'kind' => 'customer',
        ];
    }

    if ($vendorProfile instanceof \App\Models\VendorProfile && $vendorProfile->hasLocation()) {
        $points[] = [
            'lat' => (float) $vendorProfile->lat,
            'lng' => (float) $vendorProfile->lng,
            'label' => __($vendorLabel),
            'address' => $vendorProfile->vendor_address ?: __('Tagum City'),
            'kind' => $vendorMarker,
        ];
    }
@endphp

@if ($points !== [])
    <section class="brand-panel overflow-hidden p-5 sm:p-6">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="brand-kicker !mb-0">{{ __($heading) }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-3 text-xs font-semibold text-neutral-500 dark:text-zinc-400">
                    @foreach ($points as $point)
                        <span class="inline-flex items-center gap-2">
                            <span @class([
                                'h-2.5 w-2.5 rounded-full',
                                'bg-[var(--brand-600)]' => $point['kind'] === 'vendorGreen',
                                'bg-orange-500' => $point['kind'] === 'vendorOrange',
                                'bg-blue-500' => $point['kind'] === 'customer',
                            ])></span>
                            {{ $point['label'] }}
                        </span>
                    @endforeach
                </div>
            </div>

            @if ($showDistance)
                <p
                    x-data="{ distanceLabel: '' }"
                    x-text="distanceLabel"
                    x-on:order-map-distance.window="distanceLabel = $event.detail"
                    class="min-h-5 text-sm font-semibold text-[var(--brand-700)] dark:text-[var(--brand-300)]"
                ></p>
            @endif
        </div>

        <div
            x-data="sukiOrderLocationMap({
                mapId: @js($mapId),
                points: @js($points),
                showDistance: @js($showDistance),
            })"
            x-init="init()"
            x-effect="if (distanceLabel) window.dispatchEvent(new CustomEvent('order-map-distance', { detail: distanceLabel }))"
            class="overflow-hidden rounded-[1.25rem] border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-900"
        >
            <div id="{{ $mapId }}" wire:ignore class="w-full" style="height: {{ $height }}"></div>
        </div>
    </section>
@endif
