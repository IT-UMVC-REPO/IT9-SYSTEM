@props([
    'vendorProfile' => null,
    'height' => '280px',
    'zoom' => 15,
    'mapId' => null,
    'browse' => false,
])

@php
    use Illuminate\Support\Str;

    $mapId ??= ($browse ? 'suki-vendor-map-' : 'vendor-location-map-').Str::random(8);
    $hasLocation = $vendorProfile instanceof \App\Models\VendorProfile && $vendorProfile->hasLocation();
    $vendorPoint = $hasLocation ? [
        'lat' => (float) $vendorProfile->lat,
        'lng' => (float) $vendorProfile->lng,
        'name' => $vendorProfile->store_name,
        'address' => $vendorProfile->vendor_address ?: __('Tagum City'),
    ] : null;
@endphp

@if ($browse)
    <div
        x-data="sukiVendorMap()"
        x-init="$nextTick(() => initMap(@js($mapId)))"
        x-on:vendor-map-resize.window="$nextTick(() => map?.invalidateSize())"
        {{ $attributes->merge(['class' => 'overflow-hidden rounded-[1.5rem] border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-900']) }}
    >
        <div
            id="{{ $mapId }}"
            wire:ignore
            class="w-full"
            x-bind:style="(typeof mapFullscreen !== 'undefined' && mapFullscreen) ? 'height: calc(100vh - 8rem)' : @js('height: '.$height)"
        ></div>
    </div>
@elseif ($hasLocation)
    <div
        x-data="vendorLocationMap(@js($mapId), @js($zoom), @js($vendorPoint))"
        {{ $attributes->merge(['class' => 'overflow-hidden rounded-[1.5rem] border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-900']) }}
    >
        <div id="{{ $mapId }}" wire:ignore class="w-full" style="height: {{ $height }}"></div>
    </div>
@endif
