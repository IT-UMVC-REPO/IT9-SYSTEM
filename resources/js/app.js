window.global = window.global ?? window;

import './echo';
import './brand-color';
import 'emoji-picker-element';
import { RingtonePlayer } from './ringtone';
import { conversationVideoCall } from './video-call';
import { conversationVideoCallControl } from './video-call-control';
import { groupConversationVideoCall } from './group-call';
import { sukiVendorMap } from './maps/vendor-map';

window.sukiRingtone = window.sukiRingtone ?? new RingtonePlayer();

window.conversationVideoCall = conversationVideoCall;
window.groupConversationVideoCall = groupConversationVideoCall;
window.conversationVideoCallControl = conversationVideoCallControl;
window.sukiVendorMap = sukiVendorMap;

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

window.stepperButton = (callback) => ({
    _timer: null,
    _interval: null,

    start() {
        this.stop();
        callback();

        this._timer = setTimeout(() => {
            this._interval = setInterval(callback, 100);
        }, 400);
    },

    stop() {
        clearTimeout(this._timer);
        clearInterval(this._interval);
        this._timer = null;
        this._interval = null;
    },
});

window.vendorLocationMap = (mapId, zoom, vendor) => ({
    map: null,

    init() {
        this.$nextTick(() => {
            const L = window.L;

            if (!L || this.map || !vendor) {
                return;
            }

            this.map = L.map(mapId, {
                center: [vendor.lat, vendor.lng],
                zoom,
                zoomControl: true,
                scrollWheelZoom: false,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(this.map);

            const icon = L.divIcon({
                className: '',
                html: '<div style="width:40px;height:40px;border-radius:50% 50% 50% 0;background:var(--brand-600,#059669);border:3px solid white;box-shadow:0 4px 12px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;transform:rotate(-45deg)"><span style="transform:rotate(45deg);width:10px;height:10px;border-radius:9999px;background:white"></span></div>',
                iconSize: [40, 40],
                iconAnchor: [20, 40],
                popupAnchor: [0, -44],
            });

            L.marker([vendor.lat, vendor.lng], { icon })
                .addTo(this.map)
                .bindPopup(`<strong>${escapeHtml(vendor.name)}</strong><br>${escapeHtml(vendor.address)}`);
        });
    },
});

window.sukiOrderLocationMap = (options) => ({
    map: null,
    distanceLabel: '',
    points: options.points ?? [],
    mapId: options.mapId,
    showDistance: options.showDistance ?? false,
    zoom: options.zoom ?? 14,

    init() {
        this.$nextTick(() => {
            const L = window.L;

            if (!L || this.map || this.points.length === 0) {
                return;
            }

            const firstPoint = this.points[0];

            this.map = L.map(this.mapId, {
                center: [firstPoint.lat, firstPoint.lng],
                zoom: this.zoom,
                zoomControl: true,
                scrollWheelZoom: false,
                attributionControl: false,
            });

            L.control.attribution({ prefix: false }).addTo(this.map);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(this.map);

            this.points.forEach((point) => {
                const latlng = [point.lat, point.lng];

                L.marker(latlng, { icon: this.markerIcon(point.kind) })
                    .addTo(this.map)
                    .bindPopup(`<strong>${escapeHtml(point.label)}</strong><br>${escapeHtml(point.address)}`);
            });

            if (this.points.length > 1) {
                const [from, to] = this.points;
                const url = `https://router.project-osrm.org/route/v1/driving/${from.lng},${from.lat};${to.lng},${to.lat}?overview=full&geometries=geojson`;

                fetch(url)
                    .then((response) => response.json())
                    .then((data) => {
                        const coords = data.routes?.[0]?.geometry?.coordinates;

                        if (!coords) {
                            return;
                        }

                        const latLngs = coords.map(([lng, lat]) => [lat, lng]);
                        const routeLine = L.polyline(latLngs, {
                            color: 'var(--brand-600, #059669)',
                            weight: 4,
                            opacity: 0.75,
                        }).addTo(this.map);

                        this.map.fitBounds(routeLine.getBounds(), {
                            padding: [28, 28],
                            maxZoom: 15,
                        });

                        if (this.showDistance) {
                            const meters = data.routes[0].distance;
                            this.distanceLabel = `~${(meters / 1000).toFixed(1)} km by road`;
                        }
                    })
                    .catch(() => {});
            }
        });
    },

    markerIcon(kind) {
        const background = {
            customer: 'var(--map-customer-marker, royalblue)',
            vendorOrange: 'var(--map-vendor-warning-marker, orange)',
            vendorGreen: 'var(--brand-600, #059669)',
        }[kind] ?? 'var(--brand-600, #059669)';

        return window.L.divIcon({
            className: '',
            html: `
                <div style="
                    width: 34px; height: 34px;
                    border-radius: 50% 50% 50% 0;
                    background: ${background};
                    border: 3px solid white;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.22);
                    display: flex; align-items: center; justify-content: center;
                    transform: rotate(-45deg);
                ">
                    <span style="transform: rotate(45deg); width: 9px; height: 9px; border-radius: 9999px; background: white;"></span>
                </div>`,
            iconSize: [34, 34],
            iconAnchor: [17, 34],
            popupAnchor: [0, -38],
        });
    },

});
