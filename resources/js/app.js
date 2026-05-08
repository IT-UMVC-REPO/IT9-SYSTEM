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
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(this.map);

            const bounds = [];

            this.points.forEach((point) => {
                const latlng = [point.lat, point.lng];
                bounds.push(latlng);

                L.marker(latlng, { icon: this.markerIcon(point.kind) })
                    .addTo(this.map)
                    .bindPopup(`<strong>${this.escapeHtml(point.label)}</strong><br>${this.escapeHtml(point.address)}`);
            });

            if (bounds.length > 1) {
                L.polyline(bounds, {
                    color: 'var(--brand-600, #059669)',
                    weight: 4,
                    opacity: 0.72,
                    dashArray: '7 9',
                }).addTo(this.map);

                this.map.fitBounds(bounds, {
                    padding: [28, 28],
                    maxZoom: 15,
                });

                if (this.showDistance) {
                    this.distanceLabel = `~${this.distanceInKilometers(this.points[0], this.points[1])} km away`;
                }
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

    distanceInKilometers(firstPoint, secondPoint) {
        const radius = 6371;
        const toRadians = (value) => value * Math.PI / 180;
        const dLat = toRadians(secondPoint.lat - firstPoint.lat);
        const dLng = toRadians(secondPoint.lng - firstPoint.lng);
        const firstLat = toRadians(firstPoint.lat);
        const secondLat = toRadians(secondPoint.lat);
        const a = Math.sin(dLat / 2) ** 2
            + Math.cos(firstLat) * Math.cos(secondLat) * Math.sin(dLng / 2) ** 2;

        return (radius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))).toFixed(1);
    },

    escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    },
});
