/**
 * Android app (Capacitor). The screens ship inside the APK; the WebView runs at https://loveynest.my.id,
 * so cookies, CSRF and every relative URL behave exactly as on the website. Only /build and the app
 * shell are served from the APK (see MainActivity), the rest goes to the real server. From version 1.3 the app also
 * keeps a locked copy of pages, photos and voice notes for use without signal (see AppWebViewClient.java).
 */
export const NATIVE = import.meta.env.VITE_NATIVE === '1';

function screen(title, body) {
    const el = document.getElementById('native-status');
    if (!el) return Promise.resolve();
    el.hidden = false;
    el.querySelector('[data-title]').textContent = title;
    el.querySelector('[data-body]').textContent = body;
    const button = el.querySelector('button');
    return new Promise((resolve) => {
        button.onclick = () => {
            el.hidden = true;
            resolve();
        };
        // Coming back online retries on its own.
        window.addEventListener('online', () => button.click(), { once: true });
    });
}

async function fetchPage(url) {
    return fetch(url, {
        credentials: 'same-origin',
        headers: {
            'X-Inertia': 'true',
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'text/html, application/xhtml+xml',
        },
    });
}

/** Pages tried, in order, when the app opens without signal on a page it has not saved. */
const OFFLINE_FALLBACK = ['/dashboard', '/chat'];

/** Asks the server for the Inertia page of the current URL, the same request a link click makes. */
export async function loadInitialPage() {
    for (;;) {
        try {
            let response = await fetchPage(location.pathname + location.search);
            // No signal and this page was never saved: open one that was (the app keeps the last copy).
            for (const url of OFFLINE_FALLBACK) {
                if (!isOfflineMissing(response)) break;
                response = await fetchPage(url);
            }
            const external = response.status === 409 && response.headers.get('x-inertia-location');
            if (external) {
                window.location.href = external;
                return new Promise(() => {});
            }
            if (response.headers.get('x-inertia')) {
                const page = await response.json();
                // A redirect (e.g. to /login) changes the address; the history entry follows it.
                if (page.url && page.url !== location.pathname + location.search) history.replaceState(history.state, '', page.url);
                return page;
            }
            if (isOfflineMissing(response)) throw new TypeError('offline');
            await screen('Server sedang bermasalah', `LoveyNest belum bisa dimuat (kode ${response.status}). Coba lagi sebentar lagi.`);
        } catch {
            await screen('Tidak ada koneksi', 'LoveyNest butuh internet untuk memuat data kalian. Periksa koneksi lalu coba lagi.');
        }
    }
}

/** The app's answer when there is no signal and nothing saved for that address. */
export function isOfflineMissing(response) {
    const get = (name) => (typeof response?.headers?.get === 'function' ? response.headers.get(name) : response?.headers?.[name]);
    return response?.status === 503 && get('x-loveynest-offline') === 'missing';
}

/** Plugins are loaded only inside the app, so the website bundle stays as it was. */
export const plugins = () => import('@capacitor/push-notifications');

/** False in a plain browser that was handed the app build (tests), true inside the APK. */
export const hasPush = () => Boolean(window.Capacitor?.isPluginAvailable?.('PushNotifications'));

let box = null;

/** The app's own plugin (see FilesPlugin.java), loaded on first use so the website bundle stays as it was. */
async function files() {
    box ??= { plugin: (await import('@capacitor/core')).registerPlugin('LoveyNestFiles') };

    return box;
}

/** @returns {Promise<{versionCode: number, versionName: string}>} */
export async function appInfo() {
    return (await files()).plugin.info();
}

/** Hands a couple key to the app, which keeps it in the Android Keystore to open locked chat notifications. */
export async function shareKey(keyId, key) {
    if (!NATIVE || !hasPush()) return;
    try {
        const raw = new Uint8Array(await crypto.subtle.exportKey('raw', key));
        let text = '';
        raw.forEach((byte) => (text += String.fromCharCode(byte)));
        const { plugin } = await files();
        await plugin.saveKey({ keyId, key: btoa(text) });
    } catch {
        // A key that cannot be exported just keeps notifications generic.
    }
}

/** Calls `visit(path)` for a notification tapped while the app was closed or running. */
export async function watchOpen(visit) {
    if (!NATIVE || !hasPush()) return;
    const { plugin } = await files();
    plugin.addListener('open', ({ url }) => url && visit(url));
    const { url } = await plugin.takeOpenUrl();
    if (url) visit(url);
}

/**
 * Downloads the new APK in the app, checks its fingerprint and opens Android's installer.
 * @param {(percent: number) => void} onProgress
 * @throws error with code 'permission' (the install permission was just opened for the user), 'hash' or 'download'
 */
export async function installUpdate(info, onProgress) {
    const { plugin } = await files();
    const handle = await plugin.addListener('updateProgress', ({ percent }) => onProgress?.(percent));
    try {
        await plugin.installUpdate({ url: info.url, sha256: info.sha256 });
    } finally {
        handle.remove();
    }
}

/**
 * The WebView ignores <a download> and blob links, so files are handed to the app, which saves them in Download.
 * Covers the CSV/JSON exports and the photobooth "Unduh" button.
 */
export function installDownloads() {
    document.addEventListener(
        'click',
        async (event) => {
            const link = event.target instanceof Element ? event.target.closest('a[download]') : null;
            if (!link || event.defaultPrevented || !link.href) return;
            event.preventDefault();
            try {
                const blob = await (await fetch(link.href, { credentials: 'same-origin' })).blob();
                const data = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(String(reader.result).split(',')[1] ?? '');
                    reader.onerror = reject;
                    reader.readAsDataURL(blob);
                });
                const name = link.getAttribute('download') || link.href.split('/').pop().split('?')[0] || 'loveynest';
                const { plugin } = await files();
                await plugin.save({ name, mime: blob.type || 'application/octet-stream', data });
            } catch (error) {
                console.error('download failed', error);
            }
        },
        false,
    );
}

/** True inside the APK from version 1.2, which has the lock, reminders, widget and sharing below. */
export const hasAppFeatures = () => NATIVE && Boolean(window.Capacitor?.isNativePlatform?.());

async function call(method, options) {
    const { plugin } = await files();
    return plugin[method](options ?? {});
}

/** @returns {Promise<{enabled: boolean, availability: 'ok'|'none'|'unsupported', biometric: boolean}>} */
export const lockStatus = () => call('lockStatus');
/** Asks for the fingerprint, then turns the app lock on or off. Rejects when cancelled. */
export const setLock = (enabled) => call('setLock', { enabled });

/** @returns {Promise<{count: number, exact: boolean, notifications: boolean}>} */
export const scheduleReminders = (items) => call('scheduleReminders', { items });
export const reminderStatus = () => call('reminderStatus');
export const testReminder = (seconds = 10) => call('testReminder', { seconds });
export const openAlarmSettings = () => call('openAlarmSettings');
export const openAppSettings = () => call('openAppSettings');
/** After logout: no alarms or widget data of this account stay on the phone. */
export const clearAccount = () => call('clearAccount');

/** @returns {Promise<{placed: number, canPin: boolean}>} */
export const widgetStatus = () => call('widgetStatus');
/** @returns {Promise<{asked: boolean}>} false when the launcher cannot add it from here */
export const addWidget = () => call('addWidget');
export const refreshWidget = () => call('refreshWidget');

/**
 * Photos sent to LoveyNest from the gallery ("Bagikan"). Calls `fn(files)` with File objects, for photos that
 * arrive while the app runs and for the ones that opened it.
 */
export async function watchShared(fn) {
    if (!hasAppFeatures()) return;
    const { plugin } = await files();
    const take = async () => {
        const { files: list } = await plugin.takeShared();
        if (!list?.length) return;
        const picked = await Promise.all(
            list.map(async (item) => {
                try {
                    const blob = await (await fetch(item.url)).blob();
                    return new File([blob], item.name || 'foto.jpg', { type: item.mime || blob.type || 'image/jpeg' });
                } catch {
                    return null;
                }
            }),
        );
        const ready = picked.filter(Boolean);
        if (ready.length) fn(ready);
    };
    plugin.addListener('shared', () => take().catch(() => {}));
    await take();
}

/** The screens ship inside the APK, so this build always runs with the native side it was made for. */
export const hasOffline = hasAppFeatures;

/** @returns {Promise<{enabled: boolean, limitMb: number, pages: number, files: number, bytes: number, waiting: number}>} */
export const offlineStatus = () => call('offlineStatus');
/** Turning it off also removes what was kept. */
export const setOffline = (options) => call('setOffline', options);
export const clearOffline = () => call('clearOffline');

/** Chat messages written without signal (already locked when E2E is on), kept until they are sent. */
export const outbox = {
    list: async () => (hasOffline() ? (await call('outboxList')).items ?? [] : []),
    put: (item) => (hasOffline() ? call('outboxPut', { item }) : Promise.resolve()),
    remove: (id) => (hasOffline() ? call('outboxRemove', { id }) : Promise.resolve()),
};
