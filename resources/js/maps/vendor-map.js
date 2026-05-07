import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

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
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
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
                                <span style="transform: rotate(45deg); font-size: 16px;">🛒</span>
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
                    const p = feature.properties;
                    const color = escapeHtml(p.color ?? '#059669');

                    layer.bindPopup(`
                        <div style="min-width:200px; font-family: sans-serif;">
                            <img src="${escapeHtml(p.image)}" alt="${escapeHtml(p.name)}"
                                style="width:100%; height:110px; object-fit:cover; border-radius:10px; margin-bottom:8px;" />
                            <p style="font-weight:700; font-size:14px; margin:0 0 4px;">${escapeHtml(p.name)}</p>
                            <p style="font-size:12px; color:#666; margin:0 0 8px;">${escapeHtml(p.description)}</p>
                            <p style="font-size:11px; color:#888; margin:0 0 10px;">📍 ${escapeHtml(p.address || 'Tagum City')}</p>
                            <a href="${escapeHtml(p.profileUrl)}"
                                style="display:block; text-align:center; background:${color};
                                       color:white; border-radius:8px; padding:7px; font-size:13px;
                                       font-weight:600; text-decoration:none;">
                                ${escapeHtml('Visit Stall →')}
                            </a>
                        </div>
                    `, { maxWidth: 240 });
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
            marker.openPopup();
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
                                background: #3b82f6;
                                border: 2px solid white;
                                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                                display: flex; align-items: center; justify-content: center;
                                font-size: 14px;
                            ">👤</div>`,
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
                            <p style="font-size:11px; color:#888; margin:0 0 8px;">📍 ${escapeHtml(p.address || 'Tagum City')}</p>
                            <a href="${escapeHtml(p.profileUrl)}"
                                style="display:block; text-align:center; background:#3b82f6;
                                       color:white; border-radius:8px; padding:6px; font-size:12px;
                                       font-weight:600; text-decoration:none;">
                                ${escapeHtml('View Profile →')}
                            </a>
                        </div>
                    `, { maxWidth: 200 });
                },
            }).addTo(this.map);
        },
    };
}
