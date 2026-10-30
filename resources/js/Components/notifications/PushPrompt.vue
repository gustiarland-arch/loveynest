<script setup>
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { BellRing, X } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import { useToast } from '@/composables/useToast';
import { permission, pushSupport, enable } from '@/lib/push';
import { NATIVE } from '@/lib/native';
import { playChime } from '@/lib/sound';
import { t } from '@/i18n';

// A gentle one-tap offer to turn on notifications on this device. Shown only when it can work:
// the browser supports push, the server has keys, and the person has not answered yet.
const page = usePage();
const toast = useToast();
const KEY = 'push.prompt.dismissed';
const state = ref('hidden'); // hidden | ask | ios | insecure
const busy = ref(false);

// The Android app is reached through FCM; the browser needs the VAPID key.
const publicKey = computed(() => (NATIVE ? page.props.app?.fcm && 'fcm' : page.props.app?.vapidPublicKey));

function recentlyDismissed() {
    try {
        return Date.now() - Number(localStorage.getItem(KEY) ?? 0) < 3 * 86400000;
    } catch {
        return false;
    }
}

onMounted(() => {
    if (!page.props.auth?.user?.hasSpace || !publicKey.value || recentlyDismissed()) return;
    const ua = navigator.userAgent;
    const ios = /iPhone|iPad|iPod/.test(ua);
    const standalone = window.matchMedia?.('(display-mode: standalone)').matches || navigator.standalone;
    const support = pushSupport();
    if (support === 'insecure') return (state.value = 'insecure');
    if (support === 'unsupported') return (state.value = ios && !standalone ? 'ios' : 'hidden');
    if (permission() === 'default') state.value = 'ask';
});

function dismiss() {
    try {
        localStorage.setItem(KEY, String(Date.now()));
    } catch {
        /* ignore */
    }
    state.value = 'hidden';
}

async function turnOn() {
    busy.value = true;
    try {
        const result = await enable(publicKey.value);
        if (result === 'active') {
            toast.success(t('settings.enabled'));
            playChime({ force: true });
            state.value = 'hidden';
        } else {
            toast.error(result === 'denied' ? t('settings.deviceDenied') : t('settings.dismissed'));
            dismiss();
        }
    } catch {
        toast.error(t('settings.subscribeFailed'));
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div v-if="state !== 'hidden'" class="glass mb-4 flex items-start gap-3 rounded-[1.25rem] p-3.5" role="region" :aria-label="t('push.promptTitle')">
        <span class="grid size-10 shrink-0 place-items-center rounded-[0.75rem] bg-blush text-berry-ink"><BellRing class="size-5" aria-hidden="true" /></span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-plum">{{ t('push.promptTitle') }}</p>
            <p class="mt-0.5 text-xs text-ink-muted">{{ state === 'ask' ? t('push.promptBody') : state === 'ios' ? t('push.promptIos') : t('push.promptInsecure') }}</p>
            <Button v-if="state === 'ask'" size="sm" class="mt-2" :loading="busy" @click="turnOn">{{ t('push.promptCta') }}</Button>
        </div>
        <button type="button" class="grid size-9 shrink-0 place-items-center rounded-full text-ink-muted hover:bg-fill" :aria-label="t('push.promptLater')" @click="dismiss"><X class="size-4" aria-hidden="true" /></button>
    </div>
</template>
