/**
 * Keys kept in this browser's IndexedDB. CryptoKey objects are stored as they are, so a
 * non-exportable device key stays non-exportable. public/sw.js opens the same database.
 */
const DB_NAME = 'loveynest-e2e';
const STORE = 'kv';

let opening = null;

function open() {
    opening ??= new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => {
            if (!request.result.objectStoreNames.contains(STORE)) request.result.createObjectStore(STORE);
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => {
            opening = null;
            reject(request.error);
        };
    });

    return opening;
}

async function run(mode, fn) {
    const db = await open();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, mode);
        const request = fn(tx.objectStore(STORE));
        tx.oncomplete = () => resolve(request?.result);
        tx.onerror = () => reject(tx.error);
        tx.onabort = () => reject(tx.error);
    });
}

export const keyStore = {
    get: (name) => run('readonly', (store) => store.get(name)),
    set: (name, value) => run('readwrite', (store) => store.put(value, name)),
    delete: (name) => run('readwrite', (store) => store.delete(name)),
    /** All space keys: [{ keyId, spaceId, key }]. */
    async spaceKeys() {
        const db = await open();

        return new Promise((resolve, reject) => {
            const out = [];
            const tx = db.transaction(STORE, 'readonly');
            const cursor = tx.objectStore(STORE).openCursor();
            cursor.onsuccess = () => {
                const c = cursor.result;
                if (!c) return;
                if (typeof c.key === 'string' && c.key.startsWith('key:')) out.push({ keyId: c.key.slice(4), ...c.value });
                c.continue();
            };
            tx.oncomplete = () => resolve(out);
            tx.onerror = () => reject(tx.error);
        });
    },
};
