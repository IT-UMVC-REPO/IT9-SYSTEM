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

window.checkoutDeliveryMap = (config) => ({
    map: null,
    marker: null,
    dragGeocodeTimer: null,
    lat: Number(config.initialLat ?? config.defaultLat),
    lng: Number(config.initialLng ?? config.defaultLng),
    hasPin: config.initialLat !== null && config.initialLat !== undefined
        && config.initialLng !== null && config.initialLng !== undefined,
    geocoding: false,

    initMap() {
        this.$nextTick(() => {
            const L = window.L;

            if (!L || this.map) {
                return;
            }

            this.map = L.map(config.mapId, {
                center: [this.lat, this.lng],
                zoom: this.hasPin ? 17 : config.defaultZoom,
                zoomControl: true,
                scrollWheelZoom: false,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(this.map);

            if (this.hasPin) {
                this.placeMarker(L.latLng(this.lat, this.lng), { sync: false });
            }

            this.map.on('click', (event) => {
                this.placeMarker(event.latlng, { pan: true });
                this.reverseGeocode(event.latlng.lat, event.latlng.lng);
            });
        });
    },

    markerIcon() {
        return window.L.divIcon({
            className: '',
            html: '<div style="width:38px;height:38px;border-radius:50% 50% 50% 0;background:var(--brand-600,#059669);border:3px solid white;box-shadow:0 4px 14px rgba(0,0,0,.3);transform:rotate(-45deg);display:flex;align-items:center;justify-content:center"><span style="transform:rotate(45deg);width:10px;height:10px;border-radius:9999px;background:white"></span></div>',
            iconSize: [38, 38],
            iconAnchor: [19, 38],
            popupAnchor: [0, -42],
        });
    },

    placeMarker(latlng, options = {}) {
        const L = window.L;
        const nextLat = Number(latlng.lat);
        const nextLng = Number(latlng.lng);

        if (!L || !this.map || !Number.isFinite(nextLat) || !Number.isFinite(nextLng)) {
            return;
        }

        const nextLatLng = L.latLng(nextLat, nextLng);

        if (this.marker) {
            this.marker.setLatLng(nextLatLng);
        } else {
            this.marker = L.marker(nextLatLng, {
                icon: this.markerIcon(),
                draggable: true,
            }).addTo(this.map);

            this.marker.on('dragend', () => {
                const position = this.marker.getLatLng();

                this.applyCoordinates(position.lat, position.lng);
                this.scheduleDragReverseGeocode(position.lat, position.lng);
            });
        }

        this.applyCoordinates(nextLatLng.lat, nextLatLng.lng, options.sync ?? true);

        if (options.fly) {
            this.map.flyTo(nextLatLng, 17);
        } else if (options.pan) {
            this.map.panTo(nextLatLng);
        }
    },

    applyCoordinates(lat, lng, sync = true) {
        this.lat = Number(Number(lat).toFixed(6));
        this.lng = Number(Number(lng).toFixed(6));
        this.hasPin = true;

        if (sync) {
            this.syncCoordinates();
        }
    },

    scheduleDragReverseGeocode(lat, lng) {
        clearTimeout(this.dragGeocodeTimer);

        this.dragGeocodeTimer = window.setTimeout(() => {
            this.reverseGeocode(lat, lng);
        }, 350);
    },

    syncCoordinates() {
        if (typeof this.$wire?.updateDeliveryCoordinates === 'function') {
            this.$wire.updateDeliveryCoordinates(this.lat, this.lng);

            return;
        }

        this.setWireProperty('delivery_lat', this.lat);
        this.setWireProperty('delivery_lng', this.lng);
    },

    setWireProperty(property, value, live = true) {
        const setter = this.$wire?.$set ?? this.$wire?.set;

        if (setter) {
            setter.call(this.$wire, property, value, live);
        } else if (this.$wire) {
            this.$wire[property] = value;
        }
    },

    async reverseGeocode(lat, lng) {
        const normalizedLat = Number(Number(lat).toFixed(6));
        const normalizedLng = Number(Number(lng).toFixed(6));

        if (!Number.isFinite(normalizedLat) || !Number.isFinite(normalizedLng)) {
            return;
        }

        if (!this.hasPin || this.lat !== normalizedLat || this.lng !== normalizedLng) {
            this.applyCoordinates(lat, lng);
        }

        this.geocoding = true;

        try {
            const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&addressdetails=1&accept-language=en`;
            const response = await fetch(url, {
                headers: {
                    'Accept-Language': 'en',
                    'User-Agent': 'SukiMarket/1.0 (sukimarket.app)',
                },
            });
            const data = await response.json();
            const address = data.address ?? {};
            const parts = [
                [address.amenity, address.building, address.house_number].filter(Boolean).join(' '),
                address.road ?? address.pedestrian ?? address.footway ?? '',
                address.suburb ?? address.neighbourhood ?? address.village ?? address.hamlet ?? '',
                address.quarter ?? address.district ?? address.county ?? '',
                address.city ?? address.town ?? address.municipality ?? address.state_district ?? '',
            ].map((part) => String(part ?? '').trim()).filter(Boolean);
            const formatted = parts.join(', ');

            if (formatted) {
                this.setWireProperty('delivery_address', formatted, false);
            }
        } catch (error) {
            // The typed address remains editable when geocoding is unavailable.
        } finally {
            this.geocoding = false;
        }
    },

    async geocodeAddress(address) {
        const query = String(address ?? '').trim();

        if (query.length < 5 || !this.map) {
            return;
        }

        this.geocoding = true;

        try {
            const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(`${query}, Tagum City, Davao del Norte, Philippines`)}&format=jsonv2&limit=1&addressdetails=1&accept-language=en`;
            const response = await fetch(url, {
                headers: {
                    'Accept-Language': 'en',
                    'User-Agent': 'SukiMarket/1.0 (sukimarket.app)',
                },
            });
            const results = await response.json();
            const result = results?.[0];

            if (!result) {
                return;
            }

            this.placeMarker(window.L.latLng(Number(result.lat), Number(result.lon)), { pan: true });
        } catch (error) {
            // Keep the manually entered address when lookup is unavailable.
        } finally {
            this.geocoding = false;
        }
    },

    useCurrentLocation() {
        if (!navigator.geolocation || !this.map) {
            return;
        }

        this.geocoding = true;
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                this.placeMarker(window.L.latLng(lat, lng), { fly: true });
                this.reverseGeocode(lat, lng);
            },
            () => {
                this.geocoding = false;
            },
            { enableHighAccuracy: true, timeout: 8000 },
        );
    },

    destroyMap() {
        clearTimeout(this.dragGeocodeTimer);

        if (this.map) {
            this.map.remove();
            this.map = null;
            this.marker = null;
        }
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

window.sukiProfileMap = (config) => ({
    map: null,
    marker: null,
    wire: config.wire,
    mapId: config.mapId,
    geocodeTimer: null,
    initialized: false,
    lat: Number(config.lat ?? config.center?.lat ?? 7.4479),
    lng: Number(config.lng ?? config.center?.lng ?? 125.8090),

    get formattedLat() {
        return Number(this.lat).toFixed(4);
    },

    get formattedLng() {
        return Number(this.lng).toFixed(4);
    },

    init() {
        if (this.initialized) {
            return;
        }

        this.initialized = true;

        window.addEventListener('profile-address-updated', (event) => {
            this.scheduleGeocode(event.detail);
        });

        this.$nextTick(() => {
            const L = window.L;

            if (!L || this.map) {
                return;
            }

            const center = [this.lat, this.lng];

            this.map = L.map(this.mapId, {
                center,
                zoom: 15,
                zoomControl: true,
                scrollWheelZoom: false,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(this.map);

            this.marker = L.marker(center, {
                draggable: true,
            }).addTo(this.map);

            this.marker.on('dragend', () => {
                this.applyLatLng(this.marker.getLatLng());
            });

            this.map.on('click', (event) => {
                this.applyLatLng(event.latlng, true);
            });

            if (config.address) {
                this.scheduleGeocode(config.address);
            }
        });
    },

    scheduleGeocode(address) {
        clearTimeout(this.geocodeTimer);

        this.geocodeTimer = window.setTimeout(() => {
            this.geocode(address);
        }, 600);
    },

    async geocode(address) {
        const query = String(address ?? '').trim();

        if (query.length < 5 || !this.map || !this.marker) {
            return;
        }

        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(`${query}, Tagum City, Davao del Norte, Philippines`)}&format=json&limit=1`);
            const results = await response.json();
            const result = results?.[0];

            if (!result) {
                return;
            }

            this.applyLatLng({
                lat: Number(result.lat),
                lng: Number(result.lon),
            }, true);
        } catch (error) {
            // Keep the manually selected point when geocoding is unavailable.
        }
    },

    applyLatLng(latlng, pan = false) {
        const lat = Number(latlng.lat);
        const lng = Number(latlng.lng);

        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return;
        }

        this.lat = Number(lat.toFixed(6));
        this.lng = Number(lng.toFixed(6));
        this.marker?.setLatLng([this.lat, this.lng]);

        if (pan) {
            this.map?.panTo([this.lat, this.lng]);
        }

        const set = this.wire?.$set ?? this.wire?.set;

        if (set) {
            set.call(this.wire, 'lat', this.lat);
            set.call(this.wire, 'lng', this.lng);
        }
    },
});

window.sukiDatePicker = (config) => ({
    picker: null,

    init() {
        this.$nextTick(() => {
            if (!window.flatpickr || !this.$refs.datepicker || this.picker) {
                return;
            }

            this.picker = window.flatpickr(this.$refs.datepicker, {
                allowInput: true,
                dateFormat: 'Y-m-d',
                defaultDate: config.value || null,
                disableMobile: true,
                onChange: (_selectedDates, dateStr) => {
                    this.setDate(dateStr);
                },
            });
        });
    },

    setDate(dateStr) {
        const set = config.wire?.$set ?? config.wire?.set;

        if (set) {
            set.call(config.wire, config.property, dateStr);
        }
    },
});
