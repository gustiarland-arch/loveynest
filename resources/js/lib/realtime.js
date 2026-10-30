import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Live connection to Reverb for the couple's chat. The server only sends the
 * public app key and port; the host is whatever address the page was opened
 * from, so a phone on the LAN reaches the same machine as the page itself.
 *
 * Returns null when live chat is off or cannot work from this page (an https
 * page cannot open a plain ws:// socket). Callers keep polling in that case.
 */
let echo = null;

function xsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export function connectRealtime(config) {
    if (!config?.key) return null;
    const secure = config.scheme === 'https';
    if (window.location.protocol === 'https:' && !secure) return null;
    if (echo) return echo;

    window.Pusher = Pusher;
    try {
        echo = new Echo({
            broadcaster: 'reverb',
            key: config.key,
            wsHost: window.location.hostname,
            wsPort: config.port,
            wssPort: config.port,
            forceTLS: secure,
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
            auth: { headers: { 'X-XSRF-TOKEN': xsrfToken(), Accept: 'application/json' } },
        });
    } catch {
        echo = null;
    }

    return echo;
}

export function disconnectRealtime() {
    echo?.disconnect();
    echo = null;
}
