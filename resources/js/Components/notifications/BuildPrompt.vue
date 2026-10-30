<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { RefreshCw, X } from '@lucide/vue';
import { t } from '@/i18n';

// Tells people when a newer LoveyNest has been put on the server, in the browser app and in the Android app alike.
// The server's build id is compared with the one this page was loaded with; the page itself is never reloaded without a tap.
const stale = ref(false);
let current = null;
let timer = 0;
const KEY = 'build.dismissed';

function dismissedFor(id) {
    try {
        return sessionStorage.getItem(KEY) === id;
    } catch {
        return false;
    }
}

let latest = null;
async function check() {
    if (!current || stale.value || document.visibilityState !== 'visible') return;
    try {
        const response = await fetch('/aplikasi/build', { headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (!response.ok) return;
        latest = (await response.json()).build;
        if (latest && latest !== 'dev' && latest !== current && !dismissedFor(latest)) stale.value = true;
    } catch {
        // Offline: nothing to tell.
    }
}

function later() {
    try {
        sessionStorage.setItem(KEY, latest ?? '');
    } catch {
        /* ignore */
    }
    stale.value = false;
}

const reload = () => window.location.reload();

onMounted(() => {
    current = document.querySelector('meta[name="usandus-build"]')?.content || null;
    check();
    timer = window.setInterval(check, 5 * 60 * 1000);
    document.addEventListener('visibilitychange', check);
});
onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', check);
});
</script>

<template>
    <div v-if="stale" class="fixed inset-x-0 top-0 z-[60] flex justify-center px-4 pt-[max(0.5rem,env(safe-area-inset-top))]" role="status" data-testid="build-prompt">
        <div class="flex w-full max-w-md items-center gap-3 rounded-[1.25rem] bg-surface p-3 shadow-lift ring-1 ring-line">
            <span class="grid size-9 shrink-0 place-items-center rounded-[0.75rem] bg-blush text-berry-ink"><RefreshCw class="size-[1.125rem]" aria-hidden="true" /></span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-plum">{{ t('build.title') }}</p>
                <p class="text-xs text-ink-muted">{{ t('build.body') }}</p>
            </div>
            <button type="button" class="h-9 shrink-0 rounded-full bg-berry px-3 text-sm font-semibold text-white active:scale-95" data-testid="build-reload" @click="reload">{{ t('build.button') }}</button>
            <button type="button" class="grid size-9 shrink-0 place-items-center rounded-full text-ink-muted hover:bg-fill" :aria-label="t('update.later')" @click="later"><X class="size-4" aria-hidden="true" /></button>
        </div>
    </div>
</template>
