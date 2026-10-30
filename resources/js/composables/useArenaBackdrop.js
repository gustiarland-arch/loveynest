import { onBeforeUnmount, onMounted } from 'vue';

/**
 * While an Arena page is open, the page behind it (what shows when you pull past the top or bottom on a phone)
 * wears the same colours as the scene, and pulling past the edge does nothing. Everything is put back on leaving.
 */
export function useArenaBackdrop(background) {
    onMounted(() => {
        const root = document.documentElement;
        root.style.setProperty('--ar-html', background);
        root.classList.add('ar-on');
    });
    onBeforeUnmount(() => {
        const root = document.documentElement;
        root.classList.remove('ar-on');
        root.style.removeProperty('--ar-html');
    });
}
