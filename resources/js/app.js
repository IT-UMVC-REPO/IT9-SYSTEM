// Enable View Transitions API for Livewire navigate.
document.addEventListener('livewire:navigate', () => {
    if (!document.startViewTransition) return;
});

window.global = window.global ?? window;

import './echo';
import './brand-color';
import 'emoji-picker-element';
import { RingtonePlayer } from './ringtone';
import { conversationVideoCall } from './video-call';
import { conversationVideoCallControl } from './video-call-control';
import { groupConversationVideoCall } from './group-call';
import { sukiGroupCallPip } from './group-call-pip';
import { sukiVendorMap } from './maps/vendor-map';

window.sukiRingtone = window.sukiRingtone ?? new RingtonePlayer();

window.conversationVideoCall = conversationVideoCall;
window.groupConversationVideoCall = groupConversationVideoCall;
window.conversationVideoCallControl = conversationVideoCallControl;
window.sukiGroupCallPip = window.sukiGroupCallPip ?? sukiGroupCallPip;
window.sukiVendorMap = sukiVendorMap;

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

window.sukiMessageScroller = () => ({
    _scrollTimer: null,

    init() {
        this.scrollToBottom();
    },

    scrollToBottom() {
        const scroll = () => {
            this.$el.scrollTop = this.$el.scrollHeight;
        };

        this.$nextTick(() => {
            scroll();

            if (typeof window.requestAnimationFrame === 'function') {
                window.requestAnimationFrame(scroll);
            }

            window.clearTimeout(this._scrollTimer);
            this._scrollTimer = window.setTimeout(scroll, 120);
        });
    },

    destroy() {
        window.clearTimeout(this._scrollTimer);
    },
});

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

window.typingIndicator = (config) => ({
    otherUserName: config.otherUserName,
    conversationKey: config.conversationKey,
    isTyping: false,
    myTypingTimer: null,
    channelRef: null,
    _remoteTimer: null,

    init() {
        if (!window.Echo) {
            return;
        }

        this.channelRef = window.Echo.private(`messaging.${this.conversationKey}`);
        this.channelRef.listenForWhisper('typing', (event) => {
            if (event.userId === config.authUserId) {
                return;
            }

            this.isTyping = true;
            clearTimeout(this._remoteTimer);
            this._remoteTimer = setTimeout(() => {
                this.isTyping = false;
            }, 3000);
        });
    },

    onKeydown() {
        if (this.myTypingTimer !== null) {
            return;
        }

        this.channelRef?.whisper('typing', { userId: config.authUserId });
        this.myTypingTimer = setTimeout(() => {
            this.myTypingTimer = null;
        }, 1000);
    },

    destroy() {
        clearTimeout(this.myTypingTimer);
        clearTimeout(this._remoteTimer);
    },
});

window.groupTypingIndicator = (config) => ({
    groupId: config.groupId,
    authUserId: Number(config.authUserId),
    participantSummaries: config.participantSummaries ?? {},
    typers: {},
    channelRef: null,
    myTypingTimer: null,

    get typingLabel() {
        const ids = Object.keys(this.typers).map(Number);

        if (ids.length === 0) {
            return '';
        }

        if (ids.length === 1) {
            return `${this.participantSummaries[ids[0]]?.name ?? 'Someone'} is typing...`;
        }

        if (ids.length <= 3) {
            return `${ids.map((id) => this.participantSummaries[id]?.name ?? 'Someone').join(', ')} are typing...`;
        }

        return `${ids.length} people are typing...`;
    },

    get isTyping() {
        return Object.keys(this.typers).length > 0;
    },

    init() {
        if (!window.Echo) {
            return;
        }

        this.channelRef = window.Echo.private(`group.${this.groupId}`);
        this.channelRef.listenForWhisper('typing', (event) => {
            const userId = Number(event.userId);

            if (userId === this.authUserId || !Number.isInteger(userId)) {
                return;
            }

            clearTimeout(this.typers[userId]);
            this.typers = {
                ...this.typers,
                [userId]: setTimeout(() => {
                    const nextTypers = { ...this.typers };
                    delete nextTypers[userId];
                    this.typers = nextTypers;
                }, 3000),
            };
        });
    },

    onKeydown() {
        if (this.myTypingTimer !== null) {
            return;
        }

        this.channelRef?.whisper('typing', { userId: this.authUserId });
        this.myTypingTimer = setTimeout(() => {
            this.myTypingTimer = null;
        }, 1000);
    },

    destroy() {
        clearTimeout(this.myTypingTimer);
        Object.values(this.typers).forEach((timer) => clearTimeout(timer));
        this.typers = {};
    },
});

window.onlinePresence = (config) => ({
    onlineUserIds: new Set(),
    channelRef: null,
    initialized: false,

    init() {
        if (!window.Echo) {
            return;
        }

        this.channelRef = window.Echo.join(`presence.conversation.${config.conversationKey}`)
            .here((users) => {
                this.onlineUserIds = new Set(users.map((user) => Number(user.id)));
                this.initialized = true;
            })
            .joining((user) => {
                this.onlineUserIds = new Set([...this.onlineUserIds, Number(user.id)]);
            })
            .leaving((user) => {
                const nextUserIds = new Set(this.onlineUserIds);
                nextUserIds.delete(Number(user.id));
                this.onlineUserIds = nextUserIds;
            });
    },

    isOnline(userId) {
        return this.onlineUserIds.has(Number(userId));
    },

    destroy() {
        if (this.channelRef && window.Echo) {
            window.Echo.leave(`presence.conversation.${config.conversationKey}`);
        }
    },
});

window.groupOnlinePresence = (config) => ({
    onlineUserIds: new Set(),
    channelRef: null,
    initialized: false,

    init() {
        if (!window.Echo) {
            return;
        }

        this.channelRef = window.Echo.join(`presence.group.${config.groupId}`)
            .here((users) => {
                this.onlineUserIds = new Set(users.map((user) => Number(user.id)));
                this.initialized = true;
            })
            .joining((user) => {
                this.onlineUserIds = new Set([...this.onlineUserIds, Number(user.id)]);
            })
            .leaving((user) => {
                const nextUserIds = new Set(this.onlineUserIds);
                nextUserIds.delete(Number(user.id));
                this.onlineUserIds = nextUserIds;
            });
    },

    isOnline(userId) {
        return this.onlineUserIds.has(Number(userId));
    },

    destroy() {
        if (this.channelRef && window.Echo) {
            window.Echo.leave(`presence.group.${config.groupId}`);
        }
    },
});

window.conversationSidebarPresence = (config) => ({
    onlineUsers: new Set(),
    channels: [],
    initialized: false,
    pendingHydrations: 0,

    init() {
        if (!window.Echo) {
            return;
        }

        const conversations = config.conversations ?? [];
        this.pendingHydrations = conversations.length;

        if (this.pendingHydrations === 0) {
            this.initialized = true;
            return;
        }

        conversations.forEach((conversation) => {
            const channelName = `presence.conversation.${conversation.key}`;
            const otherUserId = Number(conversation.userId);
            const channel = window.Echo.join(channelName)
                .here((users) => {
                    if (users.some((user) => Number(user.id) === otherUserId)) {
                        this.onlineUsers = new Set([...this.onlineUsers, otherUserId]);
                    }

                    this.markHydrated();
                })
                .joining((user) => {
                    if (Number(user.id) === otherUserId) {
                        this.onlineUsers = new Set([...this.onlineUsers, otherUserId]);
                    }
                })
                .leaving((user) => {
                    if (Number(user.id) !== otherUserId) {
                        return;
                    }

                    const nextUsers = new Set(this.onlineUsers);
                    nextUsers.delete(otherUserId);
                    this.onlineUsers = nextUsers;
                });

            if (channel) {
                this.channels.push(channelName);
            }
        });
    },

    markHydrated() {
        this.pendingHydrations = Math.max(0, this.pendingHydrations - 1);
        this.initialized = this.pendingHydrations === 0;
    },

    isOnline(userId) {
        return this.onlineUsers.has(Number(userId));
    },

    destroy() {
        if (!window.Echo) {
            return;
        }

        this.channels.forEach((channel) => window.Echo.leave(channel));
        this.channels = [];
    },
});

function ensureLeafletDefaultIcon(L) {
    if (!L?.Icon?.Default || L.Icon.Default.prototype._SukiMarketDefaultIconPatched) {
        return;
    }

    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).href,
        iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).href,
        shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).href,
    });
    L.Icon.Default.prototype._SukiMarketDefaultIconPatched = true;
}

window.vendorLocationMap = (mapId, zoom, vendor) => ({
    map: null,

    init() {
        this.$nextTick(() => {
            const L = window.L;
            const vendorLat = Number(vendor?.lat);
            const vendorLng = Number(vendor?.lng);

            if (!L || this.map || !vendor) {
                return;
            }

            if (!Number.isFinite(vendorLat) || !Number.isFinite(vendorLng)) {
                return;
            }

            ensureLeafletDefaultIcon(L);

            this.map = L.map(mapId, {
                center: [vendorLat, vendorLng],
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

            L.marker([vendorLat, vendorLng], { icon })
                .addTo(this.map)
                .bindPopup(`<strong>${escapeHtml(vendor.name)}</strong><br>${escapeHtml(vendor.address)}`);

            window.setTimeout(() => this.map?.invalidateSize(), 100);
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

            document.body.style.overflow = '';
            document.documentElement.style.overflow = '';

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
                    'User-Agent': 'SukiMarket/1.0 (SukiMarket.app)',
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
                    'User-Agent': 'SukiMarket/1.0 (SukiMarket.app)',
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

function routeRequestSignal(timeoutMs) {
    if (typeof AbortSignal !== 'undefined' && typeof AbortSignal.timeout === 'function') {
        return AbortSignal.timeout(timeoutMs);
    }

    const controller = new AbortController();
    window.setTimeout(() => controller.abort(), timeoutMs);

    return controller.signal;
}

const routeProviderCooldowns = new Map();
const routeCache = new Map();
const routeFailureCooldownMs = 120000;

function routeCacheKey(from, to) {
    return [from, to]
        .map((point) => `${Number(point.lat).toFixed(4)},${Number(point.lng).toFixed(4)}`)
        .join('|');
}

function isRouteProviderCoolingDown(name) {
    return (routeProviderCooldowns.get(name) ?? 0) > Date.now();
}

function coolDownRouteProvider(name) {
    routeProviderCooldowns.set(name, Date.now() + routeFailureCooldownMs);
}

async function fetchRoute(from, to) {
    const cacheKey = routeCacheKey(from, to);
    const cached = routeCache.get(cacheKey);

    if (cached) {
        return cached;
    }

    const providers = [
        async function valhallaRoute() {
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

            if (response.status === 429) {
                coolDownRouteProvider('valhalla');
                throw new Error('valhalla rate limited');
            }

            if (!response.ok) {
                throw new Error('valhalla unavailable');
            }

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
        async function osrmRoute() {
            const url = `https://router.project-osrm.org/route/v1/driving/${from.lng},${from.lat};${to.lng},${to.lat}?overview=full&geometries=geojson&alternatives=true`;
            const response = await fetch(url, { signal: routeRequestSignal(6000) });

            if (response.status === 429) {
                coolDownRouteProvider('osrm');
                throw new Error('osrm rate limited');
            }

            if (!response.ok) {
                throw new Error('osrm unavailable');
            }

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
        if (isRouteProviderCoolingDown(provider.name.replace('Route', ''))) {
            continue;
        }

        try {
            const result = await provider();

            if (!Number.isFinite(result.distance) || result.distance > straightLine * 5) {
                continue;
            }

            routeCache.set(cacheKey, result);
            return result;
        } catch {
            // Try the next provider.
        }
    }

    const fallback = {
        latLngs: [[from.lat, from.lng], [to.lat, to.lng]],
        distance: straightLine,
    };

    routeCache.set(cacheKey, fallback);

    return fallback;
}

function haversineKm(lat1, lng1, lat2, lng2) {
    const earthRadiusKm = 6371;
    const dLat = ((lat2 - lat1) * Math.PI) / 180;
    const dLng = ((lng2 - lng1) * Math.PI) / 180;
    const a = Math.sin(dLat / 2) ** 2
        + Math.cos((lat1 * Math.PI) / 180)
        * Math.cos((lat2 * Math.PI) / 180)
        * Math.sin(dLng / 2) ** 2;

    return earthRadiusKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function decodePolyline6(encoded) {
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

window.sukiOrderLocationMap = (options) => ({
    map: null,
    routeLine: null,
    riderMarker: null,
    echoChannel: null,
    riderUpdatedHandler: null,
    distanceLabel: '',
    points: options.points ?? [],
    mapId: options.mapId,
    showDistance: options.showDistance ?? false,
    zoom: options.zoom ?? 14,
    orderId: options.orderId ?? null,
    riderPoint: options.riderPoint ?? null,
    liveRider: options.liveRider ?? false,
    routeMode: options.routeMode ?? null,
    routeRequestSequence: 0,
    routeRequestKey: null,
    routeRequestPendingKey: null,

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

            document.body.style.overflow = '';
            document.documentElement.style.overflow = '';

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

            if (this.riderPoint) {
                this.updateRiderPoint(this.riderPoint, false);
            }

            if (this.liveRider && this.routeMode) {
                this.subscribeToRiderUpdates();

                if (!this.riderPoint) {
                    this.fitToVisiblePoints();
                }
            } else {
                this.drawStaticRoute();
            }
        });
    },

    destroy() {
        if (this.riderUpdatedHandler) {
            window.removeEventListener('rider-location-updated', this.riderUpdatedHandler);
            this.riderUpdatedHandler = null;
        }

        if (window.Echo && this.orderId) {
            window.Echo.leave(`order.${this.orderId}`);
        }

        this.map?.remove();
        this.map = null;
        this.routeLine = null;
        this.riderMarker = null;
    },

    subscribeToRiderUpdates() {
        this.riderUpdatedHandler = (event) => {
            const detail = event.detail ?? {};
            const eventOrderId = Number(detail.order_id ?? detail.orderId);

            if (this.orderId && Number.isFinite(eventOrderId) && eventOrderId !== Number(this.orderId)) {
                return;
            }

            this.updateRiderPoint({
                lat: detail.lat,
                lng: detail.lng,
                label: this.riderPoint?.label ?? 'Rider',
                address: this.riderPoint?.address ?? 'Live rider location',
                kind: 'rider',
            });
        };

        window.addEventListener('rider-location-updated', this.riderUpdatedHandler);

        if (window.Echo && this.orderId) {
            this.echoChannel = window.Echo.private(`order.${this.orderId}`);
            this.echoChannel.listen('.RiderLocationUpdated', (event) => {
                this.updateRiderPoint({
                    lat: event.lat,
                    lng: event.lng,
                    label: this.riderPoint?.label ?? 'Rider',
                    address: this.riderPoint?.address ?? 'Live rider location',
                    kind: 'rider',
                });
            });
        }
    },

    updateRiderPoint(point, pan = true) {
        const lat = this.numberOrNull(point.lat);
        const lng = this.numberOrNull(point.lng);

        if (lat === null || lng === null || !this.map) {
            return;
        }

        this.riderPoint = {
            ...point,
            lat,
            lng,
            kind: 'rider',
        };

        const latlng = [lat, lng];

        if (this.riderMarker) {
            this.riderMarker.setLatLng(latlng);
        } else {
            this.riderMarker = window.L.marker(latlng, {
                icon: this.markerIcon('rider'),
            }).addTo(this.map).bindPopup(`<strong>${escapeHtml(this.riderPoint.label ?? 'Rider')}</strong>`);
        }

        this.drawRiderRoute();

        if (pan) {
            this.map.panTo(latlng, { animate: true, duration: 0.8 });
        }
    },

    drawStaticRoute() {
        if (this.points.length < 2) {
            this.fitToVisiblePoints();

            return;
        }

        const [from, to] = this.points;
        this.drawRoute(from, to, 'var(--brand-600, #059669)');
    },

    drawRiderRoute() {
        const target = this.routeTarget();

        if (!this.riderPoint || !target) {
            this.fitToVisiblePoints();

            return;
        }

        this.drawRoute(this.riderPoint, target, this.routeMode === 'pickup' ? 'var(--brand-600, #059669)' : 'var(--map-customer-marker, royalblue)');
    },

    drawRoute(from, to, color) {
        const routeKey = routeCacheKey(from, to);

        if (this.routeRequestPendingKey === routeKey || (this.routeLine && this.routeRequestKey === routeKey)) {
            return;
        }

        this.routeRequestPendingKey = routeKey;
        const requestId = ++this.routeRequestSequence;

        fetchRoute(from, to)
            .then(({ latLngs, distance }) => {
                if (requestId !== this.routeRequestSequence || !this.map) {
                    return;
                }

                this.routeRequestKey = routeKey;
                this.replaceRouteLine(latLngs, color);

                if (this.showDistance) {
                    this.distanceLabel = `~${distance.toFixed(1)} km by road`;
                }
            })
            .catch(() => {
                if (requestId !== this.routeRequestSequence || !this.map) {
                    return;
                }

                this.routeRequestKey = routeKey;
                this.replaceRouteLine([[from.lat, from.lng], [to.lat, to.lng]], color);

                if (this.showDistance) {
                    this.distanceLabel = `~${haversineKm(from.lat, from.lng, to.lat, to.lng).toFixed(1)} km`;
                }
            })
            .finally(() => {
                if (this.routeRequestPendingKey === routeKey) {
                    this.routeRequestPendingKey = null;
                }
            });
    },

    replaceRouteLine(latLngs, color) {
        if (this.routeLine) {
            this.map.removeLayer(this.routeLine);
        }

        this.routeLine = window.L.polyline(latLngs, {
            color,
            weight: 4,
            opacity: 0.85,
        }).addTo(this.map);

        this.map.fitBounds(this.routeLine.getBounds(), {
            padding: [32, 32],
            maxZoom: 16,
        });
    },

    routeTarget() {
        if (this.routeMode === 'pickup') {
            return this.points.find((point) => point.kind === 'vendorGreen' || point.kind === 'vendorOrange') ?? null;
        }

        if (this.routeMode === 'dropoff') {
            return this.points.find((point) => point.kind === 'customer') ?? null;
        }

        return null;
    },

    fitToVisiblePoints() {
        const visiblePoints = [
            ...this.points,
            this.riderPoint,
        ].filter((point) => point && this.numberOrNull(point.lat) !== null && this.numberOrNull(point.lng) !== null)
            .map((point) => [Number(point.lat), Number(point.lng)]);

        if (visiblePoints.length > 1) {
            this.map.fitBounds(window.L.latLngBounds(visiblePoints).pad(0.2));
        }
    },

    numberOrNull(value) {
        const number = Number(value);

        return Number.isFinite(number) ? number : null;
    },

    markerIcon(kind) {
        const background = {
            customer: 'var(--map-customer-marker, royalblue)',
            vendorOrange: 'var(--map-vendor-warning-marker, orange)',
            vendorGreen: 'var(--brand-600, #059669)',
            rider: '#d946ef',
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

window.sukiUnifiedOrderMap = (config) => ({
    map: null,
    riderMarker: null,
    echoChannel: null,
    distanceLabel: '',
    orderId: config.orderId ?? null,

    init() {
        this.$nextTick(() => {
            const L = window.L;

            if (!L || this.map) {
                return;
            }

            ensureLeafletDefaultIcon(L);

            const customerLat = this.numberOrNull(config.customerLat);
            const customerLng = this.numberOrNull(config.customerLng);
            const vendorLat = this.numberOrNull(config.vendorLat);
            const vendorLng = this.numberOrNull(config.vendorLng);
            const riderActive = config.riderActive === true;
            const riderLat = riderActive ? this.numberOrNull(config.riderLat) : null;
            const riderLng = riderActive ? this.numberOrNull(config.riderLng) : null;
            const center = [
                customerLat ?? vendorLat ?? riderLat ?? 7.4479,
                customerLng ?? vendorLng ?? riderLng ?? 125.8090,
            ];

            this.map = L.map(config.mapId, {
                center,
                zoom: 14,
                zoomControl: true,
                scrollWheelZoom: false,
                attributionControl: false,
            });

            document.body.style.overflow = '';
            document.documentElement.style.overflow = '';

            L.control.attribution({ prefix: false }).addTo(this.map);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(this.map);

            // Customer marker
            if (customerLat !== null && customerLng !== null) {
                L.marker([customerLat, customerLng], {
                    icon: this.createPinIcon('var(--map-customer-marker, royalblue)'),
                }).addTo(this.map).bindPopup(`<strong>Your location</strong><br>${escapeHtml(config.customerAddress)}`);
            }

            // Vendor marker
            if (vendorLat !== null && vendorLng !== null) {
                L.marker([vendorLat, vendorLng], {
                    icon: this.createPinIcon('var(--map-vendor-warning-marker, orange)'),
                }).addTo(this.map).bindPopup(`<strong>${escapeHtml(config.vendorName)}</strong><br>${escapeHtml(config.vendorAddress)}`);
            }

            // Rider marker (only when active)
            if (riderActive && riderLat !== null && riderLng !== null) {
                this.riderMarker = L.marker([riderLat, riderLng], {
                    icon: this.createLetterIcon('#f97316', 'R'),
                }).addTo(this.map).bindPopup('<strong>Your rider</strong>');
                this.updateDistanceLabel(riderLat, riderLng);
            }

            // Route line between customer and vendor
            if (customerLat !== null && customerLng !== null && vendorLat !== null && vendorLng !== null) {
                const from = { lat: vendorLat, lng: vendorLng };
                const to = { lat: customerLat, lng: customerLng };

                fetchRoute(from, to)
                    .then(({ latLngs, distance }) => {
                        const routeLine = L.polyline(latLngs, {
                            color: 'var(--brand-600, #059669)',
                            weight: 4,
                            opacity: 0.8,
                        }).addTo(this.map);

                        // Fit bounds to include route + rider if present
                        const bounds = routeLine.getBounds();

                        if (riderActive && riderLat !== null && riderLng !== null) {
                            bounds.extend([riderLat, riderLng]);
                        }

                        this.map.fitBounds(bounds, {
                            padding: [32, 32],
                            maxZoom: 16,
                        });

                        if (!riderActive) {
                            this.distanceLabel = `~${distance.toFixed(1)} km by road`;
                        }
                    })
                    .catch(() => {
                        // Fallback: fit to all markers
                        this.fitToAllMarkers(L, customerLat, customerLng, vendorLat, vendorLng, riderLat, riderLng);
                    });
            } else {
                this.fitToAllMarkers(L, customerLat, customerLng, vendorLat, vendorLng, riderLat, riderLng);
            }

            // Live rider tracking via Echo
            if (riderActive && window.Echo && this.orderId) {
                this.echoChannel = window.Echo.private(`order.${this.orderId}`);
                this.echoChannel.listen('.RiderLocationUpdated', (event) => {
                    const nextLat = this.numberOrNull(event.lat);
                    const nextLng = this.numberOrNull(event.lng);

                    if (nextLat === null || nextLng === null) {
                        return;
                    }

                    const latlng = [nextLat, nextLng];

                    if (this.riderMarker) {
                        this.riderMarker.setLatLng(latlng);
                    } else {
                        this.riderMarker = L.marker(latlng, {
                            icon: this.createLetterIcon('#f97316', 'R'),
                        }).addTo(this.map).bindPopup('<strong>Your rider</strong>');
                    }

                    this.updateDistanceLabel(nextLat, nextLng);
                    this.map.panTo(latlng, { animate: true, duration: 1 });
                });
            }

            window.setTimeout(() => this.map?.invalidateSize(), 100);
        });
    },

    destroy() {
        if (window.Echo && this.orderId) {
            window.Echo.leave(`order.${this.orderId}`);
        }

        this.map?.remove();
        this.map = null;
        this.riderMarker = null;
    },

    fitToAllMarkers(L, customerLat, customerLng, vendorLat, vendorLng, riderLat, riderLng) {
        const points = [
            [customerLat, customerLng],
            [vendorLat, vendorLng],
            [riderLat, riderLng],
        ].filter(([lat, lng]) => lat !== null && lng !== null);

        if (points.length > 1) {
            this.map.fitBounds(L.latLngBounds(points).pad(0.2));
        }
    },

    updateDistanceLabel(riderLat, riderLng) {
        const customerLat = this.numberOrNull(config.customerLat);
        const customerLng = this.numberOrNull(config.customerLng);

        if (customerLat === null || customerLng === null) {
            this.distanceLabel = '';

            return;
        }

        this.distanceLabel = `~${haversineKm(riderLat, riderLng, customerLat, customerLng).toFixed(1)} km from you`;
    },

    numberOrNull(value) {
        const number = Number(value);

        return Number.isFinite(number) ? number : null;
    },

    createPinIcon(background) {
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

    createLetterIcon(color, letter) {
        return window.L.divIcon({
            className: '',
            html: `<div style="width:36px;height:36px;border-radius:50% 50% 50% 0;background:${color};border:3px solid white;box-shadow:0 4px 12px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;transform:rotate(-45deg)"><span style="transform:rotate(45deg);color:white;font-size:11px;font-weight:700">${escapeHtml(letter)}</span></div>`,
            iconSize: [36, 36],
            iconAnchor: [18, 36],
            popupAnchor: [0, -40],
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
    addressUpdatedHandler: null,
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

        this.addressUpdatedHandler = (event) => {
            this.scheduleGeocode(event.detail);
        };

        window.addEventListener('profile-address-updated', this.addressUpdatedHandler);

        this.$nextTick(() => {
            const L = window.L;
            const element = document.getElementById(this.mapId);

            if (!L || this.map || !element) {
                return;
            }

            const center = [this.lat, this.lng];

            if (element._leaflet_id) {
                element._leaflet_id = null;
            }

            this.map = L.map(element, {
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

            window.setTimeout(() => this.map?.invalidateSize(), 100);
        });
    },

    destroy() {
        clearTimeout(this.geocodeTimer);

        if (this.addressUpdatedHandler) {
            window.removeEventListener('profile-address-updated', this.addressUpdatedHandler);
        }

        this.map?.remove();
        this.map = null;
        this.marker = null;
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

        // Ensure Leaflet didn't leave overflow:hidden on body
        document.body.style.overflow = '';
        document.documentElement.style.overflow = '';

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

/* -- SukiMarket scroll-reveal (IntersectionObserver) --------------- */
const scheduleSukiReveal = (delay = 0) => {
    const reveal = () => window.sukiRevealAll?.();

    if (delay > 0) {
        setTimeout(reveal, delay);

        return;
    }

    requestAnimationFrame(reveal);
};

window.sukiRevealAll = function () {
    const targets = Array.from(document.querySelectorAll('.suki-reveal:not(.is-visible)'));

    if (!targets.length) return;

    // Immediately reveal anything already inside the viewport — no observer needed
    const vH = window.innerHeight;
    const vW = window.innerWidth;
    targets.forEach(el => {
        const r = el.getBoundingClientRect();
        if (r.top < vH && r.bottom > 0 && r.left < vW && r.right > 0) {
            el.classList.add('is-visible');
        }
    });

    // Observe remaining off-screen elements
    const remaining = Array.from(document.querySelectorAll('.suki-reveal:not(.is-visible)'));
    if (!remaining.length) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0, rootMargin: '0px 0px 0px 0px' });

    remaining.forEach(el => observer.observe(el));

    // Last-resort fallback: force-reveal anything still hidden after 1 second
    clearTimeout(window._sukiRevealFallback);
    window._sukiRevealFallback = setTimeout(() => {
        document.querySelectorAll('.suki-reveal:not(.is-visible)').forEach(el => {
            el.classList.add('is-visible');
        });
    }, 1000);
};

window.sukiReveal = window.sukiRevealAll;

const registerSukiRevealLivewireHooks = () => {
    const livewire = window.Livewire;

    if (window._sukiRevealLivewireHooksRegistered || typeof livewire?.hook !== 'function') {
        return;
    }

    window._sukiRevealLivewireHooksRegistered = true;

    livewire.hook('morphed', () => scheduleSukiReveal());
    livewire.hook('morph.added', ({ el }) => {
        if (el?.classList?.contains('suki-reveal') || el?.querySelector?.('.suki-reveal')) {
            scheduleSukiReveal();
        }
    });
};

document.addEventListener('DOMContentLoaded', () => window.sukiRevealAll());
document.addEventListener('livewire:navigated', () => window.sukiRevealAll());
document.addEventListener('livewire:updated', () => scheduleSukiReveal(50));
document.addEventListener('livewire:init', registerSukiRevealLivewireHooks);
document.addEventListener('livewire:initialized', registerSukiRevealLivewireHooks);
registerSukiRevealLivewireHooks();

/* -- SukiMarket image progressive load ------------------------------ */
window.sukiLazyImage = () => ({
    loaded: false,
    error: false,
    load(el) {
        if (!(el instanceof HTMLImageElement)) {
            return;
        }

        if (el.complete) {
            this.loaded = true;

            return;
        }

        el.addEventListener('load', () => {
            this.loaded = true;
        }, { once: true });
        el.addEventListener('error', () => {
            this.error = true;
        }, { once: true });
    },
});

/* -- Counter animation (used on stat numbers) ----------------------- */
window.sukiCounter = (target, duration = 1200) => ({
    value: 0,
    start() {
        const end = parseInt(String(target).replace(/[^0-9]/g, ''), 10);

        if (isNaN(end)) {
            this.value = target;

            return;
        }

        const startTime = performance.now();
        const step = (now) => {
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const ease = 1 - ((1 - progress) ** 3);

            this.value = Math.round(ease * end).toLocaleString('en-PH');

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };

        requestAnimationFrame(step);
    },
});

/* -- Progressive image load ---------------------------------------- */
window.sukiImg = () => ({
    loaded: false,
    error: false,
    bind(el) {
        if (!(el instanceof HTMLImageElement)) {
            return;
        }

        if (el.complete && el.naturalWidth > 0) {
            this.loaded = true;

            return;
        }

        if (el.complete && el.naturalWidth === 0) {
            this.loaded = true;
            this.error = true;

            return;
        }

        el.addEventListener('load', () => {
            this.loaded = true;
        }, { once: true });
        el.addEventListener('error', () => {
            this.error = true;
            this.loaded = true;
        }, { once: true });
    },
});

/* -- Animated counter ---------------------------------------------- */
window.sukiCount = (raw, ms = 1100) => ({
    display: '0',
    start() {
        const end = parseInt(String(raw).replace(/\D/g, ''), 10);

        if (isNaN(end)) {
            this.display = raw;

            return;
        }

        const startTime = performance.now();
        const tick = (now) => {
            const progress = Math.min((now - startTime) / ms, 1);
            const ease = 1 - ((1 - progress) ** 3);

            this.display = Math.round(ease * end).toLocaleString('en-PH');

            if (progress < 1) {
                requestAnimationFrame(tick);
            }
        };

        requestAnimationFrame(tick);
    },
});
