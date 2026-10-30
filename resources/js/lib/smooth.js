// Keeps scrolling and page changes smooth without giving up the glass look: the live blur on cards is switched
// off while the page is moving and comes back about a fifth of a second after it stops (see :root[data-busy] in app.css).
import { router } from '@inertiajs/vue3';

const root = document.documentElement;
let timer = null;
let navigating = false;

function busy(hold = 220) {
    root.setAttribute('data-busy', '');
    clearTimeout(timer);
    timer = setTimeout(() => {
        if (!navigating) root.removeAttribute('data-busy');
    }, hold);
}

// Capture phase: also catches inner scroll areas such as the chat list.
window.addEventListener('scroll', () => busy(), { capture: true, passive: true });
window.addEventListener('touchmove', () => busy(), { passive: true });
window.addEventListener('wheel', () => busy(), { passive: true });

// Prefetched pages (see AppLayout) would show old data after a save, so any change throws them away.
router.on('before', (event) => {
    if (event.detail.visit.method !== 'get') router.flushAll();
});

router.on('start', (event) => {
    navigating = true;
    busy();
    // The current page dims while the next one loads (see :root[data-nav] in app.css), a tap is answered right away.
    if (!event.detail.visit.prefetch && event.detail.visit.method === 'get' && event.detail.visit.url.pathname !== location.pathname) root.setAttribute('data-nav', '');
});
router.on('finish', () => {
    root.removeAttribute('data-nav');
    navigating = false;
    // The new page animates in (see AppLayout), so blur stays off until that movement is done.
    busy(420);
});
