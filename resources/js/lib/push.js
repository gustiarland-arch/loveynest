/**
 * Browser side of Web Push. Nothing here asks for permission on its own:
 * enable() is only ever called from a button the user pressed.
 */
import { http } from '@/lib/http';
import { NATIVE, appInfo, hasPush, plugins } from '@/lib/native';

/*
 * In the Android app there is no Web Push: the same buttons register the device with Firebase (FCM) instead,
 * and the server keeps the token next to the browser subscriptions.
 */
let nativePermission = 'default';
let nativeToken = null;

async function nativeCheck() {
    const { PushNotifications } = await plugins();
    const { receive } = await PushNotifications.checkPermissions();
    nativePermission = receive === 'granted' ? 'granted' : receive === 'denied' ? 'denied' : 'default';
    return nativePermission;
}

function nativeRegister() {
    return plugins().then(
        ({ PushNotifications }) =>
            new Promise((resolve, reject) => {
                const timer = setTimeout(() => reject(new Error('push-timeout')), 20000);
                PushNotifications.addListener('registration', ({ value }) => {
                    clearTimeout(timer);
                    nativeToken = value;
                    resolve(value);
                });
                PushNotifications.addListener('registrationError', (error) => {
                    clearTimeout(timer);
                    reject(new Error(error?.error || 'push-failed'));
                });
                PushNotifications.register();
            }),
    );
}

/** Notification permission as 'granted' | 'denied' | 'default', for the browser and the app alike. */
export function permission() {
    if (NATIVE) return nativePermission;
    return typeof Notification === 'undefined' ? 'default' : Notification.permission;
}

let refreshed = false;
let booted = Promise.resolve();

/** The app re-sends its FCM token once per launch (tokens rotate), when notifications are already allowed. */
export function refreshNative() {
    if (!NATIVE) return;
    booted.then(() => {
        if (refreshed || nativePermission !== 'granted') return;
        refreshed = true;
        enable().catch(() => (refreshed = false));
    });
}

/** App start: learn the permission, open the tapped notification's page, keep the token fresh. */
export async function bootNative(visit) {
    if (!NATIVE || !hasPush()) return;
    const { PushNotifications } = await plugins();
    PushNotifications.addListener('pushNotificationActionPerformed', ({ notification }) => {
        const url = notification?.data?.url;
        if (typeof url === 'string' && url.startsWith('/') && !url.startsWith('//')) visit(url);
    });
    booted = nativeCheck().catch(() => {});
    await booted;
}

export function pushSupport() {
    if (NATIVE) return 'supported';
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return 'unsupported';
    // Plain http on a LAN address is not a secure context, so browsers hide the push APIs there.
    if (!window.isSecureContext) return 'insecure';
    return 'supported';
}

export function registerServiceWorker() {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) return Promise.resolve(null);
    return navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => null);
}

async function registration() {
    const existing = await navigator.serviceWorker.getRegistration('/');
    return existing ?? registerServiceWorker();
}

export async function currentSubscription() {
    if (NATIVE) return nativeToken ? { endpoint: `fcm:${nativeToken}` } : null;
    if (pushSupport() !== 'supported') return null;
    const reg = await registration();
    return reg ? reg.pushManager.getSubscription() : null;
}

// subscribe() can hang when the push service is unreachable (e.g. no Internet on the LAN).
function withTimeout(promise, ms) {
    return Promise.race([promise, new Promise((_, reject) => setTimeout(() => reject(new Error('push-timeout')), ms))]);
}

function keyToBytes(base64url) {
    const padded = (base64url + '='.repeat((4 - (base64url.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
}

function deviceLabel() {
    const ua = navigator.userAgent;
    const os = /Android/.test(ua) ? 'Android' : /iPhone|iPad/.test(ua) ? 'iOS' : /Windows/.test(ua) ? 'Windows' : /Mac/.test(ua) ? 'macOS' : 'Linux';
    const browser = /Edg\//.test(ua) ? 'Edge' : /Firefox\//.test(ua) ? 'Firefox' : /Chrome\//.test(ua) ? 'Chrome' : /Safari\//.test(ua) ? 'Safari' : 'Browser';
    return `${browser} · ${os}`;
}

async function save(subscription) {
    const json = subscription.toJSON();
    const encodings = window.PushManager.supportedContentEncodings ?? ['aes128gcm'];
    return http.post('/push/subscriptions', {
        ...json,
        contentEncoding: encodings.includes('aes128gcm') ? 'aes128gcm' : 'aesgcm',
        device_label: deviceLabel(),
    });
}

/**
 * @returns {Promise<'active'|'denied'|'dismissed'>}
 */
export async function enable(publicKey) {
    if (NATIVE) {
        const { PushNotifications } = await plugins();
        const { receive } = await PushNotifications.requestPermissions();
        await nativeCheck().catch(() => {});
        if (receive === 'denied') return 'denied';
        if (receive !== 'granted') return 'dismissed';
        const token = nativeToken ?? (await nativeRegister());
        await http.post('/push/fcm', {
            token,
            device_label: 'Aplikasi · Android',
        });
        return 'active';
    }
    const permission = await Notification.requestPermission();
    if (permission === 'denied') return 'denied';
    if (permission !== 'granted') return 'dismissed';

    const reg = await registration();
    await navigator.serviceWorker.ready;
    let subscription = await reg.pushManager.getSubscription();
    // A subscription made with another server key cannot be reused.
    if (subscription && subscription.options?.applicationServerKey) {
        const current = new Uint8Array(subscription.options.applicationServerKey);
        const wanted = keyToBytes(publicKey);
        if (current.length !== wanted.length || current.some((b, i) => b !== wanted[i])) {
            await subscription.unsubscribe();
            subscription = null;
        }
    }
    subscription ??= await withTimeout(reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyToBytes(publicKey) }), 20000);
    await save(subscription);
    return 'active';
}

export async function disable() {
    const subscription = await currentSubscription();
    if (!subscription) return;
    await http.delete('/push/subscriptions', { endpoint: subscription.endpoint }).catch(() => {});
    if (NATIVE) {
        nativeToken = null;
        return (await plugins()).PushNotifications.unregister().catch(() => {});
    }
    await subscription.unsubscribe().catch(() => {});
}

/** @returns {Promise<'active'|'expired'|'unknown'|'none'>} the server's view of this device */
export async function status() {
    const subscription = await currentSubscription();
    if (!subscription) return 'none';
    const { status: state } = await http.post('/push/subscriptions/status', { endpoint: subscription.endpoint });
    return state;
}

/** Called when the browser rotates the subscription; only re-saves one that was active. */
export async function refreshAfterChange(publicKey) {
    if (NATIVE) return;
    if (Notification.permission !== 'granted' || !publicKey) return;
    const reg = await registration();
    const subscription = (await reg.pushManager.getSubscription()) ?? (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyToBytes(publicKey) }));
    await save(subscription);
}

export const sendTest = () => http.post('/push/test', {});
