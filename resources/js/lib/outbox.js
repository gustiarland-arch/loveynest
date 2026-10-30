/**
 * Chat messages written without signal (Android app). Text and stickers wait on the phone, locked like they would
 * be on the server (see Offline.java), and are sent in order as soon as there is signal again: on start, when the
 * phone comes back online, when the app returns to the screen and when chat polling works again.
 */
import { HttpError, http } from './http';
import { LockedError } from './e2e';
import { hasOffline, outbox } from './native';

const listeners = new Set();
let flushing = null;

/** True for "no signal" (or a timeout), false for an answer from the server. */
export const isNetworkError = (error) => !(error instanceof HttpError) && !(error instanceof LockedError);

/** `fn({ type: 'sent', client_uuid, message })` or `fn({ type: 'dropped', client_uuid, error })`. */
export function onOutbox(fn) {
    listeners.add(fn);
    return () => listeners.delete(fn);
}

const emit = (event) => listeners.forEach((fn) => fn(event));

/**
 * @param {{client_uuid: string, payload: object, display: object, created_at: string}} item
 *   payload is what POST /chat/messages gets (body already locked), display is what the bubble shows.
 */
export const queue = (item) => outbox.put(item);

export const waitingItems = () => outbox.list().catch(() => []);

export function flush() {
    if (!hasOffline()) return Promise.resolve();
    flushing ??= (async () => {
        try {
            for (const item of await waitingItems()) {
                try {
                    const { message } = await http.post('/chat/messages', item.payload);
                    await outbox.remove(item.client_uuid);
                    emit({ type: 'sent', client_uuid: item.client_uuid, message });
                } catch (error) {
                    if (isNetworkError(error)) return;
                    if (error instanceof HttpError && [401, 419, 429].includes(error.status)) return;
                    // Refused by the server (e.g. the reply was deleted): it would never go through.
                    await outbox.remove(item.client_uuid);
                    emit({ type: 'dropped', client_uuid: item.client_uuid, error });
                }
            }
        } finally {
            flushing = null;
        }
    })();

    return flushing;
}
