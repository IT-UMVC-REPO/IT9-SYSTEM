/**
 * Broadcasting: Laravel Reverb (self-hosted, localhost:8080)
 *
 * We deliberately use Reverb instead of Pusher because Pusher's ap1 cluster
 * (Singapore) introduced 80–150 ms per-hop latency for every broadcast event,
 * causing visible 10-second message delays and degraded WebRTC call signaling.
 * Reverb runs in-process via `composer dev` and has sub-millisecond latency.
 *
 * To start all services: composer dev
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
});
