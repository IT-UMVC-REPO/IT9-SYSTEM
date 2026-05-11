/**
 * Broadcasting: Pusher-compatible realtime transport.
 *
 * Production points these VITE_REVERB_* values at Ably's Pusher adapter while
 * local environments may still point them at a Reverb-compatible endpoint.
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const realtimeScheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';
const realtimeHost = (import.meta.env.VITE_REVERB_HOST ?? '').replace(/^https?:\/\//, '');
const realtimePort = Number(import.meta.env.VITE_REVERB_PORT ?? (realtimeScheme === 'https' ? 443 : 80));

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    cluster: import.meta.env.VITE_REVERB_APP_CLUSTER ?? 'mt1',
    wsHost: realtimeHost,
    httpHost: realtimeHost,
    wsPort: realtimePort,
    wssPort: realtimePort,
    forceTLS: realtimeScheme === 'https',
    encrypted: realtimeScheme === 'https',
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
});
