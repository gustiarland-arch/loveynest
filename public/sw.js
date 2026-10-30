/*
 * LoveyNest service worker.
 *
 * Caches only what is safe to keep on a shared phone: hashed build assets,
 * icons, and the offline page. Pages and JSON carry private data and always
 * go to the network.
 *
 * Locked (end-to-end encrypted) photos and voice notes arrive from the server
 * as "LNE1" files. They are opened here with the couple key this browser keeps
 * in IndexedDB, so <img> and <audio> receive the real picture or sound. The
 * format is written by resources/js/lib/e2e/core.js; keep the two in step.
 */
const VERSION = 'v2';
const STATIC_CACHE = `usandus-static-${VERSION}`;
const PRECACHE = ['/offline.html', '/offline.js', '/icons/icon-192.png', '/icons/badge-96.png', '/icons/favicon.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key.startsWith('usandus-') && key !== STATIC_CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (LOCKED_MEDIA.test(url.pathname)) {
        event.respondWith(openLocked(request));
        return;
    }

    // Full page loads: network only, with an offline page when the server is unreachable.
    if (request.mode === 'navigate') {
        // One quiet retry first: a brief mobile-data hiccup should not show the offline page.
        event.respondWith(
            fetch(request).catch(() => new Promise((resolve) => setTimeout(resolve, 1200)).then(() => fetch(request)).catch(() => caches.match('/offline.html'))),
        );
        return;
    }

    // Build assets have content hashes in their names, so a cached copy never goes stale.
    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                        }
                        return response;
                    }),
            ),
        );
        return;
    }

    // Icons keep their names across releases: show the cached one, refresh it in the background.
    if (url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.open(STATIC_CACHE).then((cache) =>
                cache.match(request).then((cached) => {
                    const fresh = fetch(request).then((response) => {
                        if (response.ok) cache.put(request, response.clone());
                        return response;
                    });
                    return cached || fresh;
                }),
            ),
        );
        return;
    }
    // Everything else (Inertia JSON, chat polling, API calls) is left to the browser.
});

// Photos and voice notes that may be locked (the server marks a locked one with X-E2E: 1).
const LOCKED_MEDIA = /^\/(photos\/\d+\/(thumb|full)|chat\/photos\/\d+\/[a-z]+|chat\/audio\/\d+|photobooth\/foto\/\d+|impian\/\d+\/foto)$/;

async function openLocked(request) {
    const range = request.headers.get('range');
    let response = await fetch(request);
    if (response.headers.get('x-e2e') !== '1') return response;
    // A slice of a locked file cannot be opened; the whole file is needed.
    if (response.status === 206) response = await fetch(request.url, { credentials: 'same-origin' });
    if (!response.ok) return response;
    const sealed = new Uint8Array(await response.arrayBuffer());
    const opened = await openFile(sealed).catch(() => null);
    // Without the key (device not approved yet) the file stays as it is.
    if (!opened) return new Response(sealed, { status: 200, headers: { 'Content-Type': 'application/octet-stream' } });
    return slice(opened.bytes, opened.type, range);
}

/** A Range request (audio seeking, Safari) gets 206 with its part, the rest gets the whole file. */
function slice(bytes, type, range) {
    const headers = { 'Content-Type': type || 'application/octet-stream', 'Accept-Ranges': 'bytes', 'Cache-Control': 'no-store' };
    const match = range && /^bytes=(\d*)-(\d*)$/.exec(range.trim());
    if (!match || (!match[1] && !match[2]) || !bytes.length) {
        return new Response(bytes, { status: 200, headers: { ...headers, 'Content-Length': String(bytes.length) } });
    }
    const start = match[1] ? Number(match[1]) : Math.max(0, bytes.length - Number(match[2]));
    const end = match[1] && match[2] ? Math.min(Number(match[2]), bytes.length - 1) : bytes.length - 1;
    if (start >= bytes.length || start > end) {
        return new Response(null, { status: 416, headers: { ...headers, 'Content-Range': `bytes */${bytes.length}` } });
    }
    return new Response(bytes.subarray(start, end + 1), {
        status: 206,
        headers: { ...headers, 'Content-Range': `bytes ${start}-${end}/${bytes.length}`, 'Content-Length': String(end - start + 1) },
    });
}

/** "LNE1" + key id length + key id + iv + AES-GCM(mime length + mime + bytes), as in core.js. */
async function openFile(view) {
    const dec = new TextDecoder();
    if (view.length < 18 || dec.decode(view.subarray(0, 4)) !== 'LNE1') return null;
    const kidLength = view[4];
    const keyId = dec.decode(view.subarray(5, 5 + kidLength));
    const key = await coupleKey(keyId);
    if (!key) return null;
    const ivStart = 5 + kidLength;
    const plain = new Uint8Array(
        await crypto.subtle.decrypt(
            { name: 'AES-GCM', iv: view.subarray(ivStart, ivStart + 12), additionalData: new TextEncoder().encode(`LNE1:${keyId}`) },
            key,
            view.subarray(ivStart + 12),
        ),
    );
    const typeLength = plain[0];

    return { type: dec.decode(plain.subarray(1, 1 + typeLength)), bytes: plain.subarray(1 + typeLength) };
}

/** The couple key kept by the page (resources/js/lib/e2e/store.js): IndexedDB "loveynest-e2e", entry "key:<id>". */
function coupleKey(keyId) {
    return new Promise((resolve) => {
        const open = indexedDB.open('loveynest-e2e', 1);
        open.onupgradeneeded = () => {
            if (!open.result.objectStoreNames.contains('kv')) open.result.createObjectStore('kv');
        };
        open.onerror = () => resolve(null);
        open.onsuccess = () => {
            try {
                const get = open.result.transaction('kv', 'readonly').objectStore('kv').get(`key:${keyId}`);
                get.onsuccess = () => resolve(get.result?.key ?? null);
                get.onerror = () => resolve(null);
            } catch {
                resolve(null);
            }
        };
    });
}

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = {};
    }
    const title = data.title || 'LoveyNest';
    event.waitUntil(
        self.clients.matchAll({ type: 'window' }).then((clients) => {
            // Someone already reading the chat does not need a banner for it.
            const watchingChat = clients.some((client) => client.visibilityState === 'visible' && new URL(client.url).pathname === '/chat');
            if (data.tag === 'chat' && watchingChat) return undefined;

            return self.registration.showNotification(title, {
                body: data.body || '',
                tag: data.tag || undefined,
                renotify: Boolean(data.tag),
                icon: '/icons/icon-192.png',
                badge: '/icons/badge-96.png',
                lang: 'id',
                data: { url: data.url || '/dashboard' },
            });
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const path = event.notification.data && event.notification.data.url;
    // Only same-origin paths are opened, whatever the payload says.
    const target = new URL(typeof path === 'string' && path.startsWith('/') && !path.startsWith('//') ? path : '/dashboard', self.location.origin);

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const existing = clients.find((client) => new URL(client.url).origin === self.location.origin);
            if (existing) {
                // The open app navigates itself (keeps the session and avoids a full reload).
                existing.postMessage({ type: 'navigate', url: target.pathname + target.search });
                return existing.focus();
            }
            return self.clients.openWindow(target.href);
        }),
    );
});

// The browser rotated the subscription: tell the server about the new one.
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil(
        self.clients.matchAll({ type: 'window' }).then((clients) => clients.forEach((client) => client.postMessage({ type: 'push-subscription-changed' }))),
    );
});
