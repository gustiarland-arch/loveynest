<script setup>
import { onMounted, ref } from 'vue';
import { Download, X } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import { useToast } from '@/composables/useToast';
import { NATIVE, appInfo, hasPush, installUpdate } from '@/lib/native';
import { t } from '@/i18n';

// Offers a newer app (APK) from the server. Only inside the Android app; the website never shows it.
const toast = useToast();
const info = ref(null);
const busy = ref(false);
const percent = ref(0);
const KEY = 'update.dismissed';

function dismissedFor(code) {
    try {
        return Number(localStorage.getItem(KEY)) === code;
    } catch {
        return false;
    }
}

onMounted(async () => {
    if (!NATIVE || !hasPush()) return;
    try {
        const [current, response] = await Promise.all([appInfo(), fetch('/aplikasi/versi', { headers: { Accept: 'application/json' } })]);
        if (!response.ok) return;
        const latest = await response.json();
        if (latest.code > current.versionCode && !dismissedFor(latest.code)) info.value = latest;
    } catch {
        // Offline or no file on the server: nothing to offer.
    }
});

function later() {
    try {
        localStorage.setItem(KEY, String(info.value.code));
    } catch {
        /* ignore */
    }
    info.value = null;
}

async function update() {
    busy.value = true;
    percent.value = 0;
    try {
        await installUpdate(info.value, (value) => (percent.value = Math.round(value)));
    } catch (error) {
        const code = error?.code;
        toast.error(code === 'permission' ? t('update.permission') : code === 'hash' ? t('update.hash') : t('update.failed'), { timeout: 10000 });
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div v-if="info" class="glass mb-4 flex items-start gap-3 rounded-[1.25rem] p-3.5" role="region" :aria-label="t('update.title')">
        <span class="grid size-10 shrink-0 place-items-center rounded-[0.75rem] bg-blush text-berry-ink"><Download class="size-5" aria-hidden="true" /></span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-plum">{{ t('update.title') }}</p>
            <p class="mt-0.5 text-xs text-ink-muted">{{ t('update.body', { name: info.name, notes: info.notes }) }}</p>
            <Button size="sm" class="mt-2" :loading="busy" @click="update">{{ busy ? t('update.downloading', { percent }) : t('update.button') }}</Button>
        </div>
        <button v-if="!busy" type="button" class="grid size-9 shrink-0 place-items-center rounded-full text-ink-muted hover:bg-fill" :aria-label="t('update.later')" @click="later"><X class="size-4" aria-hidden="true" /></button>
    </div>
</template>
