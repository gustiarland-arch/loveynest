import { reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { usePolling } from '@/composables/usePolling';
import { useToast } from '@/composables/useToast';
import { http } from '@/lib/http';
import { playChime } from '@/lib/sound';
import { sfx } from '@/lib/arenaAudio';

/**
 * The state of invitations to play, shared by the whole app: the bar that pops up when your partner invites you,
 * the "waiting" box on the Arena page, and the jump into the game once the invitation is accepted.
 */
export const arena = reactive({ incoming: null, outgoing: null, active: null, invited: null, ready: false });

let poll = null;
const answered = new Set();

/** Remember the invitation we just sent, so we are taken into the game as soon as it is accepted. */
export function expectAnswer(id) {
    arena.invited = id;
    poll?.poke();
}

export function refreshArena() {
    return poll?.pollNow();
}

export function useArenaWatch() {
    const page = usePage();
    const toast = useToast();

    poll = usePolling(async () => {
        if (!page.props.auth?.user?.hasSpace || !page.props.partner) return false;
        const data = await http.get('/arena/status');
        const before = JSON.stringify([arena.incoming?.id, arena.outgoing?.id, arena.active?.id]);

        if (data.incoming && arena.incoming?.id !== data.incoming.id && !answered.has(data.incoming.id)) {
            playChime();
            sfx('invite');
            navigator.vibrate?.([120, 60, 120]);
        }
        arena.incoming = data.incoming && !answered.has(data.incoming.id) ? data.incoming : null;
        arena.outgoing = data.outgoing;
        arena.active = data.active;
        arena.ready = true;

        if (arena.invited) {
            if (data.active?.id === arena.invited) {
                const id = arena.invited;
                arena.invited = null;
                router.visit(`/arena/sesi/${id}`);
            } else if (!data.outgoing || data.outgoing.id !== arena.invited) {
                // It was not accepted: declined, cancelled or too old.
                if (data.declined?.id === arena.invited) toast.info(`${page.props.partner?.firstName ?? 'Pasanganmu'} belum bisa main sekarang. Coba lagi nanti ya.`, { timeout: 5000 });
                arena.invited = null;
            }
        }

        return before !== JSON.stringify([arena.incoming?.id, arena.outgoing?.id, arena.active?.id]) || !!arena.incoming || !!arena.outgoing;
    }, { min: 3000, max: 6000 });

    return poll;
}

export function markAnswered(id) {
    answered.add(id);
    if (arena.incoming?.id === id) arena.incoming = null;
}
