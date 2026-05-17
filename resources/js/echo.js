/**
 * Broadcasting: Pusher-compatible realtime transport.
 *
 * Production points these VITE_REVERB_* values at Ably's Pusher adapter while
 * local environments may still point them at a Reverb-compatible endpoint.
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const runtimeConfig = window.sukiRealtimeConfig ?? {};
const realtimeKey = runtimeConfig.key ?? import.meta.env.VITE_REVERB_APP_KEY ?? '';
const realtimeScheme = runtimeConfig.scheme ?? import.meta.env.VITE_REVERB_SCHEME ?? 'https';
const realtimeHost = String(runtimeConfig.host ?? import.meta.env.VITE_REVERB_HOST ?? '').replace(/^https?:\/\//, '');
const realtimePort = Number(runtimeConfig.port ?? import.meta.env.VITE_REVERB_PORT ?? (realtimeScheme === 'https' ? 443 : 80));
const realtimeCluster = runtimeConfig.cluster ?? import.meta.env.VITE_REVERB_APP_CLUSTER ?? 'mt1';

if (realtimeKey && realtimeHost) {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: realtimeKey,
        cluster: realtimeCluster,
        wsHost: realtimeHost,
        httpHost: realtimeHost,
        wsPort: realtimePort,
        wssPort: realtimePort,
        forceTLS: realtimeScheme === 'https',
        encrypted: realtimeScheme === 'https',
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
    });
} else {
    window.Echo = null;
    console.warn('SukiMarket realtime is disabled because the public Echo key or host is missing.');
}
