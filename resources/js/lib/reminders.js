import { hasAppFeatures, scheduleReminders } from '@/lib/native';
import { LOCKED, unsealString } from '@/lib/e2e';

/**
 * Android app: hands the coming reminders (next 14 days) to the phone, which rings them itself, also offline.
 * Locked titles are opened here with the couple key, so the alarm shows the real task or event name.
 */
let running = null;
let lastRun = 0;

export function syncReminders({ force = false } = {}) {
    if (!hasAppFeatures()) return Promise.resolve(null);
    if (running) return running;
    if (!force && Date.now() - lastRun < 5000) return Promise.resolve(null);
    running = run()
        .catch(() => null)
        .finally(() => {
            running = null;
            lastRun = Date.now();
        });

    return running;
}

async function run() {
    const response = await fetch('/aplikasi/pengingat', { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    // Offline or logged out: the phone keeps what it already has.
    if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) return null;
    const { items } = await response.json();
    const ready = await Promise.all(
        (items ?? []).map(async (item) => {
            let title = item.title;
            if (typeof item.sealedTitle === 'string' && item.sealedTitle.includes('e2e1:')) {
                const opened = await unsealString(item.sealedTitle).catch(() => null);
                if (opened && !opened.includes(LOCKED)) title = opened;
            } else if (item.sealedTitle) {
                title = item.sealedTitle;
            }
            return { id: item.id, at: item.at, title, body: item.body, url: item.url, tag: item.tag };
        }),
    );

    return scheduleReminders(ready);
}
