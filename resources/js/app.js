import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = null;

if (import.meta.env.VITE_PUSHER_APP_KEY) {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'ap1',
        forceTLS: true,
        authEndpoint: '/broadcasting/auth',
    });
}

document.addEventListener('brand-color-preview', (event) => {
    if (typeof event.detail !== 'string') {
        return;
    }

    document.documentElement.style.setProperty('--brand-preview', event.detail);
});

document.addEventListener('brand-color-persisted', () => {
    window.setTimeout(() => window.location.reload(), 1200);
});
