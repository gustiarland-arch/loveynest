/**
 * "Add to home screen". Android and desktop Chrome hand us an install prompt
 * once; it has to be caught early and kept until a button is pressed.
 */
import { ref } from 'vue';

export const installEvent = ref(null);
export const installed = ref(false);

export function isStandalone() {
    return window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

export function isIos() {
    return /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

if (typeof window !== 'undefined') {
    installed.value = isStandalone();
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installEvent.value = event;
    });
    window.addEventListener('appinstalled', () => {
        installed.value = true;
        installEvent.value = null;
    });
}

export async function promptInstall() {
    const event = installEvent.value;
    if (!event) return 'unavailable';
    event.prompt();
    const choice = await event.userChoice.catch(() => ({ outcome: 'dismissed' }));
    installEvent.value = null;

    return choice.outcome;
}
