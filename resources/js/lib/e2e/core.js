/**
 * End-to-end encryption primitives (WebCrypto only, no extra library).
 *
 * Text:  e2e1:<key id>:<base64url(iv + AES-GCM ciphertext)>
 * Files: "LNE1" + key id length + key id + iv + AES-GCM(mime length + mime + bytes)
 * Space keys travel locked for one device: ECDH P-256 with a throwaway key, HKDF-SHA-256, AES-GCM.
 * The same format is read by public/sw.js; keep the two in step.
 */
const enc = new TextEncoder();
const dec = new TextDecoder();
const subtle = () => globalThis.crypto?.subtle;

export const TOKEN_PATTERN = /e2e1:[A-Za-z0-9]{8}:[A-Za-z0-9_-]{38,}/g;
export const FILE_MAGIC = 'LNE1';

export function supported() {
    return Boolean(globalThis.isSecureContext && subtle() && globalThis.indexedDB);
}

export function b64u(bytes) {
    let s = '';
    const view = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    for (let i = 0; i < view.length; i += 0x8000) s += String.fromCharCode(...view.subarray(i, i + 0x8000));
    return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

export function unb64u(text) {
    const s = atob(text.replace(/-/g, '+').replace(/_/g, '/') + '==='.slice((text.length + 3) % 4));
    const out = new Uint8Array(s.length);
    for (let i = 0; i < s.length; i++) out[i] = s.charCodeAt(i);
    return out;
}

const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
export function randomKeyId() {
    return [...crypto.getRandomValues(new Uint8Array(8))].map((b) => ALPHABET[b % ALPHABET.length]).join('');
}

export function newSpaceKey() {
    return subtle().generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']);
}

/** The device key pair; the private half cannot be exported, only used. */
export async function newDeviceKeys() {
    const pair = await subtle().generateKey({ name: 'ECDH', namedCurve: 'P-256' }, false, ['deriveBits']);
    const jwk = await subtle().exportKey('jwk', pair.publicKey);

    return { privateKey: pair.privateKey, publicJwk: { kty: 'EC', crv: 'P-256', x: jwk.x, y: jwk.y } };
}

/** Twelve digits both screens show, so the two of you can check a new device is really yours. */
export async function fingerprint(publicJwk) {
    const jwk = typeof publicJwk === 'string' ? JSON.parse(publicJwk) : publicJwk;
    const hash = new Uint8Array(await subtle().digest('SHA-256', enc.encode(`${jwk.x}.${jwk.y}`)));
    const digits = [0, 1, 2].map((i) => String(((hash[i * 3] << 16) | (hash[i * 3 + 1] << 8) | hash[i * 3 + 2]) % 10000).padStart(4, '0'));

    return digits.join(' ');
}

async function wrapKeyFor(ecdhPrivate, ecdhPublic, salt) {
    const bits = await subtle().deriveBits({ name: 'ECDH', public: ecdhPublic }, ecdhPrivate, 256);
    const hkdf = await subtle().importKey('raw', bits, 'HKDF', false, ['deriveKey']);

    return subtle().deriveKey(
        { name: 'HKDF', hash: 'SHA-256', salt, info: enc.encode('loveynest-wrap-v1') },
        hkdf,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt'],
    );
}

/** Locks a space key so that only the device with `publicJwk` can open it. */
export async function wrapSpaceKey(spaceKey, keyId, publicJwk) {
    const jwk = typeof publicJwk === 'string' ? JSON.parse(publicJwk) : publicJwk;
    const recipient = await subtle().importKey('jwk', { kty: 'EC', crv: 'P-256', x: jwk.x, y: jwk.y }, { name: 'ECDH', namedCurve: 'P-256' }, false, []);
    const eph = await subtle().generateKey({ name: 'ECDH', namedCurve: 'P-256' }, true, ['deriveBits']);
    const epk = await subtle().exportKey('jwk', eph.publicKey);
    const salt = crypto.getRandomValues(new Uint8Array(16));
    const kek = await wrapKeyFor(eph.privateKey, recipient, salt);
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const raw = await subtle().exportKey('raw', spaceKey);
    const ct = await subtle().encrypt({ name: 'AES-GCM', iv, additionalData: enc.encode(keyId) }, kek, raw);

    return JSON.stringify({ v: 1, x: epk.x, y: epk.y, s: b64u(salt), iv: b64u(iv), ct: b64u(ct) });
}

export async function unwrapSpaceKey(wrapped, keyId, devicePrivate) {
    const w = JSON.parse(wrapped);
    const epk = await subtle().importKey('jwk', { kty: 'EC', crv: 'P-256', x: w.x, y: w.y }, { name: 'ECDH', namedCurve: 'P-256' }, false, []);
    const kek = await wrapKeyFor(devicePrivate, epk, unb64u(w.s));
    const raw = await subtle().decrypt({ name: 'AES-GCM', iv: unb64u(w.iv), additionalData: enc.encode(keyId) }, kek, unb64u(w.ct));

    return subtle().importKey('raw', raw, { name: 'AES-GCM' }, true, ['encrypt', 'decrypt']);
}

export async function sealText(text, key, keyId) {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const ct = new Uint8Array(await subtle().encrypt({ name: 'AES-GCM', iv, additionalData: enc.encode(`e2e1:${keyId}`) }, key, enc.encode(text)));
    const out = new Uint8Array(12 + ct.length);
    out.set(iv);
    out.set(ct, 12);

    return `e2e1:${keyId}:${b64u(out)}`;
}

/** @returns {{keyId: string, data: Uint8Array}} */
export function parseToken(token) {
    const [, keyId, data] = token.split(':');

    return { keyId, data: unb64u(data) };
}

export async function openText(token, key) {
    const { keyId, data } = parseToken(token);
    const plain = await subtle().decrypt({ name: 'AES-GCM', iv: data.subarray(0, 12), additionalData: enc.encode(`e2e1:${keyId}`) }, key, data.subarray(12));

    return dec.decode(plain);
}

export async function sealBytes(bytes, mime, key, keyId) {
    const type = enc.encode((mime || 'application/octet-stream').slice(0, 100));
    const body = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    const plain = new Uint8Array(1 + type.length + body.length);
    plain[0] = type.length;
    plain.set(type, 1);
    plain.set(body, 1 + type.length);
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const ct = new Uint8Array(await subtle().encrypt({ name: 'AES-GCM', iv, additionalData: enc.encode(`LNE1:${keyId}`) }, key, plain));

    const kid = enc.encode(keyId);
    const head = new Uint8Array(4 + 1 + kid.length + 12);
    head.set(enc.encode(FILE_MAGIC));
    head[4] = kid.length;
    head.set(kid, 5);
    head.set(iv, 5 + kid.length);

    return new Blob([head, ct], { type: 'application/octet-stream' });
}

/** Key id of a locked file, or null for a plain one. */
export function fileKeyId(bytes) {
    const view = new Uint8Array(bytes);
    if (view.length < 18 || dec.decode(view.subarray(0, 4)) !== FILE_MAGIC) return null;

    return dec.decode(view.subarray(5, 5 + view[4]));
}

export async function openBytes(bytes, key) {
    const view = new Uint8Array(bytes);
    const kidLength = view[4];
    const keyId = dec.decode(view.subarray(5, 5 + kidLength));
    const ivStart = 5 + kidLength;
    const plain = new Uint8Array(await subtle().decrypt({ name: 'AES-GCM', iv: view.subarray(ivStart, ivStart + 12), additionalData: enc.encode(`LNE1:${keyId}`) }, key, view.subarray(ivStart + 12)));
    const typeLength = plain[0];

    return { type: dec.decode(plain.subarray(1, 1 + typeLength)), bytes: plain.subarray(1 + typeLength) };
}

export const PASSWORD_ITERATIONS = 600000;

/** Key derived from the login password (PBKDF2-SHA-256) that locks the space keys for password recovery. */
export async function derivePasswordKey(password, salt, iterations = PASSWORD_ITERATIONS) {
    const base = await subtle().importKey('raw', enc.encode(String(password).normalize('NFKC')), 'PBKDF2', false, ['deriveKey']);

    return subtle().deriveKey({ name: 'PBKDF2', hash: 'SHA-256', salt, iterations }, base, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
}

/** Locks a space key with a password-derived key; the key id is bound to the ciphertext. */
export async function wrapWithPasswordKey(spaceKey, keyId, passwordKey) {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const raw = await subtle().exportKey('raw', spaceKey);
    const ct = await subtle().encrypt({ name: 'AES-GCM', iv, additionalData: enc.encode(`pw:${keyId}`) }, passwordKey, raw);

    return JSON.stringify({ v: 1, iv: b64u(iv), ct: b64u(ct) });
}

export async function unwrapWithPasswordKey(wrapped, keyId, passwordKey) {
    const w = JSON.parse(wrapped);
    const raw = await subtle().decrypt({ name: 'AES-GCM', iv: unb64u(w.iv), additionalData: enc.encode(`pw:${keyId}`) }, passwordKey, unb64u(w.ct));

    return subtle().importKey('raw', raw, { name: 'AES-GCM' }, true, ['encrypt', 'decrypt']);
}

export function newPasswordSalt() {
    return crypto.getRandomValues(new Uint8Array(16));
}
