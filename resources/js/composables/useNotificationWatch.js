import { router, usePage } from '@inertiajs/vue3';
import { usePolling } from '@/composables/usePolling';
import { useToast } from '@/composables/useToast';
import { http } from '@/lib/http';
import { playChime } from '@/lib/sound';

/**
 * While the app is open, notices new notifications within seconds even when Web Push is slow or unavailable:
 * plays the chime, shows a banner and refreshes the bell. Only runs while the page is visible (see usePolling).
 */
export function useNotificationWatch() {
    const page = usePage();
    const toast = useToast();
    let seen = null;
    let lastUnread = null;

    usePolling(async () => {
        if (!page.props.auth?.user?.hasSpace) return false;
        const { unread, items } = await http.get('/notifications/latest');
        const ids = new Set(items.map((item) => item.id));
        let found = false;

        if (seen) {
            const fresh = items.filter((item) => !seen.has(item.id));
            if (fresh.length) {
                found = true;
                const reading = location.pathname.startsWith('/chat');
                const audible = fresh.filter((item) => !(reading && item.type === 'chat_message'));
                if (audible.length) {
                    playChime();
                    const first = audible[0];
                    toast.info([first.title, first.body].filter(Boolean).join(' · ') + (audible.length > 1 ? ` (+${audible.length - 1})` : ''), { timeout: 5000 });
                }
            }
        }
        seen = new Set([...(seen ?? []), ...ids]);

        if (lastUnread !== null && unread !== lastUnread) router.reload({ only: ['badges'], preserveScroll: true, preserveState: true });
        lastUnread = unread;
        return found;
    }, { min: 15000, max: 45000 });
}
