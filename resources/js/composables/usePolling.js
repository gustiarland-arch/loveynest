import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Adaptive polling that only runs while the page is mounted AND visible.
 *
 * - Starts at `min` ms; each quiet poll stretches the gap by 1.5× up to `max`.
 * - `poke()` (activity, e.g. a new or sent message) snaps back to `min`.
 * - Failures back off exponentially up to `errorMax` and set `failing`.
 * - Hidden tab or unmount: no timers, no requests.
 *
 * `task` returns true when it found something new.
 */
export function usePolling(task, { min = 4000, max = 15000, errorMax = 60000 } = {}) {
    const failing = ref(false);
    const running = ref(false);
    let delay = min;
    let timer = null;
    let inFlight = false;
    let stopped = true;

    function schedule(ms) {
        clearTimeout(timer);
        if (!stopped && document.visibilityState === 'visible') timer = setTimeout(tick, ms);
    }

    async function tick() {
        if (inFlight || stopped || document.visibilityState !== 'visible') return;
        inFlight = true;
        try {
            const changed = await task();
            failing.value = false;
            delay = changed ? min : Math.min(Math.round(delay * 1.5), max);
        } catch {
            failing.value = true;
            delay = Math.min(Math.max(delay * 2, min * 2), errorMax);
        } finally {
            inFlight = false;
            schedule(delay);
        }
    }

    function onVisibility() {
        if (document.visibilityState === 'visible') {
            delay = min;
            tick();
        } else {
            clearTimeout(timer);
        }
    }

    function onOnline() {
        delay = min;
        tick();
    }

    function start() {
        if (!stopped) return;
        stopped = false;
        running.value = true;
        document.addEventListener('visibilitychange', onVisibility);
        window.addEventListener('online', onOnline);
        schedule(min);
    }

    function stop() {
        stopped = true;
        running.value = false;
        clearTimeout(timer);
        document.removeEventListener('visibilitychange', onVisibility);
        window.removeEventListener('online', onOnline);
    }

    function poke() {
        delay = min;
        schedule(min);
    }

    onMounted(start);
    onBeforeUnmount(stop);

    return { failing, running, poke, pollNow: tick, stop };
}
