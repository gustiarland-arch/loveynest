<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Fingerprint, AlarmClock, LayoutGrid, Music, Share2, Smartphone, CloudOff } from '@lucide/vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Toggle from '@/Components/ui/Toggle.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { useToast } from '@/composables/useToast';
import { hasAppFeatures, lockStatus, setLock, reminderStatus, testReminder, openAlarmSettings, openAppSettings, widgetStatus, addWidget, offlineStatus, setOffline, clearOffline } from '@/lib/native';
import { syncReminders } from '@/lib/reminders';
import { soundOn, setSound, playSound } from '@/lib/sound';
import { t } from '@/i18n';

// Android app only: the features that live on the phone itself.
const toast = useToast();
const app = hasAppFeatures();
const lock = ref({ enabled: false, availability: 'unsupported', biometric: false });
const lockBusy = ref(false);
const reminders = ref(null);
const widget = ref({ placed: 0, canPin: false });
const sound = ref(soundOn());
const tones = ['peluk', 'cium', 'kangen', 'semangat', 'selesai', 'kabar', 'pengingat'];
const offline = ref(null);
const limits = [100, 300, 500, 1000];
const clearOpen = ref(false);

const megabytes = (bytes) => (bytes < 1024 * 1024 ? '< 1' : String(Math.round(bytes / (1024 * 1024))));

async function load() {
    if (!app) return;
    try {
        [lock.value, widget.value, offline.value] = await Promise.all([lockStatus(), widgetStatus(), offlineStatus().catch(() => null)]);
        reminders.value = (await syncReminders({ force: true })) ?? (await reminderStatus());
    } catch {
        // An older app without these features: the page says so below.
    }
}

async function toggleLock(value) {
    if (lockBusy.value || lock.value.availability !== 'ok') {
        lock.value = { ...lock.value };
        return;
    }
    lockBusy.value = true;
    try {
        lock.value = { ...lock.value, ...(await setLock(value)) };
        toast.success(value ? t('aplikasi.lockOn') : t('aplikasi.lockOff'));
    } catch {
        lock.value = { ...lock.value };
    } finally {
        lockBusy.value = false;
    }
}

async function tryReminder() {
    await testReminder(10);
    toast.success(t('aplikasi.testSent'), { timeout: 8000 });
}

async function pinWidget() {
    const { asked } = await addWidget();
    if (!asked) toast.info(t('aplikasi.widgetManual'), { timeout: 8000 });
}

async function changeOffline(options) {
    offline.value = await setOffline(options);
    if (options.enabled === false) toast.success(t('aplikasi.offlineOffDone'));
}

async function emptyOffline() {
    clearOpen.value = false;
    offline.value = await clearOffline();
    toast.success(t('aplikasi.offlineCleared'));
}

function toggleSound(value) {
    sound.value = value;
    setSound(value);
}

function onVisible() {
    if (document.visibilityState === 'visible') load();
}

onMounted(() => {
    load();
    document.addEventListener('visibilitychange', onVisible);
});
onBeforeUnmount(() => document.removeEventListener('visibilitychange', onVisible));
</script>

<template>
    <Head :title="t('aplikasi.title')" />
    <PageHeader :title="t('aplikasi.title')" :lead="t('aplikasi.lead')" back="/pengaturan" />

    <Card v-if="!app">
        <h2 class="flex items-center gap-2 text-lg font-semibold"><Smartphone class="size-4 text-ink-muted" aria-hidden="true" />{{ t('aplikasi.onlyApp') }}</h2>
        <p class="mt-2 text-sm text-ink-muted">{{ t('aplikasi.onlyAppBody') }}</p>
        <!-- A plain page (not Inertia), so a normal link. -->
        <a href="/aplikasi" class="mt-3 inline-flex h-9 items-center rounded-full bg-berry px-4 text-sm font-semibold text-white hover:bg-berry-deep">{{ t('aplikasi.download') }}</a>
    </Card>

    <div v-else class="grid gap-4">
        <Card>
            <h2 class="flex items-center gap-2 text-lg font-semibold"><Fingerprint class="size-4 text-ink-muted" aria-hidden="true" />{{ t('aplikasi.lock') }}</h2>
            <p class="mt-1 text-sm text-ink-muted">{{ t('aplikasi.lockBody') }}</p>
            <div class="mt-3">
                <Toggle :model-value="lock.enabled" :label="t('aplikasi.lockToggle')" @update:model-value="toggleLock" />
            </div>
            <p v-if="lock.availability === 'none'" class="mt-1 text-xs text-warning">{{ t('aplikasi.lockNone') }}</p>
            <p v-else-if="lock.availability === 'unsupported'" class="mt-1 text-xs text-ink-muted">{{ t('aplikasi.lockUnsupported') }}</p>
        </Card>

        <Card v-if="offline">
            <h2 class="flex items-center gap-2 text-lg font-semibold"><CloudOff class="size-4 text-ink-muted" aria-hidden="true" />{{ t('aplikasi.offline') }}</h2>
            <p class="mt-1 text-sm text-ink-muted">{{ t('aplikasi.offlineBody') }}</p>
            <div class="mt-3">
                <Toggle :model-value="offline.enabled" :label="t('aplikasi.offlineToggle')" @update:model-value="(value) => changeOffline({ enabled: value })" />
            </div>
            <template v-if="offline.enabled">
                <p class="mt-2 text-sm font-semibold text-plum">{{ t('aplikasi.offlineUsed', { mb: megabytes(offline.bytes), limit: offline.limitMb, pages: offline.pages, files: offline.files }) }}</p>
                <p v-if="offline.waiting" class="mt-1 text-xs text-warning">{{ t('aplikasi.offlineWaiting', { count: offline.waiting }) }}</p>
                <p class="mt-3 text-xs font-semibold text-ink-muted">{{ t('aplikasi.offlineLimit') }}</p>
                <div class="mt-1 flex flex-wrap gap-2" role="radiogroup" :aria-label="t('aplikasi.offlineLimit')">
                    <Button v-for="mb in limits" :key="mb" size="sm" :variant="offline.limitMb === mb ? 'primary' : 'soft'" role="radio" :aria-checked="offline.limitMb === mb" @click="changeOffline({ limitMb: mb })">{{ mb >= 1000 ? '1 GB' : `${mb} MB` }}</Button>
                </div>
                <Button size="sm" variant="ghost" class="mt-3" @click="clearOpen = true">{{ t('aplikasi.offlineClear') }}</Button>
            </template>
            <p class="mt-2 text-xs text-ink-muted">{{ t('aplikasi.offlineHint') }}</p>
        </Card>

        <Card>
            <h2 class="flex items-center gap-2 text-lg font-semibold"><AlarmClock class="size-4 text-ink-muted" aria-hidden="true" />{{ t('aplikasi.reminders') }}</h2>
            <p class="mt-1 text-sm text-ink-muted">{{ t('aplikasi.remindersBody') }}</p>
            <p v-if="reminders" class="mt-2 text-sm font-semibold text-plum">{{ reminders.count ? t('aplikasi.remindersCount', { count: reminders.count }) : t('aplikasi.remindersNone') }}</p>
            <p v-if="reminders && !reminders.notifications" class="mt-1 text-xs text-warning">{{ t('aplikasi.notifOff') }}</p>
            <p v-if="reminders && !reminders.exact" class="mt-1 text-xs text-warning">{{ t('aplikasi.notExact') }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <Button size="sm" @click="tryReminder">{{ t('aplikasi.test') }}</Button>
                <Button v-if="reminders && !reminders.exact" size="sm" variant="secondary" @click="openAlarmSettings">{{ t('aplikasi.allowExact') }}</Button>
                <Button size="sm" variant="ghost" @click="openAppSettings">{{ t('aplikasi.battery') }}</Button>
            </div>
            <p class="mt-2 text-xs text-ink-muted">{{ t('aplikasi.batteryHint') }}</p>
        </Card>

        <Card>
            <h2 class="flex items-center gap-2 text-lg font-semibold"><LayoutGrid class="size-4 text-ink-muted" aria-hidden="true" />{{ t('aplikasi.widget') }}</h2>
            <p class="mt-1 text-sm text-ink-muted">{{ t('aplikasi.widgetBody') }}</p>
            <p v-if="widget.placed" class="mt-2 text-sm font-semibold text-plum">{{ t('aplikasi.widgetPlaced') }}</p>
            <Button v-if="widget.canPin" size="sm" class="mt-3" @click="pinWidget">{{ t('aplikasi.widgetAdd') }}</Button>
            <p v-else class="mt-2 text-xs text-ink-muted">{{ t('aplikasi.widgetManual') }}</p>
        </Card>

        <Card>
            <h2 class="flex items-center gap-2 text-lg font-semibold"><Share2 class="size-4 text-ink-muted" aria-hidden="true" />{{ t('aplikasi.share') }}</h2>
            <p class="mt-1 text-sm text-ink-muted">{{ t('aplikasi.shareBody') }}</p>
        </Card>

        <Card>
            <h2 class="flex items-center gap-2 text-lg font-semibold"><Music class="size-4 text-ink-muted" aria-hidden="true" />{{ t('aplikasi.sounds') }}</h2>
            <p class="mt-1 text-sm text-ink-muted">{{ t('aplikasi.soundsBody') }}</p>
            <div class="mt-3"><Toggle :model-value="sound" :label="t('aplikasi.soundToggle')" @update:model-value="toggleSound" /></div>
            <div class="mt-2 flex flex-wrap gap-2">
                <Button v-for="name in tones" :key="name" size="sm" variant="soft" @click="playSound(name, { force: true })">▶ {{ t(`aplikasi.tone.${name}`) }}</Button>
            </div>
        </Card>
    </div>

    <ConfirmDialog v-model:open="clearOpen" :title="t('aplikasi.offlineClearTitle')" :description="t('aplikasi.offlineClearBody')" :confirm-label="t('aplikasi.offlineClear')" danger @confirm="emptyOffline" />
</template>
