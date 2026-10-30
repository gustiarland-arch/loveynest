import '../css/app.css';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { i18n, t } from './i18n';
import AppLayout from './Layouts/AppLayout.vue';
import GuestLayout from './Layouts/GuestLayout.vue';
import { bootNative, registerServiceWorker } from './lib/push';
import { NATIVE, clearAccount, hasAppFeatures, installDownloads, isOfflineMissing, loadInitialPage, watchOpen, watchShared } from './lib/native';
import { flush as flushOutbox } from './lib/outbox';
import { syncReminders } from './lib/reminders';
import { receive as receiveShared } from './lib/shared';
import './lib/install';
import './lib/smooth';
import { MotionPlugin } from '@vueuse/motion';
import { boot as bootE2e, e2e, onLockedError, unsealDeep } from './lib/e2e';
import { useToast } from './composables/useToast';

const guestPages = ['Welcome', 'Auth/', 'Error', 'Onboarding/'];
// Full-screen pages that draw their own chrome.
const barePages = ['Chat/', 'Arena/Play'];

const pages = import.meta.glob('./Pages/**/*.vue');

// iOS Safari ignores user-scalable=no, so pinch zoom is cancelled here (touch-action in the CSS stops double-tap zoom).
for (const type of ['gesturestart', 'gesturechange', 'gestureend']) document.addEventListener(type, (e) => e.preventDefault());
document.addEventListener('touchmove', (e) => e.touches.length > 1 && e.preventDefault(), { passive: false });
// Lets the server tell the installed home-screen app from a browser tab (shown in Profil > Privasi).
try {
    const installed = NATIVE || window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    document.cookie = `ln_app=${installed ? 1 : 0}; path=/; max-age=31536000; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
} catch {
    // Labels fall back to the browser name.
}

const start = (page) =>
    createInertiaApp({
        page,
        // Locked text in the props is opened before the page renders, so it never flashes as gibberish.
        resolve: async (name, page) => {
            const [module] = await Promise.all([pages[`./Pages/${name}.vue`]?.(), bootE2e(page.props).then(() => unsealDeep(page.props))]);
            if (!module) throw new Error(`Page not found: ${name}`);
            return module.default ?? module;
        },
        title: (title) => (title ? `${title} · LoveyNest` : 'LoveyNest'),
        layout: (name) => {
            if (barePages.some((prefix) => name.startsWith(prefix))) return null;
            return guestPages.some((prefix) => name.startsWith(prefix)) ? GuestLayout : AppLayout;
        },
        progress: { color: '#C88FA8', delay: 60 },
        withApp(app) {
            app.use(i18n);
            app.use(MotionPlugin);
        },
    });

if (NATIVE) {
    // The app has no service worker (push comes through FCM) and asks the server for its first page itself.
    navigator.serviceWorker
        ?.getRegistrations()
        .then((list) => list.forEach((r) => r.unregister()))
        .catch(() => {});
    installDownloads();
    const started = loadInitialPage().then(start);
    // A tapped notification can arrive before the first page is up (cold start).
    const open = (url) => started.then(() => router.visit(url));
    bootNative(open).catch(() => {});
    watchOpen(open).catch(() => {});
    started.then(() => watchShared(receiveShared)).catch(() => {});
    started.then(() => syncReminders({ force: true })).catch(() => {});
    if (hasAppFeatures()) {
        // Reminders rung by the phone: refreshed on start, after any change (a non-GET visit) and on return.
        let signedIn = null;
        router.on('navigate', (event) => {
            const user = Boolean(event.detail.page.props?.auth?.user);
            if (user && signedIn !== true) syncReminders({ force: true });
            // Logged out: this account's alarms and widget data leave the phone.
            if (!user && signedIn !== false) clearAccount().catch(() => {});
            signedIn = user;
        });
        router.on('finish', (event) => {
            if (signedIn && event.detail.visit.method !== 'get') syncReminders();
        });
        document.addEventListener('visibilitychange', () => {
            if (signedIn && document.visibilityState === 'visible') syncReminders();
        });

        // Without signal: a page the phone has not saved says so instead of an error box.
        router.on('httpException', (event) => {
            if (!isOfflineMissing(event.detail.response)) return;
            event.preventDefault();
            toast.error(t('offline.notSaved'));
        });
        router.on('networkError', (event) => {
            event.preventDefault();
            toast.error(t('offline.noSignal'));
        });
        // Chat messages written without signal go out once it is back.
        started.then(() => flushOutbox()).catch(() => {});
        window.addEventListener('online', () => flushOutbox().catch(() => {}));
        document.addEventListener('visibilitychange', () => {
            if (signedIn && document.visibilityState === 'visible') flushOutbox().catch(() => {});
        });
    }
} else {
    start();
    registerServiceWorker();
}

// Locked photos and voice notes are opened by the service worker. When a new one takes over (first visit or an
// update), the page reloads once so that pictures already on screen are fetched through it.
navigator.serviceWorker?.addEventListener('controllerchange', () => {
    if (!e2e.enabled) return;
    try {
        if (sessionStorage.getItem('usandus-sw-reload')) return;
        sessionStorage.setItem('usandus-sw-reload', '1');
    } catch {
        return;
    }
    window.location.reload();
});

// A save without the couple key says why on screen; plain text is never sent instead.
const toast = useToast();
onLockedError((message) => toast.error(message));

// A tapped notification asks the already-open app to go to its page.
navigator.serviceWorker?.addEventListener('message', (event) => {
    const url = event.data?.type === 'navigate' ? event.data.url : null;
    if (typeof url === 'string' && url.startsWith('/') && !url.startsWith('//')) router.visit(url);
});

// A page restored from the back/forward cache may belong to an ended session.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) window.location.reload();
});
