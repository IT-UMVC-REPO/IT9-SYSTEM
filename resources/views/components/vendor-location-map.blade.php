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
        x-data="{
            map: null,
            init() {
                this.$nextTick(() => {
                    const L = window.L;

                    if (! L || this.map) {
                        return;
                    }

                    const vendor = @js($vendorPoint);
                    const escapeHtml = (value) => String(value ?? '')
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;')
                        .replaceAll(String.fromCharCode(34), '&quot;')
                        .replaceAll('\'', '&#039;')
                    ;

                    this.map = L.map(@js($mapId), {
                        center: [vendor.lat, vendor.lng],
                        zoom: @js($zoom),
                        zoomControl: true,
                        scrollWheelZoom: false,
                    });

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href=&quot;https://www.openstreetmap.org/copyright&quot;>OpenStreetMap</a>',
                        maxZoom: 19,
                    }).addTo(this.map);

                    const icon = L.divIcon({
                        className: '',
                        html: `
                            <div style=&quot;
                                width: 40px; height: 40px;
                                border-radius: 50% 50% 50% 0;
                                background: var(--brand-600, #059669);
                                border: 3px solid white;
                                box-shadow: 0 4px 12px rgba(0,0,0,0.25);
                                display: flex; align-items: center; justify-content: center;
                                transform: rotate(-45deg);
                            &quot;>
                                <span style=&quot;transform: rotate(45deg); width: 10px; height: 10px; border-radius: 9999px; background: white;&quot;></span>
                            </div>`,
                        iconSize: [40, 40],
                        iconAnchor: [20, 40],
                        popupAnchor: [0, -44],
                    });

                    L.marker([vendor.lat, vendor.lng], { icon })
                        .addTo(this.map)
                        .bindPopup(`<strong>${escapeHtml(vendor.name)}</strong><br>${escapeHtml(vendor.address)}`);
                });
            },
        }"
        x-init="init()"
        {{ $attributes->merge(['class' => 'overflow-hidden rounded-[1.5rem] border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-900']) }}
    >
        <div id="{{ $mapId }}" wire:ignore class="w-full" style="height: {{ $height }}"></div>
    </div>
@endif
