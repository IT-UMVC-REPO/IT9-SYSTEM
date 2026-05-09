import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

window.L = window.L ?? L;

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).href,
    iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).href,
    shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).href,
});

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

function routeRequestSignal(timeoutMs) {
    if (typeof AbortSignal !== 'undefined' && typeof AbortSignal.timeout === 'function') {
        return AbortSignal.timeout(timeoutMs);
    }

    const controller = new AbortController();
    window.setTimeout(() => controller.abort(), timeoutMs);

    return controller.signal;
}

export async function fetchRoute(from, to) {
    const providers = [
        async () => {
            const body = JSON.stringify({
                locations: [
                    { lon: from.lng, lat: from.lat },
                    { lon: to.lng, lat: to.lat },
                ],
                costing: 'auto',
                directions_options: { units: 'km' },
            });
            const response = await fetch('https://valhalla1.openstreetmap.de/route', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body,
                signal: routeRequestSignal(6000),
            });
            const data = await response.json();
            const shape = data.trip?.legs?.[0]?.shape;

            if (!shape) {
                throw new Error('no shape');
            }

            return {
                latLngs: decodePolyline6(shape),
                distance: data.trip?.summary?.length,
            };
        },
        async () => {
            const url = `https://router.project-osrm.org/route/v1/driving/${from.lng},${from.lat};${to.lng},${to.lat}?overview=full&geometries=geojson&alternatives=true`;
            const response = await fetch(url, { signal: routeRequestSignal(6000) });
            const data = await response.json();
            const route = (data.routes ?? []).sort((a, b) => a.distance - b.distance)[0];

            if (!route) {
                throw new Error('no route');
            }

            return {
                latLngs: route.geometry.coordinates.map(([lng, lat]) => [lat, lng]),
                distance: route.distance / 1000,
            };
        },
    ];

    const straightLine = haversineKm(from.lat, from.lng, to.lat, to.lng);

    for (const provider of providers) {
        try {
            const result = await provider();

            if (!Number.isFinite(result.distance) || result.distance > straightLine * 5) {
                continue;
            }

            return result;
        } catch {
            // Try the next provider.
        }
    }

    return {
        latLngs: [[from.lat, from.lng], [to.lat, to.lng]],
        distance: straightLine,
    };
}

export function haversineKm(lat1, lng1, lat2, lng2) {
    const earthRadiusKm = 6371;
    const dLat = ((lat2 - lat1) * Math.PI) / 180;
    const dLng = ((lng2 - lng1) * Math.PI) / 180;
    const a = Math.sin(dLat / 2) ** 2
        + Math.cos((lat1 * Math.PI) / 180)
        * Math.cos((lat2 * Math.PI) / 180)
        * Math.sin(dLng / 2) ** 2;

    return earthRadiusKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

export function decodePolyline6(encoded) {
    const result = [];
    let index = 0;
    let lat = 0;
    let lng = 0;

    while (index < encoded.length) {
        let byte;
        let shift = 0;
        let value = 0;

        do {
            byte = encoded.charCodeAt(index) - 63;
            index += 1;
            value |= (byte & 0x1f) << shift;
            shift += 5;
        } while (byte >= 0x20);

        lat += (value & 1) ? ~(value >> 1) : value >> 1;
        shift = 0;
        value = 0;

        do {
            byte = encoded.charCodeAt(index) - 63;
            index += 1;
            value |= (byte & 0x1f) << shift;
            shift += 5;
        } while (byte >= 0x20);

        lng += (value & 1) ? ~(value >> 1) : value >> 1;
        result.push([lat / 1e6, lng / 1e6]);
    }

    return result;
}

export function sukiVendorMap() {
    return {
        map: null,
        vendorLayer: null,
        customerLayer: null,
        vendorMarkers: new Map(),
        showCustomers: false,

        async initMap(elementId = 'suki-vendor-map') {
            if (this.map) {
                return;
            }

            this.map = L.map(elementId, {
                center: [7.4479, 125.8090],
                zoom: 14,
                zoomControl: true,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(this.map);

            await this.loadVendors();
            this.openHighlightedVendor();
        },

        async loadVendors() {
            const response = await fetch('/api/map/vendors');
            const geojson = await response.json();

            if (this.vendorLayer) {
                this.map.removeLayer(this.vendorLayer);
            }

            this.vendorMarkers.clear();

            this.vendorLayer = L.geoJSON(geojson, {
                pointToLayer: (feature, latlng) => {
                    const color = feature.properties.color ?? '#059669';
                    const icon = L.divIcon({
                        className: '',
                        html: `
                            <div style="
                                width: 40px; height: 40px;
                                border-radius: 50% 50% 50% 0;
                                background: ${escapeHtml(color)};
                                border: 3px solid white;
                                box-shadow: 0 4px 12px rgba(0,0,0,0.25);
                                display: flex; align-items: center; justify-content: center;
                                transform: rotate(-45deg);
                                cursor: pointer;
                            ">
                                <span style="transform: rotate(45deg); width: 10px; height: 10px; border-radius: 9999px; background: white;"></span>
                            </div>`,
                        iconSize: [40, 40],
                        iconAnchor: [20, 40],
                        popupAnchor: [0, -44],
                    });

                    const marker = L.marker(latlng, { icon });
                    this.vendorMarkers.set(String(feature.properties.id), marker);
                    this.vendorMarkers.set(String(feature.properties.user_id), marker);

                    return marker;
                },
                onEachFeature: (feature, layer) => {
                    layer.on('click', () => {
                        window.dispatchEvent(new CustomEvent('vendor-selected', {
                            detail: feature.properties,
                        }));
                    });
                },
            }).addTo(this.map);

            if (geojson.features.length > 0) {
                this.map.fitBounds(this.vendorLayer.getBounds().pad(0.15));
            }
        },

        openHighlightedVendor() {
            const highlight = new URLSearchParams(window.location.search).get('highlight');

            if (!highlight || !this.vendorMarkers.has(highlight)) {
                return;
            }

            const marker = this.vendorMarkers.get(highlight);
            this.map.flyTo(marker.getLatLng(), 16);
            marker.fire('click');
        },

        async toggleCustomers() {
            this.showCustomers = !this.showCustomers;

            if (!this.showCustomers) {
                if (this.customerLayer) {
                    this.map.removeLayer(this.customerLayer);
                    this.customerLayer = null;
                }

                return;
            }

            const response = await fetch('/api/map/customers');

            if (response.status === 403) {
                this.showCustomers = false;
                return;
            }

            const geojson = await response.json();

            this.customerLayer = L.geoJSON(geojson, {
                pointToLayer: (feature, latlng) => {
                    const icon = L.divIcon({
                        className: '',
                        html: `
                            <div style="
                                width: 32px; height: 32px;
                                border-radius: 50%;
                                background: var(--map-customer-marker, royalblue);
                                border: 2px solid white;
                                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                                display: flex; align-items: center; justify-content: center;
                            ">
                                <span style="width: 8px; height: 8px; border-radius: 9999px; background: white;"></span>
                            </div>`,
                        iconSize: [32, 32],
                        iconAnchor: [16, 16],
                        popupAnchor: [0, -20],
                    });

                    return L.marker(latlng, { icon });
                },
                onEachFeature: (feature, layer) => {
                    const p = feature.properties;

                    layer.bindPopup(`
                        <div style="min-width:160px; font-family: sans-serif;">
                            <p style="font-weight:700; font-size:13px; margin:0 0 4px;">${escapeHtml(p.name)}</p>
                            <p style="font-size:11px; color:#888; margin:0 0 8px;">${escapeHtml(p.address || 'Tagum City')}</p>
                            <a href="${escapeHtml(p.profileUrl)}"
                                style="display:block; text-align:center; background: var(--map-customer-marker, royalblue);
                                       color:white; border-radius:8px; padding:6px; font-size:12px;
                                       font-weight:600; text-decoration:none;">
                                ${escapeHtml('View Profile ->')}
                            </a>
                        </div>
                    `, { maxWidth: 200 });
                },
            }).addTo(this.map);
        },
    };
}
