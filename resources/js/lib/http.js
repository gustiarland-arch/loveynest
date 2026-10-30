/**
 * Small fetch wrapper for the JSON endpoints (chat). Same-origin cookies carry
 * the session; the XSRF cookie is echoed as a header for browsers that do not
 * send Sec-Fetch-Site.
 */
import { unsealDeep } from './e2e';

export class HttpError extends Error {
    constructor(status, data) {
        super(data?.message || `HTTP ${status}`);
        this.status = status;
        this.data = data;
    }
}

function xsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : null;
}

export async function request(method, url, { body, timeout = 12000, signal } = {}) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeout);
    signal?.addEventListener('abort', () => controller.abort());

    try {
        const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        const token = xsrfToken();
        if (token) headers['X-XSRF-TOKEN'] = token;
        const form = body instanceof FormData;
        if (body !== undefined && !form) headers['Content-Type'] = 'application/json';

        const response = await fetch(url, {
            method,
            headers,
            body: body === undefined || form ? body : JSON.stringify(body),
            credentials: 'same-origin',
            signal: controller.signal,
        });
        const data = response.headers.get('content-type')?.includes('application/json') ? await unsealDeep(await response.json()) : null;

        if (response.status === 401 || response.status === 419) {
            // Session ended or expired: a full reload lands on the login page.
            window.location.reload();
        }
        if (!response.ok) throw new HttpError(response.status, data);
        return data;
    } finally {
        clearTimeout(timer);
    }
}

export const http = {
    get: (url, options) => request('GET', url, options),
    post: (url, body, options) => request('POST', url, { ...options, body }),
    patch: (url, body, options) => request('PATCH', url, { ...options, body }),
    delete: (url, body, options) => request('DELETE', url, { ...options, body }),
};

/** RFC 4122 v4 id. crypto.randomUUID needs a secure context; plain LAN http does not have one. */
export function uuid() {
    if (globalThis.crypto?.randomUUID && globalThis.isSecureContext) return crypto.randomUUID();
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
