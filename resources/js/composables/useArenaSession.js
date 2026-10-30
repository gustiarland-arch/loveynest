import { computed, ref } from 'vue';
import { usePolling } from '@/composables/usePolling';
import { http } from '@/lib/http';

/**
 * One shared game for the two of you. The server keeps the truth (who is ready, scores, whose turn);
 * this polls it and sends moves. Answers that arrive out of order never overwrite a newer one.
 */
export function useArenaSession(id) {
    const state = ref(null);
    const offset = ref(0);
    const failed = ref(false);
    let sent = 0;
    let applied = 0;
    let lastKey = '';

    function apply(data, ticket) {
        if (ticket < applied) return false;
        applied = ticket;
        offset.value = data.now - Date.now();
        const key = JSON.stringify({ ...data, now: 0 });
        const changed = key !== lastKey;
        lastKey = key;
        if (changed) state.value = data;
        return changed;
    }

    async function load() {
        const ticket = ++sent;
        const data = await http.get(`/arena/sesi/${id}/state`);
        failed.value = false;
        return apply(data, ticket);
    }

    const polling = usePolling(load, { min: 1200, max: 3000 });

    async function act(body) {
        const ticket = ++sent;
        const data = await http.post(`/arena/sesi/${id}/aksi`, body);
        const changed = apply(data, ticket);
        polling.poke();
        return changed;
    }

    /** The server's clock now, in milliseconds. */
    const serverNow = () => Date.now() + offset.value;

    return { state, act, serverNow, failed, failing: polling.failing, refresh: polling.pollNow, stop: polling.stop, ended: computed(() => state.value && state.value.status !== 'active') };
}
