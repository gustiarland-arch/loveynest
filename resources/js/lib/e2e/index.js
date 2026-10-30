import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    TOKEN_PATTERN,
    fileKeyId,
    fingerprint,
    derivePasswordKey,
    newDeviceKeys,
    newPasswordSalt,
    newSpaceKey,
    openBytes,
    openText,
    randomKeyId,
    sealBytes,
    sealText,
    supported,
    unb64u,
    unwrapSpaceKey,
    unwrapWithPasswordKey,
    wrapSpaceKey,
    wrapWithPasswordKey,
    b64u,
    PASSWORD_ITERATIONS,
} from './core';
import { keyStore } from './store';
import { NATIVE, shareKey } from '@/lib/native';

/**
 * End-to-end encryption for LoveyNest.
 *
 * Every phone or browser has its own key pair. The couple space has one content key, which the
 * server only ever sees locked for a device. A new device gets the content key when a device
 * that already has it approves it on screen (after comparing the code). The login password is a second
 * way in: a device that holds the key also stores it locked with a key derived from the password (in this
 * browser, PBKDF2), so entering the password on a device without the key opens it. The server only keeps
 * the locked result and a random salt. If the password is changed on a device that lacks the key, the old
 * stored copy stops matching until a device with the key stores it again.
 */
export const LOCKED = '🔒 Terkunci';

export const e2e = reactive({
    enabled: false,
    // off | unsupported | nospace | loading | ready | waiting | error
    status: 'off',
    spaceId: null,
    activeKeyId: null,
    deviceId: null,
    fingerprint: null,
    pending: [],
    devices: [],
    error: null,
    // null | 'unlock' (no key here, a password copy exists) | 'enable' (key here, no password copy yet)
    passwordPrompt: null,
});

export class LockedError extends Error {}

let userId = null;
const keys = new Map(); // keyId -> CryptoKey
let keysLoaded = null;
let refreshing = null;
let lastRefresh = 0;
let pollTimer = null;
const plainCache = new Map();
// The password typed at login (or just changed) lives in memory only, for a few minutes.
let pendingPassword = null;
let pendingTimer = null;

/** Called by the login, register and change-password forms with the password just sent. */
export function rememberPassword(password) {
    clearTimeout(pendingTimer);
    pendingPassword = typeof password === 'string' && password !== '' ? password : null;
    lastRefresh = 0;
    if (pendingPassword) pendingTimer = setTimeout(forgetPassword, 5 * 60 * 1000);
}

export function forgetPassword() {
    clearTimeout(pendingTimer);
    pendingPassword = null;
}

/** Opens the keys the server holds locked with this password; true when at least one opened. */
async function openWithPassword(password, spaceId) {
    const { wraps } = await api('POST', '/e2e/password/unlock', { password });
    const derived = new Map(); // salt|iterations -> CryptoKey, so one derivation serves every key
    let opened = 0;
    for (const row of wraps) {
        const id = `${row.salt}|${row.iterations}`;
        try {
            if (!derived.has(id)) derived.set(id, await derivePasswordKey(password, unb64u(row.salt), row.iterations));
            await storeKey(row.key_id, await unwrapWithPasswordKey(row.wrapped, row.key_id, derived.get(id)), spaceId);
            opened++;
        } catch {
            /* stored with another password (changed since), or damaged */
        }
    }

    return opened > 0;
}

/** Stores every key this device holds for the space, locked with the password. */
async function saveWithPassword(password) {
    const rows = (await keyStore.spaceKeys()).filter((row) => row.spaceId === e2e.spaceId).slice(0, 40);
    if (!rows.length) return false;
    const salt = newPasswordSalt();
    const derived = await derivePasswordKey(password, salt, PASSWORD_ITERATIONS);
    const wraps = [];
    for (const row of rows) {
        wraps.push({ key_id: row.keyId, salt: b64u(salt), iterations: PASSWORD_ITERATIONS, wrapped: await wrapWithPasswordKey(row.key, row.keyId, derived) });
    }
    await api('POST', '/e2e/password/wraps', { password, wraps });

    return true;
}

function loadKeys() {
    keysLoaded ??= keyStore
        .spaceKeys()
        .then((list) =>
            list.forEach((row) => {
                keys.set(row.keyId, row.key);
                if (NATIVE) shareKey(row.keyId, row.key);
            }),
        )
        .catch(() => {});

    return keysLoaded;
}

function xsrf() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : null;
}

async function api(method, url, body) {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const token = xsrf();
    if (token) headers['X-XSRF-TOKEN'] = token;
    if (body !== undefined) headers['Content-Type'] = 'application/json';
    const response = await fetch(url, { method, headers, credentials: 'same-origin', body: body === undefined ? undefined : JSON.stringify(body) });
    const data = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null;
    if (!response.ok) {
        const error = new Error(data?.message || `HTTP ${response.status}`);
        error.status = response.status;
        throw error;
    }

    return data;
}

function deviceLabel() {
    const ua = navigator.userAgent;
    const os = /iPhone/.test(ua) ? 'iPhone' : /iPad/.test(ua) ? 'iPad' : /Android/.test(ua) ? 'Android' : /Mac/.test(ua) ? 'Mac' : /Windows/.test(ua) ? 'Windows' : 'Perangkat';
    const browser = /CriOS|Chrome/.test(ua) && !/Edg/.test(ua) ? 'Chrome' : /Edg/.test(ua) ? 'Edge' : /Firefox|FxiOS/.test(ua) ? 'Firefox' : /Safari/.test(ua) ? 'Safari' : 'Browser';
    const app = window.matchMedia?.('(display-mode: standalone)').matches || navigator.standalone ? 'aplikasi' : browser;

    return `${os} · ${app}`;
}

async function device() {
    const name = `device:${userId}`;
    let row = await keyStore.get(name);
    if (!row) {
        const fresh = await newDeviceKeys();
        row = { id: null, ...fresh };
        await keyStore.set(name, row);
    }
    if (!row.id) {
        const { id } = await api('POST', '/e2e/devices', { public_key: JSON.stringify(row.publicJwk), label: deviceLabel() });
        row = { ...row, id };
        await keyStore.set(name, row);
    }

    return row;
}

async function storeKey(keyId, key, spaceId) {
    keys.set(keyId, key);
    if (NATIVE) shareKey(keyId, key);
    await keyStore.set(`key:${keyId}`, { key, spaceId });
}

/** Called with the page props on start and after visits; does the network part at most every 20 s. */
export function boot(props) {
    const config = props?.e2e ?? null;
    const id = props?.auth?.user?.id ?? null;
    e2e.enabled = Boolean(config);
    if (!config || !id) {
        e2e.status = 'off';
        return Promise.resolve();
    }
    if (!supported()) {
        e2e.status = 'unsupported';
        return Promise.resolve();
    }
    if (userId !== id) {
        userId = id;
        lastRefresh = 0;
    }
    const changed = config.spaceId !== e2e.spaceId || config.keyId !== e2e.activeKeyId;
    e2e.spaceId = config.spaceId;
    e2e.activeKeyId = config.keyId;
    if (!config.spaceId) {
        e2e.status = 'nospace';
        return loadKeys();
    }
    if (changed || Date.now() - lastRefresh > 20000) refresh();

    return loadKeys();
}

export function refresh() {
    refreshing ??= doRefresh()
        .catch((error) => {
            e2e.status = keys.has(e2e.activeKeyId) ? 'ready' : 'error';
            e2e.error = error.message;
        })
        .finally(() => {
            refreshing = null;
            lastRefresh = Date.now();
            schedulePoll();
        });

    return refreshing;
}

async function doRefresh() {
    if (!['ready', 'waiting'].includes(e2e.status)) e2e.status = 'loading';
    await loadKeys();
    let dev = await device();
    let status = await api('GET', `/e2e/status?device=${dev.id}`);
    if (!status.device) {
        // The server no longer knows this device (removed in settings): register again.
        await keyStore.set(`device:${userId}`, { ...dev, id: null });
        dev = await device();
        status = await api('GET', `/e2e/status?device=${dev.id}`);
    }
    e2e.deviceId = dev.id;
    e2e.fingerprint = await fingerprint(dev.publicJwk);

    let gained = false;
    for (const row of status.keys) {
        if (keys.has(row.key_id)) continue;
        try {
            await storeKey(row.key_id, await unwrapSpaceKey(row.wrapped, row.key_id, dev.privateKey), status.spaceId);
            gained = true;
        } catch {
            /* a key locked for an older key pair of this device */
        }
    }

    // No key here, but the password opens one: the usual way back in on a new phone or after clearing the browser.
    if (status.activeKeyId && !keys.has(status.activeKeyId) && status.passwordWrap && pendingPassword) {
        try {
            gained = (await openWithPassword(pendingPassword, status.spaceId)) || gained;
        } catch (error) {
            if (error.status === 422) forgetPassword();
        }
    }

    if (!status.activeKeyId && status.spaceId) {
        // First device of this space: it makes the key.
        const keyId = randomKeyId();
        const key = await newSpaceKey();
        try {
            await api('POST', '/e2e/keys', { device: dev.id, key_id: keyId, wrapped: await wrapSpaceKey(key, keyId, dev.publicJwk) });
            await storeKey(keyId, key, status.spaceId);
            status.activeKeyId = keyId;
        } catch (error) {
            if (error.status !== 409) throw error;
            status = await api('GET', `/e2e/status?device=${dev.id}`);
        }
    }

    e2e.spaceId = status.spaceId;
    e2e.activeKeyId = status.activeKeyId;
    e2e.devices = status.devices ?? [];
    e2e.pending = await Promise.all((status.pending ?? []).map(async (d) => ({ ...d, code: await fingerprint(d.publicKey) })));
    e2e.status = keys.has(status.activeKeyId) ? 'ready' : 'waiting';
    e2e.error = null;
    e2e.passwordPrompt = null;

    if (e2e.status === 'ready') {
        if (pendingPassword) {
            // Keep the password copy in step with the current password (also after a change).
            try {
                await saveWithPassword(pendingPassword);
                status.passwordWrap = true;
            } catch (error) {
                if (error.status === 422) forgetPassword();
            }
            forgetPassword();
        }
        if (!status.passwordWrap && !passwordPromptDismissed()) e2e.passwordPrompt = 'enable';
    } else if (status.passwordWrap) {
        e2e.passwordPrompt = 'unlock';
    }

    // Text that was shown as locked can be opened now.
    if (gained) router.reload();
}

function passwordPromptDismissed() {
    try {
        return sessionStorage.getItem('e2e-pw-later') === '1';
    } catch {
        return false;
    }
}

export function dismissPasswordPrompt() {
    try {
        sessionStorage.setItem('e2e-pw-later', '1');
    } catch {
        /* the prompt just shows again next time */
    }
    if (e2e.passwordPrompt === 'enable') e2e.passwordPrompt = null;
}

/** Password typed on the screen: opens the key on this device, or (key here) stores the password copy. */
export async function usePassword(password) {
    const mode = e2e.passwordPrompt;
    try {
        if (mode === 'unlock') {
            if (!(await openWithPassword(password, e2e.spaceId))) {
                throw new Error('Password ini tidak bisa membuka kunci. Mungkin password diganti setelah kunci disimpan. Coba password yang lama, atau izinkan dari perangkat lain.');
            }
            await refresh();
            // A full reload, so everything on screen (chat history, lists) is read again with the key in place.
            window.location.reload();
        } else {
            if (!(await saveWithPassword(password))) throw new LockedError('Perangkat ini belum punya kunci berdua.');
            try {
                sessionStorage.removeItem('e2e-pw-later');
            } catch {
                /* nothing to clear */
            }
            await refresh();
        }
    } catch (error) {
        if (error.status === 422) error.message = 'Password salah.';
        throw error;
    }
}

function schedulePoll() {
    clearTimeout(pollTimer);
    if (e2e.status === 'off' || e2e.status === 'unsupported' || e2e.status === 'nospace') return;
    // Waiting for approval: look often. Otherwise only to notice a new device asking.
    pollTimer = setTimeout(() => {
        if (document.visibilityState === 'visible') refresh();
        else schedulePoll();
    }, e2e.status === 'waiting' ? 5000 : 60000);
}

/** Gives every key this device holds for the space to an approved device. */
export async function approve(pendingDevice) {
    const dev = await device();
    const wraps = [];
    for (const row of await keyStore.spaceKeys()) {
        if (row.spaceId !== e2e.spaceId) continue;
        wraps.push({ key_id: row.keyId, device_id: pendingDevice.id, wrapped: await wrapSpaceKey(row.key, row.keyId, pendingDevice.publicKey) });
    }
    if (!wraps.length) throw new LockedError('Perangkat ini belum punya kunci berdua.');
    await api('POST', '/e2e/share', { device: dev.id, wraps });
    await refresh();
}

export async function removeDevice(id) {
    await api('DELETE', `/e2e/devices/${id}`);
    await refresh();
}

/** New key for the space. Whatever was locked with keys this device does not have stays locked for good. */
export async function resetKey() {
    const dev = await device();
    const keyId = randomKeyId();
    const key = await newSpaceKey();
    await api('POST', '/e2e/keys', { device: dev.id, key_id: keyId, wrapped: await wrapSpaceKey(key, keyId, dev.publicJwk), reset: true });
    await storeKey(keyId, key, e2e.spaceId);
    e2e.activeKeyId = keyId;
    await refresh();
    router.reload();
}

async function activeKey() {
    await loadKeys();
    if (!e2e.enabled) return null;
    if (e2e.status === 'unsupported') throw new LockedError('Enkripsi butuh alamat https://. Buka LoveyNest lewat alamat https.');
    if (refreshing && !keys.has(e2e.activeKeyId)) await refreshing;
    const key = e2e.activeKeyId ? keys.get(e2e.activeKeyId) : null;
    if (!key) throw new LockedError('Kunci berdua belum ada di perangkat ini. Minta izin dari perangkat yang sudah terbuka kuncinya.');

    return key;
}

/** Locks one text. With E2E off (or for empty text) the text comes back unchanged. */
export async function seal(text) {
    if (typeof text !== 'string' || text.trim() === '' || !e2e.enabled) return text;
    const key = await activeKey();

    return sealText(text, key, e2e.activeKeyId);
}

function setPath(target, path, fn) {
    const [head, ...rest] = path;
    if (target == null) return [];
    if (head === '*') return Object.keys(target).flatMap((k) => (rest.length ? setPath(target[k], rest, fn) : [fn(target, k)]));
    if (!(head in target)) return [];

    return rest.length ? setPath(target[head], rest, fn) : [fn(target, head)];
}

/** Copy of `data` with the named fields locked; "subtasks.*.title" and "options.*" reach into lists. */
export async function sealData(data, fields) {
    const copy = clone(data);
    if (!e2e.enabled) return copy;
    await activeKey();
    const jobs = fields.flatMap((field) =>
        setPath(copy, field.split('.'), (obj, k) =>
            seal(obj[k]).then((v) => {
                obj[k] = v;
            }),
        ),
    );
    await Promise.all(jobs);

    return copy;
}

/** Deep copy of plain objects and lists; files and dates are kept as they are. */
function clone(value) {
    if (Array.isArray(value)) return value.map(clone);
    if (value && typeof value === 'object' && Object.getPrototypeOf(value) === Object.prototype) {
        return Object.fromEntries(Object.entries(value).map(([k, v]) => [k, clone(v)]));
    }

    return value;
}

/**
 * Sends an Inertia form with some fields locked:
 *   submitSealed(form, ['title', 'body'], (f) => f.post('/journal', options), (d) => ({ ...d, note: d.note || null }))
 * `prepare` does what the form's own transform did. Shows the problem on the form instead of ever sending plain text.
 */
export async function submitSealed(form, fields, send, prepare = (data) => data, after = null) {
    let payload;
    try {
        payload = await sealData(prepare(form.data()), fields);
        if (after) payload = await after(payload);
    } catch (error) {
        form.setError(fields[0], error.message);
        notifyLocked(error);
        return false;
    }
    form.transform(() => payload);
    send(form);
    form.transform((data) => data);

    return true;
}

/** router.post/patch with locked fields. */
export async function sealedVisit(method, url, data, fields, options = {}) {
    let payload;
    try {
        payload = await sealData(data, fields);
    } catch (error) {
        notifyLocked(error);
        return false;
    }
    router[method](url, payload, options);

    return true;
}

let onLocked = null;
export function onLockedError(fn) {
    onLocked = fn;
}
function notifyLocked(error) {
    onLocked?.(error.message);
}

async function openToken(token) {
    if (plainCache.has(token)) return plainCache.get(token);
    const keyId = token.slice(5, 13);
    const key = keys.get(keyId);
    if (!key) return LOCKED;
    try {
        const plain = await openText(token, key);
        if (plainCache.size > 4000) plainCache.clear();
        plainCache.set(token, plain);
        return plain;
    } catch {
        return LOCKED;
    }
}

export async function unsealString(text) {
    if (typeof text !== 'string' || !text.includes('e2e1:')) return text;
    await loadKeys();
    const tokens = [...new Set(text.match(TOKEN_PATTERN) ?? [])];
    if (!tokens.length) return text;
    const opened = new Map(await Promise.all(tokens.map(async (t) => [t, await openToken(t)])));

    return text.replace(TOKEN_PATTERN, (t) => opened.get(t) ?? LOCKED);
}

/** Opens every locked token anywhere inside `value`, in place. */
export async function unsealDeep(value) {
    if (!value || typeof value !== 'object') return value;
    const jobs = [];
    const seen = new Set();
    const walk = (node) => {
        if (!node || typeof node !== 'object' || seen.has(node)) return;
        seen.add(node);
        for (const k of Object.keys(node)) {
            const v = node[k];
            if (typeof v === 'string') {
                if (v.includes('e2e1:')) jobs.push(unsealString(v).then((plain) => (node[k] = plain)));
            } else if (v && typeof v === 'object') walk(v);
        }
    };
    walk(value);
    if (jobs.length) {
        await loadKeys();
        await Promise.all(jobs);
    }

    return value;
}

/** Locks a file (photo, voice note). With E2E off the file comes back unchanged. */
export async function sealFile(blob, name = 'berkas') {
    if (!e2e.enabled || !blob) return blob;
    const key = await activeKey();
    const sealed = await sealBytes(new Uint8Array(await blob.arrayBuffer()), blob.type, key, e2e.activeKeyId);

    return new File([sealed], `${name}.lne`, { type: 'application/octet-stream' });
}

/** Opens a locked file fetched by hand (the service worker does this for <img> and <audio>). */
export async function openFile(buffer) {
    const keyId = fileKeyId(buffer);
    if (!keyId) return null;
    await loadKeys();
    const key = keys.get(keyId);
    if (!key) throw new LockedError(LOCKED);

    return openBytes(buffer, key);
}
