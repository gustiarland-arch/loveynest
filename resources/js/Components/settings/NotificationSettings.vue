<script setup>
import { computed, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Bell, BellOff, Send, Clock, Volume2 } from '@lucide/vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Toggle from '@/Components/ui/Toggle.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import TimeField from '@/Components/ui/TimeField.vue';
import { useToast } from '@/composables/useToast';
import { NATIVE } from '@/lib/native';
import { permission, pushSupport, status, enable, disable, sendTest } from '@/lib/push';
import { soundOn, setSound, playChime, unlockAudio } from '@/lib/sound';
import { formatDateTime } from '@/lib/dates';
import { t } from '@/i18n';

const props = defineProps({
    settings: { type: Object, required: true },
});

const toast = useToast();
const types = ['chat_message', 'event_reminder', 'task_reminder', 'partner_activity', 'couple_update'];
const prefs = ref(JSON.parse(JSON.stringify(props.settings.preferences)));
const busy = ref(false);
const sound = ref(soundOn());
function saveSound(value) {
    setSound(value);
    if (value) tryChime();
}
function tryChime() {
    unlockAudio();
    if (!playChime({ force: true })) toast.error(t('settings.soundBlocked'));
}

// 'loading' | 'unsupported' | 'insecure' | 'not-configured' | 'denied' | 'active' | 'inactive' | 'expired'
const device = ref('loading');

async function refreshDevice() {
    const support = pushSupport();
    if (support !== 'supported') return (device.value = support);
    if (!props.settings.push.configured) return (device.value = 'not-configured');
    if (props.settings.push.problem) return (device.value = props.settings.push.problem);
    if (permission() === 'denied') return (device.value = 'denied');
    try {
        const state = await status();
        device.value = state === 'active' ? 'active' : state === 'expired' ? 'expired' : 'inactive';
    } catch {
        device.value = 'inactive';
    }
}

onMounted(refreshDevice);

const deviceText = computed(
    () =>
        ({
            loading: '',
            unsupported: t('settings.deviceUnsupported'),
            insecure: t('settings.deviceInsecure'),
            'not-configured': t(NATIVE ? 'settings.deviceNotConfiguredApp' : 'settings.deviceNotConfigured'),
            duplicate: t('settings.deviceDuplicate'),
            mismatch: t('settings.deviceMismatch'),
            denied: t('settings.deviceDenied'),
            active: t('settings.deviceActive'),
            inactive: t('settings.deviceInactive'),
            expired: t('settings.deviceExpired'),
        })[device.value],
);
const canEnable = computed(() => ['inactive', 'expired'].includes(device.value));

async function turnOn() {
    busy.value = true;
    try {
        const result = await enable(props.settings.push.publicKey);
        if (result === 'active') toast.success(t('settings.enabled'));
        else if (result === 'dismissed') toast.error(t('settings.dismissed'));
        await refreshDevice();
        router.reload({ only: ['notifications'] });
    } catch (error) {
        // subscribe() fails when the browser cannot reach its push service (offline, blocked,
        // or a private window); saving fails when our server rejects it.
        toast.error(error?.name === 'NotAllowedError' ? t('settings.deviceDenied') : error?.status ? error.message : t('settings.subscribeFailed'));
        await refreshDevice();
    } finally {
        busy.value = false;
    }
}

async function turnOff() {
    busy.value = true;
    await disable();
    toast.success(t('settings.disabled'));
    await refreshDevice();
    router.reload({ only: ['notifications'] });
    busy.value = false;
}

async function test() {
    busy.value = true;
    try {
        const result = await sendTest();
        if (result.sent) toast.success(t('settings.testSent', { count: result.sent }));
        else toast.error(t('settings.testFailed') + (result.status ? ` (${result.status}${result.reason ? ': ' + result.reason : ''})` : ''), { timeout: 12000 });
        await refreshDevice();
    } catch (error) {
        toast.error(error.message);
    } finally {
        busy.value = false;
    }
}

function save(type) {
    router.patch('/settings/notifications', { preferences: { [type]: prefs.value[type] } }, { preserveScroll: true, preserveState: true });
}

const schedulerText = computed(() => {
    const last = props.settings.schedulerLastRun;
    if (!last) return t('settings.schedulerNever');
    const stale = Date.now() - new Date(last).getTime() > 5 * 60 * 1000;
    return t(stale ? 'settings.schedulerStale' : 'settings.schedulerOk', { time: formatDateTime(last) });
});
const digest = ref(props.settings.digest ?? 'off');
const digestOptions = ['off', 'daily', 'weekly'].map((value) => ({ value, label: t(`settings.digestOpt.${value}`) }));
function saveDigest() {
    router.patch('/settings/digest', { digest: digest.value }, { preserveScroll: true });
}
const quietOn = ref(!!props.settings.quiet?.start);
const quiet = ref({ start: props.settings.quiet?.start ?? '22:00', end: props.settings.quiet?.end ?? '07:00' });
const quietErrors = ref({});
function saveQuiet() {
    quietErrors.value = {};
    const payload = quietOn.value ? { start: quiet.value.start, end: quiet.value.end } : { start: null, end: null };
    router.patch('/settings/quiet-hours', payload, { preserveScroll: true, onError: (e) => (quietErrors.value = e) });
}
const schedulerStale = computed(() => !props.settings.schedulerLastRun || Date.now() - new Date(props.settings.schedulerLastRun).getTime() > 5 * 60 * 1000);
</script>

<template>
    <Card id="notifikasi">
        <h2 class="flex items-center gap-2 text-lg font-semibold"><Bell class="size-4 text-berry-ink" aria-hidden="true" />{{ $t('settings.notifications') }}</h2>
        <p class="mt-1 text-sm text-ink-muted">{{ $t('settings.notificationsLead') }}</p>

        <section class="mt-5 rounded-[1rem] bg-plum/[0.05] p-4" aria-live="polite">
            <h3 class="text-sm font-semibold text-plum">{{ $t('settings.device') }}</h3>
            <p class="mt-1 text-sm" :class="device === 'active' ? 'text-sage-ink font-semibold' : 'text-ink-muted'">{{ deviceText }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <Button v-if="canEnable" size="sm" :loading="busy" @click="turnOn"><Bell class="size-4" aria-hidden="true" />{{ $t('settings.enable') }}</Button>
                <template v-if="device === 'active'">
                    <Button size="sm" variant="soft" :loading="busy" @click="test"><Send class="size-4" aria-hidden="true" />{{ $t('settings.test') }}</Button>
                    <Button size="sm" variant="ghost" :disabled="busy" @click="turnOff"><BellOff class="size-4" aria-hidden="true" />{{ $t('settings.disable') }}</Button>
                </template>
            </div>
            <p v-if="settings.push.devices" class="mt-3 text-xs text-ink-muted">{{ $t('settings.otherDevices', { count: settings.push.devices }) }}</p>
            <p class="mt-2 text-xs text-ink-muted">{{ $t('settings.honesty') }}</p>
        </section>

        <section class="mt-4 rounded-[1rem] bg-plum/[0.05] p-4">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-plum">{{ $t('settings.sound') }}</h3>
                    <p class="mt-1 text-xs text-ink-muted">{{ $t('settings.soundHint') }}</p>
                </div>
                <Toggle v-model="sound" :label="$t('settings.sound')" hide-label @update:model-value="saveSound" />
            </div>
            <Button size="sm" variant="soft" class="mt-3" @click="tryChime"><Volume2 class="size-4" aria-hidden="true" />{{ $t('settings.soundTest') }}</Button>
        </section>

        <div class="mt-5">
            <div class="grid grid-cols-[1fr_auto_auto] items-end gap-x-2 border-b border-line pb-2 text-xs font-semibold text-ink-muted">
                <span />
                <span class="w-14 text-center leading-tight">{{ $t('settings.inApp') }}</span>
                <span class="w-14 text-center leading-tight">{{ $t('settings.push') }}</span>
            </div>
            <fieldset v-for="type in types" :key="type" class="grid grid-cols-[1fr_auto_auto] items-center gap-x-2 border-b border-line py-2 last:border-0">
                <legend class="sr-only">{{ $t(`settings.types.${type}`) }}</legend>
                <span class="text-sm font-semibold text-plum" aria-hidden="true">
                    {{ $t(`settings.types.${type}`) }}
                    <span v-if="type === 'chat_message'" class="block text-xs font-normal text-ink-muted">{{ $t('settings.chatInAppNote') }}</span>
                </span>
                <span class="flex w-14 justify-center">
                    <Toggle v-if="type !== 'chat_message'" v-model="prefs[type].in_app" :label="`${$t(`settings.types.${type}`)}: ${$t('settings.inApp')}`" hide-label @update:model-value="save(type)" />
                </span>
                <span class="flex w-14 justify-center">
                    <Toggle v-model="prefs[type].push" :label="`${$t(`settings.types.${type}`)}: ${$t('settings.push')}`" hide-label @update:model-value="save(type)" />
                </span>
            </fieldset>
        </div>

        <div class="mt-4 rounded-md bg-blush-soft p-4">
            <Toggle v-model="prefs.chat_message.show_preview" :label="$t('settings.showPreview')" @update:model-value="save('chat_message')" />
            <p class="mt-1 text-xs text-ink-muted">{{ $t('settings.showPreviewHint') }}</p>
        </div>

        <div class="mt-4 rounded-md bg-plum/[0.05] p-4">
            <SelectField v-model="digest" :label="$t('settings.digest')" :options="digestOptions" @update:model-value="saveDigest" />
            <p class="mt-1 text-xs text-ink-muted">{{ $t('settings.digestHint') }}</p>
        </div>

        <div class="mt-4 rounded-md bg-plum/[0.05] p-4">
            <Toggle v-model="quietOn" :label="$t('settings.quiet')" @update:model-value="quietOn || saveQuiet()" />
            <p class="mt-1 text-xs text-ink-muted">{{ $t('settings.quietHint') }}</p>
            <form v-if="quietOn" class="mt-3 grid grid-cols-2 items-end gap-3" novalidate @submit.prevent="saveQuiet">
                <TimeField v-model="quiet.start" :label="$t('settings.quietFrom')" :error="quietErrors.start" />
                <TimeField v-model="quiet.end" :label="$t('settings.quietTo')" :error="quietErrors.end" />
                <Button type="submit" size="sm" variant="secondary" class="col-span-2">{{ $t('common.save') }}</Button>
            </form>
        </div>

        <p class="mt-4 flex items-start gap-2 text-xs" :class="schedulerStale ? 'text-warning' : 'text-ink-muted'">
            <Clock class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
            <span><strong class="font-semibold">{{ $t('settings.scheduler') }}:</strong> {{ schedulerText }}</span>
        </p>
    </Card>
</template>
